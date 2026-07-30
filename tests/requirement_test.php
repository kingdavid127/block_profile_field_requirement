<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_profile_field_requirement;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the requirement resolver.
 *
 * @package   block_profile_field_requirement
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(requirement::class)]
final class requirement_test extends \advanced_testcase {
    /**
     * Build an unsaved block object carrying the given config.
     *
     * @param array $config instance configuration
     * @param int $instanceid id the verification preference is keyed on
     * @return \block_base
     */
    private function make_block(array $config, int $instanceid = 1): \block_base {
        $block = block_instance(requirement::BLOCKNAME);
        $block->config = (object) $config;
        $block->instance = (object) ['id' => $instanceid];

        return $block;
    }

    /**
     * Load a user with custom profile data attached.
     *
     * @param int $userid
     * @return \stdClass
     */
    private function load_user(int $userid): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = get_complete_user_data('id', $userid);
        profile_load_data($user);

        return $user;
    }

    /**
     * The removed messaging fields must never be offered again.
     *
     * Requiring one of these made the course permanently unreachable, because the
     * column no longer exists so the value could never be filled in.
     */
    public function test_get_core_field_options_excludes_removed_fields(): void {
        $options = requirement::get_core_field_options();

        foreach (['icq', 'skype', 'yahoo', 'aim', 'msn'] as $gone) {
            $this->assertArrayNotHasKey($gone, $options);
            $this->assertFalse(requirement::core_field_exists($gone));
        }

        // The fields that do still exist are offered, with real labels.
        foreach (['address', 'city', 'country', 'department', 'institution', 'phone1', 'phone2'] as $live) {
            $this->assertArrayHasKey($live, $options);
            $this->assertNotEmpty($options[$live]);
            $this->assertTrue(requirement::core_field_exists($live));
        }
    }

    /**
     * Core field types come from core rather than being assumed.
     */
    public function test_get_core_field_type(): void {
        $this->assertSame(PARAM_TEXT, requirement::get_core_field_type('city'));
        $this->assertSame(PARAM_ALPHA, requirement::get_core_field_type('country'));
        $this->assertSame(PARAM_NOTAGS, requirement::get_core_field_type('phone1'));
    }

    /**
     * A block with nothing selected asks for nothing.
     */
    public function test_unconfigured_block_is_satisfied(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $block = $this->make_block([]);

        $this->assertFalse(requirement::is_configured($block));
        $this->assertTrue(requirement::is_satisfied($block, $user));
    }

    /**
     * Configured core fields that are already filled in are satisfied.
     */
    public function test_core_field_satisfied_when_filled(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['city' => 'Wellington']);
        $block = $this->make_block(['corefields' => ['city']]);

        $this->assertTrue(requirement::is_configured($block));
        $this->assertSame([], requirement::get_outstanding_core_fields($block, $user));
        $this->assertTrue(requirement::is_satisfied($block, $user));
    }

    /**
     * An empty core field is outstanding.
     */
    public function test_core_field_outstanding_when_empty(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['city' => '']);
        $block = $this->make_block(['corefields' => ['city']]);

        $this->assertSame(['city'], requirement::get_outstanding_core_fields($block, $user));
        $this->assertFalse(requirement::is_satisfied($block, $user));
    }

    /**
     * A configured field that no longer exists is ignored rather than blocking.
     */
    public function test_removed_core_field_in_saved_config_is_ignored(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        // Simulates an instance configured before the field was dropped from core.
        $block = $this->make_block(['corefields' => ['icq', 'skype']]);

        $this->assertSame([], requirement::get_configured_core_fields($block));
        $this->assertFalse(requirement::is_configured($block));
        $this->assertTrue(requirement::is_satisfied($block, $user));
    }

    /**
     * An empty custom profile field is outstanding, and filling it satisfies.
     */
    public function test_custom_field_outstanding_then_satisfied(): void {
        $this->resetAfterTest();

        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'jobtitle',
            'name' => 'Job title',
        ]);

        $user = $this->getDataGenerator()->create_user();
        // The is_editable() check runs against the current user, so the
        // field is only editable once the owner is logged in.
        $this->setUser($user);

        $block = $this->make_block(['fields' => [$field->id]]);

        $loaded = $this->load_user($user->id);
        $this->assertCount(1, requirement::get_editable_profile_fields($block, $loaded));
        $this->assertCount(1, requirement::get_outstanding_profile_fields($block, $loaded));
        $this->assertFalse(requirement::is_satisfied($block, $loaded));

        // Fill it in.
        profile_save_data((object) ['id' => $user->id, 'profile_field_jobtitle' => 'Developer']);

        $loaded = $this->load_user($user->id);
        $this->assertSame([], requirement::get_outstanding_profile_fields($block, $loaded));
        $this->assertTrue(requirement::is_satisfied($block, $loaded));
    }

    /**
     * A checkbox answered "no" stores '0', which is a real answer.
     *
     * The old implementation used empty() and so treated this as unfilled,
     * trapping the user in a redirect loop they could never escape.
     */
    public function test_checkbox_answered_no_counts_as_answered(): void {
        $this->resetAfterTest();

        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'checkbox',
            'shortname' => 'agreed',
            'name' => 'Agreed',
        ]);

        $user = $this->getDataGenerator()->create_user();
        $block = $this->make_block(['fields' => [$field->id]]);

        $this->setUser($user);
        profile_save_data((object) ['id' => $user->id, 'profile_field_agreed' => 0]);

        $loaded = $this->load_user($user->id);
        $this->assertSame([], requirement::get_outstanding_profile_fields($block, $loaded));
        $this->assertTrue(requirement::is_satisfied($block, $loaded));
    }

    /**
     * A field the user cannot edit is dropped from the requirement.
     *
     * Otherwise the block would demand something the form can never render.
     */
    public function test_locked_field_is_not_required(): void {
        $this->resetAfterTest();

        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'internalref',
            'name' => 'Internal reference',
            'visible' => PROFILE_VISIBLE_NONE,
            'locked' => 1,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $block = $this->make_block(['fields' => [$field->id]]);
        $loaded = $this->load_user($user->id);

        $this->assertSame([], requirement::get_editable_profile_fields($block, $loaded));
        $this->assertSame([], requirement::get_outstanding_profile_fields($block, $loaded));
        $this->assertTrue(requirement::is_satisfied($block, $loaded));
    }

    /**
     * A visible but locked field is not required either.
     *
     * The core profile form freezes locked fields for anyone without
     * moodle/user:update, so the user could never satisfy such a requirement.
     */
    public function test_visible_locked_field_is_not_required(): void {
        $this->resetAfterTest();

        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'staffid',
            'name' => 'Staff ID',
            'visible' => PROFILE_VISIBLE_ALL,
            'locked' => 1,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $block = $this->make_block(['fields' => [$field->id]]);
        $loaded = $this->load_user($user->id);

        $this->assertSame([], requirement::get_editable_profile_fields($block, $loaded));
        $this->assertTrue(requirement::is_satisfied($block, $loaded));

        // Someone who can edit any profile is not frozen, so the field still counts.
        $this->setAdminUser();
        $this->assertCount(1, requirement::get_editable_profile_fields($block, $loaded));
        $this->assertFalse(requirement::is_satisfied($block, $loaded));
    }

    /**
     * A deleted field left in the saved config does not block anyone.
     */
    public function test_deleted_custom_field_in_saved_config_is_ignored(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $block = $this->make_block(['fields' => [123456]]);

        $loaded = $this->load_user($user->id);
        $this->assertSame([], requirement::get_outstanding_profile_fields($block, $loaded));
        $this->assertTrue(requirement::is_satisfied($block, $loaded));
    }

    /**
     * Verification is required independently of the fields being filled in.
     */
    public function test_verification_required(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['city' => 'Wellington']);
        $block = $this->make_block(['corefields' => ['city'], 'requireverification' => 1], 42);

        $this->assertTrue(requirement::requires_verification($block));
        $this->assertFalse(requirement::is_verified($block, $user));
        $this->assertFalse(requirement::is_satisfied($block, $user));

        set_user_preference(requirement::PREFERENCE_PREFIX . 42, 1, $user);

        $this->assertTrue(requirement::is_verified($block, $user));
        $this->assertTrue(requirement::is_satisfied($block, $user));
    }

    /**
     * Verification is tracked per block instance.
     */
    public function test_verification_is_per_instance(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['city' => 'Wellington']);
        set_user_preference(requirement::PREFERENCE_PREFIX . 42, 1, $user);

        $confirmed = $this->make_block(['corefields' => ['city'], 'requireverification' => 1], 42);
        $other = $this->make_block(['corefields' => ['city'], 'requireverification' => 1], 43);

        $this->assertTrue(requirement::is_satisfied($confirmed, $user));
        $this->assertFalse(requirement::is_satisfied($other, $user));
    }

    /**
     * Verification alone does not make an otherwise unconfigured block demand anything.
     */
    public function test_verification_without_fields_is_satisfied(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $block = $this->make_block(['requireverification' => 1]);

        $this->assertTrue(requirement::is_satisfied($block, $user));
    }

    /**
     * The site wide instance check reflects reality and honours its cache purge.
     */
    public function test_any_instance_exists(): void {
        $this->resetAfterTest();

        requirement::purge_instance_cache();
        $this->assertFalse(requirement::any_instance_exists());

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_block(requirement::BLOCKNAME, [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);

        // The generator writes the row directly, so the cache must be cleared the
        // way instance_create() does at runtime.
        requirement::purge_instance_cache();
        $this->assertTrue(requirement::any_instance_exists());
    }
}
