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
 * Profile field requirement form
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Profile field requirement form
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_form extends \moodleform {

    /**
     * Form definiton.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $user = $this->_customdata['user'];

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id', $user->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'instanceid', $this->_customdata['instanceid']);
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']);
        $mform->setType('returnurl', PARAM_URL);

        $mform->addElement('header', 'update_fields',
            get_string('updaterequiredfields', 'block_profile_field_requirement'));

        $mform->addElement('html', $this->_customdata['updatedesc']);

        $fields = profile_get_user_fields_with_data($user->id);

        foreach ($fields as $formfield) {
            if ($formfield->is_editable()
                && in_array($formfield->fieldid, $this->_customdata['fields'])) {
                $formfield->edit_field($mform);
            }
        }

        foreach ($this->_customdata['corefields'] as $corefield) {
            if (empty($user->{$corefield})) {
                if ($corefield == 'country') {
                    $purpose = user_edit_map_field_purpose($user->id, 'country');
                    $choices = get_string_manager()->get_list_of_countries();
                    $choices = array('' => get_string('selectacountry') . '...') + $choices;
                    $mform->addElement('select', 'corefield_country', get_string('selectacountry'), $choices, $purpose);
                    if (!empty($CFG->country)) {
                        $mform->setDefault('country', core_user::get_property_default('country'));
                    }
                } else {
                    if (get_string_manager()->string_exists($corefield, 'moodle')) {
                        $mform->addElement('text', 'corefield_' . $corefield, get_string($corefield));
                    } else {
                        $mform->addElement('text', 'corefield_' . $corefield, $corefield);
                    }
                }
                $mform->setType('corefield_' . $corefield, PARAM_RAW);
            }
        }

        if ($this->_customdata['requireverification']) {
            $mform->addElement('advcheckbox', 'profileconfirm', '',
                get_string('confirm', 'block_profile_field_requirement'), null, [0, 1]);
            $mform->setType('profileconfirm', PARAM_INT);
            $user->profileconfirm = get_user_preferences('block_field_requirement_' . $this->_customdata['instanceid']);
        }

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);
    }

    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = array();

        foreach ($data as $name => $value) {
            if (strpos($name, 'profile_field_') === 0) {
                $hortname = substr($name, 14);
                $field = $DB->get_record('user_info_field', array('shortname' => $hortname));
                require_once($CFG->dirroot . '/user/profile/field/' . $field->datatype. '/field.class.php');
                $classname = 'profile_field_' . $field->datatype;
                $fieldobject = new $classname($field->id, 0, $field);;
                if ($error = $fieldobject->edit_validate_field((object)$data)) {
                    $errors += $error;
                }
            }
        }
        return $errors;
    }
}
