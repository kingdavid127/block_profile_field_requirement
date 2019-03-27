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
        global $USER;
        $mform = $this->_form;

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id', $USER->id);
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

        $fields = profile_get_user_fields_with_data($USER->id);

        foreach ($fields as $formfield) {
            if ($formfield->is_editable()
                && $formfield->is_empty()
                && in_array($formfield->fieldid, $this->_customdata['fields'])) {
                $formfield->edit_field($mform);
            }
        }

        $this->add_action_buttons(true, get_string('updatemyprofile'));
    }

}
