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

use block_base;
use coding_exception;
use context_system;
use core_cache\cache;
use core_user;
use profile_field_base;
use stdClass;

/**
 * Works out what a given block instance still requires from a given user.
 *
 * This is the single source of truth shared by the hook listener (which decides
 * whether to redirect) and update.php (which renders the form). Keeping both
 * sides on the same logic is what prevents the redirect loops that arise when
 * the block demands a field the form never renders.
 *
 * @package   block_profile_field_requirement
 * @copyright 2019 MLC
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requirement {
    /** @var string Block name as stored in the block_instances table. */
    public const BLOCKNAME = 'profile_field_requirement';

    /** @var string Prefix for the per-instance verification user preference. */
    public const PREFERENCE_PREFIX = 'block_field_requirement_';

    /**
     * Core user fields an administrator may mark as required.
     *
     * Only fields that still exist as columns on the user table are offered. The
     * historic messaging fields (icq, skype, yahoo, aim, msn) were dropped from
     * core and requiring one made the course permanently unreachable.
     *
     * @return array shortname => human readable label
     */
    public static function get_core_field_options(): array {
        $candidates = [
            'address',
            'city',
            'country',
            'department',
            'institution',
            'phone1',
            'phone2',
        ];

        $options = [];
        foreach ($candidates as $name) {
            if (self::core_field_exists($name)) {
                $options[$name] = get_string($name);
            }
        }

        return $options;
    }

    /**
     * Whether a core user field is still a real property of the user record.
     *
     * @param string $name field shortname
     * @return bool
     */
    public static function core_field_exists(string $name): bool {
        try {
            core_user::get_property_definition($name);
        } catch (coding_exception $e) {
            return false;
        }

        return true;
    }

    /**
     * The PARAM_* type core declares for a user field.
     *
     * @param string $name field shortname
     * @return string
     */
    public static function get_core_field_type(string $name): string {
        $definition = core_user::get_property_definition($name);

        return $definition['type'] ?? PARAM_TEXT;
    }

    /**
     * Custom profile field ids this instance has been configured with.
     *
     * @param block_base $block
     * @return int[]
     */
    public static function get_configured_profile_fields(block_base $block): array {
        $fields = (array) ($block->config->fields ?? []);

        return array_values(array_map('intval', $fields));
    }

    /**
     * Core user fields this instance has been configured with, filtered to those that still exist.
     *
     * @param block_base $block
     * @return string[]
     */
    public static function get_configured_core_fields(block_base $block): array {
        $fields = (array) ($block->config->corefields ?? []);

        return array_values(array_filter($fields, [self::class, 'core_field_exists']));
    }

    /**
     * Whether this instance asks for anything at all.
     *
     * @param block_base $block
     * @return bool
     */
    public static function is_configured(block_base $block): bool {
        return (bool) (self::get_configured_profile_fields($block) || self::get_configured_core_fields($block));
    }

    /**
     * Whether the current user may actually change the value of a profile field.
     *
     * This is the same test the core profile form applies: an invisible field is
     * not rendered at all, and a locked one is frozen for everyone without
     * moodle/user:update.
     *
     * @param profile_field_base $field
     * @return bool
     */
    public static function is_field_editable(profile_field_base $field): bool {
        if (!$field->is_editable()) {
            return false;
        }

        return !$field->is_locked() || has_capability('moodle/user:update', context_system::instance());
    }

    /**
     * Configured custom profile fields the user is actually able to edit.
     *
     * A field the user cannot edit can never be satisfied, so requiring it would
     * trap them in a redirect loop. Such fields are dropped from the requirement
     * entirely rather than silently blocking access.
     *
     * @param block_base $block
     * @param stdClass $user
     * @return profile_field_base[] keyed by field id
     */
    public static function get_editable_profile_fields(block_base $block, stdClass $user): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $configured = self::get_configured_profile_fields($block);
        if (!$configured) {
            return [];
        }

        $fields = [];
        foreach (profile_get_user_fields_with_data($user->id) as $field) {
            if (!in_array((int) $field->fieldid, $configured, true)) {
                continue;
            }
            if (!self::is_field_editable($field)) {
                continue;
            }
            $fields[(int) $field->fieldid] = $field;
        }

        return $fields;
    }

    /**
     * Editable custom profile fields the user has not filled in.
     *
     * Emptiness is delegated to profile_field_base::is_empty(), which correctly
     * treats a checkbox answer of '0' as a real answer.
     *
     * @param block_base $block
     * @param stdClass $user
     * @return profile_field_base[] keyed by field id
     */
    public static function get_outstanding_profile_fields(block_base $block, stdClass $user): array {
        $outstanding = [];
        foreach (self::get_editable_profile_fields($block, $user) as $fieldid => $field) {
            if ($field->is_empty()) {
                $outstanding[$fieldid] = $field;
            }
        }

        return $outstanding;
    }

    /**
     * Configured core user fields the user has not filled in.
     *
     * @param block_base $block
     * @param stdClass $user
     * @return string[]
     */
    public static function get_outstanding_core_fields(block_base $block, stdClass $user): array {
        $outstanding = [];
        foreach (self::get_configured_core_fields($block) as $name) {
            if (empty($user->{$name})) {
                $outstanding[] = $name;
            }
        }

        return $outstanding;
    }

    /**
     * Whether this instance asks the user to confirm their details are accurate.
     *
     * @param block_base $block
     * @return bool
     */
    public static function requires_verification(block_base $block): bool {
        return !empty($block->config->requireverification);
    }

    /**
     * Whether the user has confirmed their details for this instance.
     *
     * @param block_base $block
     * @param stdClass $user
     * @return bool
     */
    public static function is_verified(block_base $block, stdClass $user): bool {
        return (bool) get_user_preferences(self::PREFERENCE_PREFIX . $block->instance->id, null, $user);
    }

    /**
     * Whether the user has met everything this instance asks for.
     *
     * @param block_base $block
     * @param stdClass $user
     * @return bool
     */
    public static function is_satisfied(block_base $block, stdClass $user): bool {
        if (!self::is_configured($block)) {
            return true;
        }
        if (self::requires_verification($block) && !self::is_verified($block, $user)) {
            return false;
        }
        if (self::get_outstanding_core_fields($block, $user)) {
            return false;
        }
        if (self::get_outstanding_profile_fields($block, $user)) {
            return false;
        }

        return true;
    }

    /**
     * Whether any instance of this block exists anywhere on the site.
     *
     * Used as a cheap gate so that sites which do not use the block never force
     * the block manager to load early. Kept fresh by instance_create() and
     * instance_delete() on the block class, with a TTL as a backstop.
     *
     * @return bool
     */
    public static function any_instance_exists(): bool {
        global $DB;

        $cache = cache::make('block_profile_field_requirement', 'instances');
        $exists = $cache->get('any');

        if ($exists === false) {
            $exists = $DB->record_exists('block_instances', ['blockname' => self::BLOCKNAME]) ? 1 : 0;
            $cache->set('any', $exists);
        }

        return (bool) $exists;
    }

    /**
     * Forget the cached instance count.
     */
    public static function purge_instance_cache(): void {
        cache::make('block_profile_field_requirement', 'instances')->purge();
    }
}
