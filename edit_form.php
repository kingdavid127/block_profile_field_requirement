<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing profile field requirements block instances.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/profile/lib.php');

class block_profile_field_requirement_edit_form extends block_edit_form {

    /**
     * @param $mform
     *
     * @throws coding_exception
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader',
            get_string('requiredfields', 'block_profile_field_requirement'));

        $mform->addElement('textarea', 'config_updatedesc',
            get_string('desctext', 'block_profile_field_requirement'), 'rows="4" cols="40"');
        $mform->setType('config_updatedesc', PARAM_TEXT);
        $mform->setDefault('config_updatedesc', get_string('updateprofile', 'block_profile_field_requirement'));

        $fields = profile_get_custom_fields();
        if (empty($fields)) {
            $mform->addElement('html', get_string('nofields', 'block_profile_field_requirement'));
        }

        $profile_fields = array();
        foreach ($fields as $field) {
            $profile_fields[$field->id] = $field->name . ' (' . $field->shortname . ')';
        }

        if ($profile_fields) {
            $mform->addElement('select', 'config_fields',
                    get_string('profilefields', 'block_profile_field_requirement'),
                    $profile_fields, [])->setMultiple(true);
        }

        $corefields = array(
            'icq' => 'icq',
            'skype' => 'skype',
            'yahoo' => 'yahoo',
            'aim' => 'aim',
            'msn' => 'msn',
            'phone1' => get_string('phone1'),
            'phone2' => get_string('phone2'),
            'institution' => get_string('institution'),
            'department' => get_string('department'),
            'address' => get_string('address'),
            'city' => get_string('city'),
            'country' => get_string('country')
        );

        $mform->addElement('select', 'config_corefields',
            get_string('corefields', 'block_profile_field_requirement'),
            $corefields, [])->setMultiple(true);

        $mform->addElement('advcheckbox', 'config_requireverification', get_string('requireverification', 'block_profile_field_requirement'));

    }
}