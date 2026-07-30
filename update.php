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
 * Lets a user supply the profile fields a block instance requires.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_profile_field_requirement\form\profile_field_form;
use block_profile_field_requirement\requirement;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

$instanceid = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$course = get_course($courseid);
require_login($course);

if ($course->id == SITEID) {
    $context = context_system::instance();
    $PAGE->set_pagelayout('standard');
    $defaultreturn = new moodle_url('/');
} else {
    $context = context_course::instance($course->id);
    $PAGE->set_pagelayout('incourse');
    $defaultreturn = new moodle_url('/course/view.php', ['id' => $course->id]);
}

$returnurl = $returnurl ?: $defaultreturn->out_as_local_url(false);

// The instance must exist, be one of ours, and actually live in the context we
// were called for. Without this, any block instance id could be passed in.
$instance = $DB->get_record(
    'block_instances',
    ['id' => $instanceid, 'blockname' => requirement::BLOCKNAME],
    '*',
    MUST_EXIST
);

// Context ids come back as strings from the path, so normalise before comparing.
$allowedcontextids = array_map('intval', $context->get_parent_context_ids(true));
if (!in_array((int) $instance->parentcontextid, $allowedcontextids, true)) {
    throw new moodle_exception('error_wrongcontext', 'block_profile_field_requirement');
}

$block = block_instance(requirement::BLOCKNAME, $instance, $PAGE);
if (!$block) {
    throw new moodle_exception('error_noinstance', 'block_profile_field_requirement');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/profile_field_requirement/update.php', [
    'instanceid' => $instance->id,
    'courseid' => $course->id,
]));
$PAGE->set_title(get_string('updaterequiredfields', 'block_profile_field_requirement'));
$PAGE->set_heading($course->fullname);

// Nothing configured means nothing to ask for.
if (!requirement::is_configured($block)) {
    redirect($returnurl);
}

$user = get_complete_user_data('id', $USER->id);
profile_load_data($user);

$profileform = new profile_field_form(null, [
    'user' => $user,
    'instanceid' => $instance->id,
    'courseid' => $course->id,
    'returnurl' => $returnurl,
    'updatedesc' => $block->config->updatedesc ?? '',
    'profilefields' => requirement::get_editable_profile_fields($block, $user),
    'corefields' => requirement::get_outstanding_core_fields($block, $user),
    'requireverification' => requirement::requires_verification($block),
]);

if ($profileform->is_cancelled()) {
    redirect($returnurl);
}

if ($profiledata = $profileform->get_data()) {
    $corefields = new stdClass();
    $hascorefields = false;

    foreach ($profiledata as $name => $value) {
        if (strpos($name, 'corefield_') !== 0) {
            continue;
        }
        $corefield = substr($name, strlen('corefield_'));
        if (requirement::core_field_exists($corefield)) {
            $corefields->{$corefield} = $value;
            $hascorefields = true;
        }
        unset($profiledata->{$name});
    }

    if ($hascorefields) {
        $corefields->id = $USER->id;
        $DB->update_record('user', $corefields);
    }

    $profiledata->id = $USER->id;
    profile_save_data($profiledata);

    if (requirement::requires_verification($block)) {
        set_user_preference(
            requirement::PREFERENCE_PREFIX . $instance->id,
            empty($profiledata->profileconfirm) ? 0 : 1
        );
    }

    $USER = get_complete_user_data('id', $USER->id);
    profile_load_custom_fields($USER);
    \core\event\user_updated::create_from_userid($USER->id)->trigger();

    // Only leave once the requirement is genuinely met, otherwise the hook would
    // bounce the user straight back here with no explanation.
    $user = get_complete_user_data('id', $USER->id);
    profile_load_data($user);

    if (requirement::is_satisfied($block, $user)) {
        redirect($returnurl);
    }

    \core\notification::error(get_string('error_stilloutstanding', 'block_profile_field_requirement'));
}

echo $OUTPUT->header();
$profileform->display();
echo $OUTPUT->footer();
