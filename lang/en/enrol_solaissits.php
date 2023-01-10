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
 * Language file
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['component'] = 'SOL AIS-SITS';
$string['course'] = 'Course';
$string['coursedoesntexist'] = 'Course doesn\'t exist: {$a}';

$string['enrolsync'] = 'Enrolment sync task';

$string['invalidtimestartendvalues'] = 'Invalid timestart ({$a->timestart}) and timeend ({$a->timeend}) values';

$string['module'] = 'Module';

$string['pluginname'] = 'Solent AIS-SITS';
$string['pluginname_desc'] = 'An enrolment method for managing enrolments from Solent\'s AIS-SITS interface.';
$string['pluginnotinstalled'] = 'Plugin not installed';

$string['roleactions'] = 'Role enrolment actions';
$string['roleactions_desc'] = 'Depending on the role and page type, a request to unenrol a user in a course will perform the following actions.';
$string['roledoesntexist'] = 'Role doesn\'t exist: {$a}';

$string['solaissits:config'] = 'Configure enrolment on payment enrol instances';
$string['solaissits:enrol'] = 'Enrol users on course';
$string['solaissits:manage'] = 'Manage enrolled users';
$string['solaissits:unenrol'] = 'Unenrol users from course';

$string['userdoesntexist'] = 'User doesn\'t exist: {$a}';

$string['wscannotenrol'] = 'Plugin instance cannot enrol a user in the course id = {$a->courseid}';
$string['wscannotunenrol'] = 'Plugin instance cannot unenrol a user in the course {$a->courseshortname}';
$string['wsusercannotassign'] = 'You don\'t have the permission to assign this role ({$a->roleid}) to this user ({$a->userid}) in this course ({$a->courseid}).';
