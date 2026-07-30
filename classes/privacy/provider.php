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
use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for block_profile_field_requirement.
 *
 * The block stores no data of its own beyond a per-instance user preference
 * recording that the user confirmed their details are accurate. The profile
 * data itself is owned and exported by core_user.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    user_preference_provider {
    /**
     * Describe the data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            requirement::PREFERENCE_PREFIX,
            'privacy:metadata:preference:verification'
        );

        return $collection;
    }

    /**
     * Export the verification preferences held for a user.
     *
     * One preference exists per block instance, so the stored names are the
     * prefix followed by the block instance id.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        foreach ((array) get_user_preferences(null, null, $userid) as $name => $value) {
            if (strpos($name, requirement::PREFERENCE_PREFIX) !== 0) {
                continue;
            }
            writer::export_user_preference(
                'block_profile_field_requirement',
                $name,
                (string) $value,
                get_string(
                    'privacy:metadata:preference:verification',
                    'block_profile_field_requirement'
                )
            );
        }
    }
}
