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
 * Externallib
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/externallib.php');

/**
 * Externallib class for SOL AIS-SITS
 */
class enrol_solaissits_external extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function enrol_users_parameters() {
        return new external_function_parameters(
            array(
                'enrolments' => new external_multiple_structure(
                    new external_single_structure([
                        'roleshortname' => new external_value(PARAM_RAW, 'Role to assign to the user'),
                        'useridnumber' => new external_value(PARAM_RAW, 'The user that is going to be enrolled'),
                        'courseidnumber' => new external_value(PARAM_RAW, 'The course to enrol the user role in'),
                        'groups' => new external_multiple_structure(
                            new external_single_structure([
                                'name' => new external_value(PARAM_ALPHANUMEXT,
                                    'Group name. Group is created if it doesn\'t exist'),
                                'action' => new external_value(PARAM_ALPHA, 'add or del', VALUE_OPTIONAL, 'add')
                            ]), 'Manage this user\'s group membership', VALUE_OPTIONAL
                        ),
                        'timestart' => new external_value(PARAM_INT, 'Timestamp when the enrolment start', VALUE_OPTIONAL),
                        'timeend' => new external_value(PARAM_INT, 'Timestamp when the enrolment end', VALUE_OPTIONAL),
                        'suspend' => new external_value(
                            PARAM_INT, 'set to 1 to suspend & 0 to unsuspend the enrolment', VALUE_OPTIONAL)
                    ])
                )
            )
        );
    }

    /**
     * Enrolment of users.
     *
     * Function throws an exception at the first error encountered.
     * @param array $enrolments  An array of user enrolment
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function enrol_users($enrolments) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_users_parameters(),
                array('enrolments' => $enrolments));

        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs
                                                           // (except if the DB doesn't support it).

        // Retrieve the enrolment plugin.
        $enrol = enrol_get_plugin('solaissits');
        if (empty($enrol)) {
            throw new moodle_exception('pluginnotinstalled', 'enrol_solaissits');
        }

        foreach ($params['enrolments'] as $enrolment) {
            // Ensure the current user is allowed to run this function in the enrolment context.
            $course = enrol_solaissits\helper::get_course_by_idnumber($enrolment['courseidnumber']);
            if (!$course) {
                throw new invalid_parameter_exception(
                    get_string('coursedoesntexist', 'enrol_solaissits', $enrolment['courseidnumber'])
                );
            }
            $context = context_course::instance($course->id, IGNORE_MISSING);
            self::validate_context($context);

            // Check that the user has the permission to enrol with this method.
            require_capability('enrol/solaissits:enrol', $context);
            // Get the role from the shortname.
            $role = enrol_solaissits\helper::get_role_by_shortname($enrolment['roleshortname']);
            if (!$role) {
                throw new invalid_parameter_exception(
                    get_string('roledoesntexist', 'enrol_solaissits', $enrolment['roleshortname'])
                );
            }
            // Throw an exception if user is not able to assign the role.
            $roles = get_assignable_roles($context);
            if (!array_key_exists($role->id, $roles)) {
                $errorparams = new stdClass();
                $errorparams->roleid = $enrolment['roleshortname'];
                $errorparams->courseid = $enrolment['courseidnumber'];
                $errorparams->userid = $enrolment['useridnumber'];
                throw new moodle_exception('wsusercannotassign', 'enrol_solaissits', '', $errorparams);
            }

            $user = enrol_solaissits\helper::get_user_by_idnumber($enrolment['useridnumber']);
            if (!$user) {
                throw new invalid_parameter_exception(
                    get_string('userdoesntexist', 'enrol_solaissits', $enrolment['useridnumber'])
                );
            }

            // Validate timestart and timeend.
            $timestart = $enrolment['timestart'] ?? 0;
            $timeend = $enrolment['timeend'] ?? 0;
            if ($timeend > $timestart && $timeend != 0) {
                throw new invalid_parameter_exception(
                    get_string('invalidtimestartendvalues', 'enrol_solaissits', [
                        'timestart' => $timestart,
                        'timeend' => $timeend])
                );
            }

            // Check enrolment plugin instance is enabled/exists.
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
            // Check that the plugin allows this enrolment from this user.
            if (!$enrol->allow_enrol($instance)) {
                $errorparams = new stdClass();
                $errorparams->roleid = $enrolment['roleshortname'];
                $errorparams->courseid = $enrolment['courseidnumber'];
                $errorparams->userid = $enrolment['useridnumber'];
                throw new moodle_exception('wscannotenrol', 'enrol_solaissits', '', $errorparams);
            }

            $action = 'add';
            $suspend = isset($enrolment['suspend']) ? $enrolment['suspend'] : null;
            if ($suspend === 1) {
                $action = 'suspend';
            }
            if ($suspend === 0) {
                $action = 'unsuspend';
            }
            $data = new stdClass();
            $data->action = $action;
            $data->userid = $user->id;
            $data->courseid = $course->id;
            $data->roleid = $role->id;
            $data->timestart = $timestart;
            $data->timeend = $timeend;
            $data->groups = $enrolment['groups'] ?? [];
            $enrol->external_enrol_user($data);
        }

        // Because I'm creating enrolment instances etc, perhaps I shouldn't have transactions.
        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function enrol_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_users_parameters() {
        return new external_function_parameters(
            array(
                'enrolments' => new external_multiple_structure(
                    new external_single_structure([
                        'useridnumber' => new external_value(PARAM_RAW, 'The user that is going to be unenrolled'),
                        'courseidnumber' => new external_value(PARAM_RAW, 'The course the user wil be unenrolled from'),
                        'roleshortname' => new external_value(PARAM_RAW, 'Role to remove from the user'),
                    ])
                )
            )
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
    public static function unenrol_users($enrolments) {
        global $CFG, $DB;
        $params = self::validate_parameters(self::unenrol_users_parameters(), array('enrolments' => $enrolments));
        require_once($CFG->libdir . '/enrollib.php');
        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs.
        $enrol = enrol_get_plugin('solaissits');
        if (empty($enrol)) {
            throw new moodle_exception('pluginnotinstalled', 'enrol_solaissits');
        }

        foreach ($params['enrolments'] as $enrolment) {
            $course = enrol_solaissits\helper::get_course_by_idnumber($enrolment['courseidnumber']);
            if (!$course) {
                throw new invalid_parameter_exception(
                    get_string('coursedoesntexist', 'enrol_solaissits', $enrolment['courseidnumber'])
                );
            }
            $context = context_course::instance($course->id);
            self::validate_context($context);
            require_capability('enrol/solaissits:unenrol', $context);
            $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'solaissits'));
            if (!$instance) {
                throw new moodle_exception('wsnoinstance', 'enrol_solaissits', $enrolment);
            }
            $user = enrol_solaissits\helper::get_user_by_idnumber($enrolment['useridnumber']);
            if (!$user) {
                throw new invalid_parameter_exception(
                    get_string('userdoesntexist', 'enrol_solaissits', $enrolment['useridnumber'])
                );
            }
            if (!$enrol->allow_unenrol($instance)) {
                throw new moodle_exception('wscannotunenrol', 'enrol_solaissits', '', $enrolment);
            }
            // Get the role from the shortname.
            $role = enrol_solaissits\helper::get_role_by_shortname($enrolment['roleshortname']);
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
    public static function unenrol_users_returns() {
        return null;
    }
}
