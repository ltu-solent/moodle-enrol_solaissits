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
 * Helper functions
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits;

class helper {
    public static function get_course_by_idnumber($idnumber) {
        global $DB;
        return $DB->get_record('course', ['idnumber' => $idnumber]);
    }

    public static function get_role_by_shortname($shortname) {
        global $DB;
        return $DB->get_record('role', ['shortname' => $shortname]);
    }

    public static function get_user_by_idnumber($idnumber) {
        global $DB;
        $record = $DB->get_record('user', ['idnumber' => $idnumber]);
        error_log(print_r($record, true));
        return $record;
    }
}