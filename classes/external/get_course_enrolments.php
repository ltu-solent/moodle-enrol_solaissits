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

namespace enrol_solaissits\external;


use core\context;
use core\exception\moodle_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use Exception;
use stdClass;

/**
 * Class get_course_enrolments
 *
 * @package    enrol_solaissits
 * @copyright  2024 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_enrolments extends external_api {
    /**
     * Get course enrolment parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            ]
        );

    }

    /**
     * Get course enrolments
     *
     * @param string $courseidnumber
     * @return object
     */
    public static function execute($courseidnumber) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseidnumber' => $courseidnumber,
            ]
        );
        $course = $DB->get_record('course', ['idnumber' => $courseidnumber], '*', MUST_EXIST);
        $coursecontext = context\course::instance($course->id, IGNORE_MISSING);
        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseidnumber = $params['courseidnumber'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        course_require_view_participants($coursecontext);
        /** @var \enrol_solaissits_plugin $enrol */
        $enrol = enrol_get_plugin('solaissits');
        if (empty($enrol)) {
            throw new moodle_exception('pluginnotinstalled', 'enrol_solaissits');
        }

        $return = new stdClass();
        $return->course = (object)[
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'idnumber' => $course->idnumber,
        ];
        $return->enrolments = $enrol->get_course_enrolments($course->id);
        $return->queueditems = $enrol->get_queued_items_for(0, $course->id);
        return $return;

    }

    /**
     * Return structure of get_course_enrolments
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of the course'),
                'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                'shortname' => new external_value(PARAM_RAW, 'Shortname of the course'),
            ]),
            'enrolments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the user enrolment'),
                    'status' => new external_value(PARAM_INT, 'Enrolment status. 0=active, 1=suspended'),
                    'enrolid' => new external_value(PARAM_INT, 'Id of the enrolment instance'),
                    'timestart' => new external_value(PARAM_INT, 'Unix timestamp for when the enrolment begins'),
                    'timeend' => new external_value(PARAM_INT, 'Unix timestamp for when the enrolment begins'),
                    'userid' => new external_value(PARAM_INT, 'Userid'),
                    'useridnumber' => new external_value(PARAM_RAW,
                        'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
                    'roles' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Role assignment id'),
                            'roleid' => new external_value(PARAM_INT, 'Role id'),
                            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Role shortname'),
                        ])
                    ),
                    'groups' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Id of group'),
                            'name' => new external_value(PARAM_RAW, 'Name of group assigned to this user'),
                        ])
                    ),
                ])
            ),
            'queueditems' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of queued item'),
                    'action' => new external_value(PARAM_ALPHANUMEXT, 'Enrolment action - add, remove, suspend'),
                    'roleid' => new external_value(PARAM_INT, 'Role id'),
                    'roleshortname' => new external_value(PARAM_ALPHANUMEXT, 'Role shortname'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'useridnumber' => new external_value(PARAM_RAW,
                        'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'timestart' => new external_value(PARAM_INT, 'Unix timestamp for when the enrolment begins'),
                    'timeend' => new external_value(PARAM_INT, 'Unix timestamp for when the enrolment ends'),
                    'groups' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Id of queued group item'),
                            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action - add, del'),
                            'name' => new external_value(PARAM_RAW, 'Name of group to be assigned to this user.'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
