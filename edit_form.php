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

/**
 * Form for editing profile field requirement block instances.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_profile_field_requirement\requirement;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Instance configuration form.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_profile_field_requirement_edit_form extends block_edit_form {
    /**
     * Add the instance specific settings.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform): void {
        $mform->addElement(
            'header',
            'configheader',
            get_string('requiredfields', 'block_profile_field_requirement')
        );

        $mform->addElement(
            'textarea',
            'config_updatedesc',
            get_string('desctext', 'block_profile_field_requirement'),
            'rows="4" cols="40"'
        );
        $mform->setType('config_updatedesc', PARAM_RAW);
        $mform->setDefault(
            'config_updatedesc',
            get_string('updateprofile', 'block_profile_field_requirement')
        );

        $profilefields = [];
        foreach (profile_get_custom_fields() as $field) {
            $profilefields[$field->id] = $field->name . ' (' . $field->shortname . ')';
        }

        if ($profilefields) {
            $mform->addElement(
                'select',
                'config_fields',
                get_string('profilefields', 'block_profile_field_requirement'),
                $profilefields,
                []
            )->setMultiple(true);
        } else {
            $mform->addElement(
                'static',
                'config_nofields',
                get_string('profilefields', 'block_profile_field_requirement'),
                get_string('nofields', 'block_profile_field_requirement')
            );
        }

        $mform->addElement(
            'select',
            'config_corefields',
            get_string('corefields', 'block_profile_field_requirement'),
            requirement::get_core_field_options(),
            []
        )->setMultiple(true);

        $mform->addElement(
            'advcheckbox',
            'config_requireverification',
            get_string('requireverification', 'block_profile_field_requirement')
        );
        $mform->addHelpButton(
            'config_requireverification',
            'requireverification',
            'block_profile_field_requirement'
        );
    }
}
