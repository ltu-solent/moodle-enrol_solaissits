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
use core_external\external_format_value;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use Exception;
use stdClass;

/**
 * Class get_enrolments
 *
 * @package    enrol_solaissits
 * @copyright  2024 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_enrolments extends external_api {
    /**
     * The parameters required to get enrolment infor on a course for a user.
     * Only returns enrolments relevant to this enrolment method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
                'useridnumber' => new external_value(PARAM_ALPHANUMEXT, 'User idnumber'),
            ]
        );
    }

    /**
     * Get enrolments function
     *
     * @param string $courseidnumber
     * @param string $useridnumber
     * @return stdClass Object containing user, course, enrolment and queued items
     */
    public static function execute($courseidnumber, $useridnumber) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseidnumber' => $courseidnumber,
                'useridnumber' => $useridnumber,
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
        $user = $DB->get_record('user', ['idnumber' => $useridnumber], '*', MUST_EXIST);
        /** @var \enrol_solaissits_plugin $enrol */
        $enrol = enrol_get_plugin('solaissits');
        if (empty($enrol)) {
            throw new moodle_exception('pluginnotinstalled', 'enrol_solaissits');
        }
        $enrolments = $enrol->get_enrolments_for($user->id, $course->id);
        return $enrolments;
    }

    /**
     * Get enrolments return structure
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'user' => new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'ID of the user'),
                    'username' => new external_value(PARAM_RAW,
                        'Username policy is defined in Moodle security config', VALUE_OPTIONAL),
                    'firstname' => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
                    'lastname' => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
                    'fullname' => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
                    'email' => new external_value(PARAM_TEXT,
                        'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                    'address' => new external_value(PARAM_TEXT, 'Postal address', VALUE_OPTIONAL),
                    'phone1' => new external_value(PARAM_NOTAGS, 'Phone 1', VALUE_OPTIONAL),
                    'phone2' => new external_value(PARAM_NOTAGS, 'Phone 2', VALUE_OPTIONAL),
                    'department' => new external_value(PARAM_TEXT, 'department', VALUE_OPTIONAL),
                    'institution' => new external_value(PARAM_TEXT, 'institution', VALUE_OPTIONAL),
                    'idnumber' => new external_value(PARAM_RAW,
                        'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
                    'interests' => new external_value(PARAM_TEXT, 'user interests (separated by commas)', VALUE_OPTIONAL),
                    'firstaccess' => new external_value(PARAM_INT, 'first access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastaccess' => new external_value(PARAM_INT, 'last access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastcourseaccess' => new external_value(PARAM_INT,
                        'last access to the course (0 if never)', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'User profile description', VALUE_OPTIONAL),
                    'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                    'city' => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
                    'country' => new external_value(PARAM_ALPHA,
                        'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
                    'profileimageurlsmall' => new external_value(PARAM_URL,
                        'User image profile URL - small version', VALUE_OPTIONAL),
                    'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version', VALUE_OPTIONAL),
                    'customfields' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'type' => new external_value(PARAM_ALPHANUMEXT,
                                    'The type of the custom field - text field, checkbox...'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                                'name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                                'shortname' => new external_value(PARAM_RAW,
                                    'The shortname of the custom field - to be able to build the field class in the code'),
                            ]
                        ), 'User custom fields (also known as user profile fields)', VALUE_OPTIONAL),
                    'groups' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'group id'),
                                'name' => new external_value(PARAM_RAW, 'group name'),
                                'description' => new external_value(PARAM_RAW, 'group description'),
                                'descriptionformat' => new external_format_value('description'),
                            ]
                        ), 'user groups', VALUE_OPTIONAL),
                    'roles' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'roleid' => new external_value(PARAM_INT, 'role id'),
                                'name' => new external_value(PARAM_RAW, 'role name'),
                                'shortname' => new external_value(PARAM_ALPHANUMEXT, 'role shortname'),
                                'sortorder' => new external_value(PARAM_INT, 'role sortorder'),
                            ]
                        ), 'user roles', VALUE_OPTIONAL),
                    'preferences' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'name' => new external_value(PARAM_RAW, 'The name of the preferences'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                            ]
                    ), 'User preferences', VALUE_OPTIONAL),
                    'enrolledcourses' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'Id of the course'),
                                'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                                'shortname' => new external_value(PARAM_RAW, 'Shortname of the course'),
                            ]
                    ), 'Courses where the user is enrolled - limited by which courses the user is able to see', VALUE_OPTIONAL),
                ]
            ),
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
                    'roles' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Role assignment id'),
                            'roleid' => new external_value(PARAM_INT, 'Role id'),
                            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Role shortname'),
                        ])
                    ),
                ])
            ),
            'queueditems' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of queued item'),
                    'action' => new external_value(PARAM_ALPHANUMEXT, 'Enrolment action - add, remove, suspend'),
                    'roleid' => new external_value(PARAM_INT, 'Role id'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
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
