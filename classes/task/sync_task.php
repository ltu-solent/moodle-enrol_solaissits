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
 * Enrolment sync tasks
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits\task;

use core\task\scheduled_task;

/**
 * Enrolment sync task
 */
class sync_task extends scheduled_task {

    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('enrolsync', 'enrol_solaissits');
    }

    /**
     * Execute task
     *
     * @return void
     */
    public function execute() {
        if (!enrol_is_enabled('solaissits')) {
            return;
        }

        // Instance of enrol_flatfile_plugin.
        $plugin = enrol_get_plugin('solaissits');
        $result = $plugin->sync(new \null_progress_trace());
        return $result;
    }
}
