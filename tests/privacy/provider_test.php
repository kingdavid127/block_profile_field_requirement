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

namespace block_profile_field_requirement\privacy;

use block_profile_field_requirement\requirement;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the privacy provider.
 *
 * @package   block_profile_field_requirement
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The plugin declares the preference it stores.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_profile_field_requirement');
        $collection = provider::get_metadata($collection);

        $types = $collection->get_collection();
        $this->assertCount(1, $types);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\user_preference::class, $types[0]);
        $this->assertSame(requirement::PREFERENCE_PREFIX, $types[0]->get_name());
    }

    /**
     * Verification preferences are exported, and unrelated ones are not.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        set_user_preference(requirement::PREFERENCE_PREFIX . '7', 1, $user);
        set_user_preference(requirement::PREFERENCE_PREFIX . '9', 1, $user);
        set_user_preference('some_unrelated_preference', 'nope', $user);

        provider::export_user_preferences($user->id);

        $context = \context_system::instance();
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $exported = $writer->get_user_preferences('block_profile_field_requirement');

        $this->assertObjectHasProperty(requirement::PREFERENCE_PREFIX . '7', $exported);
        $this->assertObjectHasProperty(requirement::PREFERENCE_PREFIX . '9', $exported);
        $this->assertObjectNotHasProperty('some_unrelated_preference', $exported);
    }

    /**
     * A user with no preferences exports nothing.
     */
    public function test_export_user_preferences_none(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences($user->id);

        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
