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

require_once("../../config.php");
require_once("custom_profile_fields_form.php");

$instanceid = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$returnurl = required_param('returnurl', PARAM_URL);

$course = $DB->get_record("course", array("id" => $courseid), '*', MUST_EXIST);

if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
    $PAGE->set_pagelayout('standard');
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
    $PAGE->set_pagelayout('incourse');
}

$instance = $DB->get_record('block_instances', array('id' => $instanceid));
$block = block_instance('profile_field_requirement', $instance);

// Page format.
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/blocks/profile_field_requirement/update.php',
    ['id' => $instance->id, 'courseid' => $courseid]));
$PAGE->set_title(get_string('updaterequiredfields', 'block_profile_field_requirement'));

if (!empty($block->config->fields) || !empty($block->config->corefields)) {

    $user = $DB->get_record('user', array('id' => $USER->id), '*', MUST_EXIST);
    // Load custom profile fields data.
    profile_load_data($user);

    $profileform = new profile_field_form(null, [
        'updatedesc' => $block->config->updatedesc,
        'fields' => !empty($block->config->fields) ? $block->config->fields : array(),
        'corefields' => !empty($block->config->corefields) ? $block->config->corefields : array(),
        'instanceid' => $instance->id,
        'courseid' => $course->id,
        'requireverification' => $block->config->requireverification,
        'user' => $user,
        'returnurl' => $returnurl
    ]);

    if ($profileform->is_cancelled()) {
        redirect($returnurl);
    } else if ($profiledata = $profileform->get_data()) {
        if (!empty($profiledata->profileconfirm)) {
            set_user_preference('block_field_requirement_' . $profiledata->instanceid, $profiledata->profileconfirm);
        }

        foreach ($profiledata as $name => $value) {
            if (strpos($name, 'corefield_') === 0) {
                if (!isset($userraw)) {
                    $userraw = new stdClass();
                }
                $corefield = substr($name, 10);
                $userraw->{$corefield} = $value;
                unset($profiledata->{$name});
            }
        }

        if (isset($userraw)) {
            $userraw->id = $profiledata->id;
            $DB->update_record('user', $userraw);
            $USER = get_complete_user_data('id', $USER->id);
        }

        profile_save_data($profiledata);
        \core\event\user_updated::create_from_userid($USER->id)->trigger();
        profile_load_custom_fields($USER);

        redirect($returnurl);
    } else {
        $profileform->set_data($profiledata);
    }
    echo $OUTPUT->header();
    $profileform->display();
    echo $OUTPUT->footer();
}
