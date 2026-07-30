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
 * Profile field requirement block.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_profile_field_requirement\requirement;

/**
 * Marks the pages on which a set of profile fields is required.
 *
 * The block renders nothing to learners. Its placement is what defines the scope
 * of the requirement, and the redirect itself is issued from a before_http_headers
 * hook listener so that it happens before any output is sent.
 *
 * @package    block_profile_field_requirement
 * @copyright  2019 MLC
 * @copyright  2026 Dragonfly EdTech
 * @author     David Saylor <david.saylor@dragonflyedtech.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_profile_field_requirement extends block_base {
    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_profile_field_requirement');
    }

    /**
     * This block has no site level settings.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Each instance carries its own list of required fields.
     *
     * @return bool
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Where the block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['site' => true, 'course' => true];
    }

    /**
     * Keep the "is this block used anywhere" cache honest.
     *
     * @return bool
     */
    public function instance_create(): bool {
        requirement::purge_instance_cache();

        return true;
    }

    /**
     * Keep the "is this block used anywhere" cache honest.
     *
     * @return bool
     */
    public function instance_delete(): bool {
        requirement::purge_instance_cache();

        return true;
    }

    /**
     * The block shows nothing to learners.
     *
     * Users who can configure the block get a short note while editing, since the
     * instance is otherwise invisible and hard to find again.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (
            $this->page->user_is_editing()
                && has_capability('block/profile_field_requirement:addinstance', $this->context)
        ) {
            $key = requirement::is_configured($this) ? 'editingnote' : 'editingnoteunconfigured';
            $this->content->text = get_string($key, 'block_profile_field_requirement');
        }

        return $this->content;
    }
}
