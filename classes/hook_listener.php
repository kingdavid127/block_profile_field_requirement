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

use core\hook\output\before_http_headers;
use moodle_url;

/**
 * Redirects users who have outstanding profile requirements.
 *
 * The check runs from before_http_headers, which is dispatched at the very top
 * of core_renderer::header() while $PAGE is still in STATE_BEFORE_HEADER. That
 * means redirect() can issue a real 302 rather than the JavaScript fallback the
 * old get_content() implementation relied on.
 *
 * Which pages are covered is decided entirely by block placement: load_blocks()
 * applies the context path, showinsubcontexts, pagetypepattern and subpagepattern
 * matching for us, so the block's "Where this block appears" settings are the
 * scope of the requirement.
 *
 * @package   block_profile_field_requirement
 * @copyright 2019 MLC
 * @copyright 2026 Dragonfly EdTech
 * @author    David Saylor <david.saylor@dragonflyedtech.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Page types that must stay reachable, or the user has no way to comply.
     *
     * @var string[]
     */
    private const EXEMPT_PAGETYPES = [
        'blocks-profile_field_requirement-update',
        'admin-tool-policy-index',
        'user-policy',
    ];

    /**
     * Bounce the user to the update form if any block on this page is unsatisfied.
     *
     * @param before_http_headers $hook
     */
    public static function enforce_requirements(before_http_headers $hook): void {
        global $PAGE, $USER;

        // Cheap bail-outs first, before touching the block manager.
        if (CLI_SCRIPT || AJAX_SCRIPT || during_initial_install()) {
            return;
        }
        if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return;
        }
        if (in_array($PAGE->pagetype, self::EXEMPT_PAGETYPES, true) || !$PAGE->has_set_url()) {
            return;
        }
        if (!requirement::any_instance_exists()) {
            return;
        }

        // Load exactly the way core would. block_manager::load_blocks() only ever
        // runs once per page, so forcing the hidden blocks out here would also hide
        // them from moodle_page::starting_output() later, leaving editing users
        // unable to see or unhide any block on the site. Hidden instances of this
        // block are skipped individually below instead.
        $PAGE->blocks->load_blocks();
        if (!$PAGE->blocks->is_block_present(requirement::BLOCKNAME)) {
            return;
        }

        foreach ($PAGE->blocks->get_regions() as $region) {
            foreach ($PAGE->blocks->get_blocks_for_region($region) as $block) {
                if ($block->instance->blockname !== requirement::BLOCKNAME) {
                    continue;
                }
                // A hidden instance enforces nothing.
                if (empty($block->instance->visible)) {
                    continue;
                }
                // Anyone who can configure the block is exempt, otherwise they could
                // not reach the course to fix a misconfiguration.
                if (has_capability('block/profile_field_requirement:addinstance', $block->context)) {
                    continue;
                }
                if (requirement::is_satisfied($block, $USER)) {
                    continue;
                }

                redirect(new moodle_url('/blocks/profile_field_requirement/update.php', [
                    'instanceid' => $block->instance->id,
                    'courseid' => $PAGE->course->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false),
                ]));
            }
        }
    }
}
