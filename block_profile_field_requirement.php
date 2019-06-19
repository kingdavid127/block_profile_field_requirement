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
 * Profile field requirement
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/profile/lib.php');

class block_profile_field_requirement extends block_base {

    /**
     * @throws coding_exception
     */
    function init() {
        $this->title = get_string('pluginname', 'block_profile_field_requirement');
    }

    /**
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * @return array
     */
    function applicable_formats() {
        return array('site' => true, 'course' => true);
    }

    /**
     * @return null|stdObject
     * @throws coding_exception
     * @throws moodle_exception
     */
    function get_content() {
        global $COURSE, $USER;

        if ($this->content !== NULL) {
            return $this->content;
        }

        if ($COURSE->id == SITEID) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($COURSE->id);
        }

        if (isloggedin() && !isguestuser()) {
            if (!empty($this->config->fields)) {
                foreach ($this->config->fields as $field) {
                    $profile = new \profile_field_base($field, $USER->id);
                    if ($this->page->pagetype !== 'blocks-profile_field_requirement-update'
                            &&
                            (
                                    empty($profile->data)
                                    || (!empty($this->config->requireverification) &&
                                            !get_user_preferences('block_field_requirement_' . $this->instance->id))
                            )
                            && !has_capability('block/profile_field_requirement:addinstance', $context)
                    ) {
                        redirect(new \moodle_url('/blocks/profile_field_requirement/update.php',
                                [
                                        'instanceid' => $this->instance->id,
                                        'courseid' => $COURSE->id,
                                        'returnurl' => $this->page->url->out_as_local_url(false)
                                ]));
                    }
                }
            }

            if (!empty($this->config->corefields)) {
                foreach ($this->config->corefields as $field) {
                    if ($this->page->pagetype !== 'blocks-profile_field_requirement-update'
                            &&
                            (
                                    empty($USER->{$field})
                                    || (!empty($this->config->requireverification) &&
                                            !get_user_preferences('block_field_requirement_' . $this->instance->id))
                            )
                            && !has_capability('block/profile_field_requirement:addinstance', $context)
                    ) {
                        redirect(new \moodle_url('/blocks/profile_field_requirement/update.php',
                                [
                                        'instanceid' => $this->instance->id,
                                        'courseid' => $COURSE->id,
                                        'returnurl' => $this->page->url->out_as_local_url(false)
                                ]));
                    }
                }
            }
        }

        return null;
    }
}
