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
 * Main enrolment class
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * SOL AIS-SITS enrolment class
 */
class enrol_solaissits_plugin extends enrol_plugin {
    /**
     * Add new instance of enrol plugin.
     * @param stdClass $course
     * @param array $fields instance fields
     * @return int|null id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        if ($DB->record_exists('enrol', array('courseid' => $course->id, 'enrol' => 'solaissits'))) {
            // Only one instance allowed.
            return null;
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Does this plugin allow enrolments?
     *
     * @param stdClass $instance course enrol instance
     *
     * @return bool - true means user with 'enrol/solaissits:enrol' may enrol others freely.
     */
    public function allow_enrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow unenrolments?
     *
     * @param stdClass $instance course enrol instance
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * @return bool - true means user with 'enrol/solaissits:unenrol' may unenrol others freely.
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }
}
