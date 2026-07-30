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

namespace block_profile_field_requirement\form;

use block_profile_field_requirement\requirement;
use core_user;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Lets a user fill in the profile fields a block instance requires.
 *
 * Expected custom data:
 *  - user                stdClass, profile data already loaded
 *  - instanceid          int
 *  - courseid            int
 *  - returnurl           string, already cleaned to a local URL
 *  - updatedesc          string
 *  - profilefields       profile_field_base[] editable custom fields to render
 *  - corefields          string[] core user fields still outstanding
 *  - requireverification bool
 *
 * @package   block_profile_field_requirement
 * @copyright 2019 MLC
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;
        $user = $this->_customdata['user'];

        $mform->addElement('hidden', 'id', $user->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'instanceid', $this->_customdata['instanceid']);
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']);
        $mform->setType('returnurl', PARAM_LOCALURL);

        $mform->addElement(
            'header',
            'updatefields',
            get_string('updaterequiredfields', 'block_profile_field_requirement')
        );

        if (!empty($this->_customdata['updatedesc'])) {
            $mform->addElement('html', format_text($this->_customdata['updatedesc'], FORMAT_HTML));
        }

        foreach ($this->_customdata['profilefields'] as $formfield) {
            $formfield->edit_field($mform);
        }

        foreach ($this->_customdata['corefields'] as $corefield) {
            $this->add_core_field($corefield);
        }

        if ($this->_customdata['requireverification']) {
            $mform->addElement(
                'advcheckbox',
                'profileconfirm',
                '',
                get_string('confirm', 'block_profile_field_requirement'),
                null,
                [0, 1]
            );
            $mform->setType('profileconfirm', PARAM_INT);
            $mform->addRule('profileconfirm', get_string('required'), 'required', null, 'client');

            $preference = requirement::PREFERENCE_PREFIX . $this->_customdata['instanceid'];
            $user->profileconfirm = (int) get_user_preferences($preference, 0, $user);
        }

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);
    }

    /**
     * Let each custom profile field tweak its own element once data is set.
     *
     * This is what freezes a locked field, exactly as the core profile form does.
     * Such fields are already excluded from the requirement, so this only guards
     * against one being rendered by some other route.
     */
    public function definition_after_data(): void {
        parent::definition_after_data();

        foreach ($this->_customdata['profilefields'] as $formfield) {
            $formfield->edit_after_data($this->_form);
        }
    }

    /**
     * Add a single core user field, typed the way core declares it.
     *
     * @param string $corefield field shortname
     */
    private function add_core_field(string $corefield): void {
        $mform = $this->_form;
        $user = $this->_customdata['user'];
        $elementname = 'corefield_' . $corefield;

        if ($corefield === 'country') {
            $choices = get_string_manager()->get_list_of_countries();
            $choices = ['' => get_string('selectacountry') . '...'] + $choices;
            $mform->addElement(
                'select',
                $elementname,
                get_string('selectacountry'),
                $choices,
                user_edit_map_field_purpose($user->id, 'country')
            );
            $mform->setDefault($elementname, core_user::get_property_default('country'));
        } else {
            $mform->addElement('text', $elementname, get_string($corefield));
        }

        $mform->setType($elementname, requirement::get_core_field_type($corefield));
        $mform->addRule($elementname, get_string('required'), 'required', null, 'client');
    }

    /**
     * Validate the submitted data.
     *
     * Custom profile fields are validated by their own datatype class, the same
     * way the core profile form does it.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        foreach ($this->_customdata['profilefields'] as $formfield) {
            $fielderrors = $formfield->edit_validate_field((object) $data);
            if ($fielderrors) {
                $errors += $fielderrors;
            }
        }

        return $errors;
    }
}
