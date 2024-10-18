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
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;

/**
 * Class unenrol_users
 *
 * @package    enrol_solaissits
 * @copyright  2024 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unenrol_users extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'enrolments' => new external_multiple_structure(
                    new external_single_structure([
                        'useridnumber' => new external_value(PARAM_RAW, 'The user that is going to be unenrolled'),
                        'courseidnumber' => new external_value(PARAM_RAW, 'The course the user wil be unenrolled from'),
                        'roleshortname' => new external_value(PARAM_RAW, 'Role to remove from the user'),
                    ])
                ),
            ]
        );
    }

    /**
     * Unenrolment of users.
     *
     * @param array $enrolments an array of course user and role ids
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function execute($enrolments) {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['enrolments' => $enrolments]);
        require_once($CFG->libdir . '/enrollib.php');
        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs.
        /** @var \enrol_solaissits_plugin $enrol */
        $enrol = enrol_get_plugin('solaissits');
        if (empty($enrol)) {
            throw new moodle_exception('pluginnotinstalled', 'enrol_solaissits');
        }

        foreach ($params['enrolments'] as $enrolment) {
            $course = \enrol_solaissits\helper::get_course_by_idnumber($enrolment['courseidnumber']);
            if (!$course) {
                throw new invalid_parameter_exception(
                    get_string('coursedoesntexist', 'enrol_solaissits', $enrolment['courseidnumber'])
                );
            }
            $context = context\course::instance($course->id);
            self::validate_context($context);
            require_capability('enrol/solaissits:unenrol', $context);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'solaissits']);
            if (!$instance) {
                throw new moodle_exception('wsnoinstance', 'enrol_solaissits', $enrolment);
            }
            $user = \enrol_solaissits\helper::get_user_by_idnumber($enrolment['useridnumber']);
            if (!$user) {
                throw new invalid_parameter_exception(
                    get_string('userdoesntexist', 'enrol_solaissits', $enrolment['useridnumber'])
                );
            }
            if (!$enrol->allow_unenrol($instance)) {
                throw new moodle_exception('wscannotunenrol', 'enrol_solaissits', '', $enrolment);
            }
            // Get the role from the shortname.
            $role = \enrol_solaissits\helper::get_role_by_shortname($enrolment['roleshortname']);
            if (!$role) {
                throw new invalid_parameter_exception(
                    get_string('roledoesntexist', 'enrol_solaissits', $enrolment['roleshortname'])
                );
            }

            $data = new stdClass();
            $data->action = 'del';
            $data->userid = $user->id;
            $data->courseid = $course->id;
            $data->roleid = $role->id;
            $data->timestart = 0;
            $data->timeend = 0;
            $data->groups = [];
            $enrol->external_unenrol_user($data);
        }
        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}
