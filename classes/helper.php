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

use core_course\customfield\course_handler;
use stdClass;

/**
 * Helper class
 */
class helper {

    /**
     * Get course record by course idnumber
     *
     * @param string $idnumber
     * @return \stdClass|null
     */
    public static function get_course_by_idnumber(string $idnumber) {
        global $DB;
        if (empty($idnumber)) {
            return null; // Don't allow empty strings.
        }
        return $DB->get_record('course', ['idnumber' => $idnumber]);
    }

    /**
     * Get role by shortname
     *
     * @param string $shortname
     * @return \stdClass|null
     */
    public static function get_role_by_shortname(string $shortname) {
        global $DB;
        if (empty($shortname)) {
            return null; // Don't allow empty strings.
        }
        return $DB->get_record('role', ['shortname' => $shortname]);
    }

    /**
     * Get user by idnumber
     *
     * @param string $idnumber
     * @return \stdClass|null
     */
    public static function get_user_by_idnumber(string $idnumber) {
        global $DB;
        if (empty($idnumber)) {
            return null; // Don't allow empty strings.
        }
        return $DB->get_record('user', ['idnumber' => $idnumber]);
    }

    /**
     * Get roles that can be applied at course context level.
     *
     * @return array List of roles
     */
    public static function get_roles() {
        global $DB;
        // Only interested in roles that can be assigned at course context level.
        $sql = "SELECT r.* FROM {role} r
        JOIN {role_context_levels} rcl ON rcl.roleid = r.id AND rcl.contextlevel = :coursecontextlevel";
        $params = [
            'coursecontextlevel' => CONTEXT_COURSE,
        ];
        $courseroles = $DB->get_records_sql($sql, $params);
        $roles = role_fix_names($courseroles);
        return $roles;
    }

    /**
     * Check to see if a template has been applied to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public static function istemplated($courseid): bool {
        $value = static::get_customfield($courseid, 'templateapplied');
        if ($value == null) {
            return false;
        }
        return (bool)$value;
    }

    /**
     * Get pagetype for given course
     *
     * @param int $courseid
     * @return string
     */
    public static function get_pagetype($courseid): string {
        return static::get_customfield($courseid, 'pagetype');
    }

    /**
     * Get any customfield value for given course and field name
     *
     * @param int $courseid
     * @param string $shortname
     * @return mixed
     */
    public static function get_customfield($courseid, $shortname) {
        $handler = course_handler::create();
        $datas = $handler->get_instance_data($courseid, true);
        foreach ($datas as $data) {
            $fieldname = $data->get_field()->get('shortname');
            if ($fieldname != $shortname) {
                continue;
            }
            $value = $data->get_value();
            if (empty($value)) {
                continue;
            }
            return $value;
        }
        return null;
    }

    /**
     * Gets (and creates if necessary) the enrol instance for solaissits
     *
     * @param stdClass $course
     * @return stdClass
     */
    public static function get_enrol_instance($course) {
        $enrol = enrol_get_plugin('solaissits');
        $instance = null;
        $enrolinstances = enrol_get_instances($course->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "solaissits") {
                $instance = $courseenrolinstance;
                break;
            }
        }
        if (empty($instance)) {
            // Create an instance if it doesn't exist, even though it might be deleted later.
            $instanceid = $enrol->add_instance($course);
            $instances = enrol_get_instances($course->id, true);
            $instance = $instances[$instanceid];
        }
        return $instance;
    }
}
