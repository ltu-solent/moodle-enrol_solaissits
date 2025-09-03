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

$string['add'] = 'Add';

$string['component'] = 'SITS enrolments';
$string['confirmbulkdeleteenrolment'] = 'Are you sure you want to delete these user enrolments?';
$string['course'] = 'Course';
$string['coursedoesntexist'] = 'Course doesn\'t exist: {$a}';
$string['courseidnotset'] = 'Courseid not set';
$string['coursenotset'] = 'Course not set';

$string['del'] = 'Delete';
$string['deleteselectedusers'] = 'Delete selected user enrolments';

$string['editselectedusers'] = 'Edit selected user enrolments';
$string['enrolsync'] = 'SITS enrolment sync task';

$string['id'] = 'ID';
$string['invalidtimestartendvalues'] = 'Invalid timestart ({$a->timestart}) and timeend ({$a->timeend}) values';

$string['missingcourseid'] = 'Courseid not provided';
$string['missingroleid'] = 'Roleid not provided';
$string['missinguserid'] = 'Userid not provided';
$string['module'] = 'Module';

$string['noenrol'] = 'No enrolment method specified';
$string['nottemplated'] = 'Not templated';
$string['notvisible'] = 'Not visible';

$string['pluginname'] = 'SITS enrolments';
$string['pluginname_desc'] = 'An enrolment method for managing enrolments from Solent\'s AIS-SITS interface.';
$string['pluginnotinstalled'] = 'Plugin not installed';

$string['queueditems'] = 'Queued items';
$string['queueditemsheading'] = 'Queued enrolment items from SITS';

$string['roleactions'] = 'Role enrolment actions';
$string['roleactions_desc'] = 'Depending on the role and page type, a request to unenrol a user in a course will perform the following actions.';
$string['roledoesntexist'] = 'Role doesn\'t exist: {$a}';
$string['roleidnotset'] = 'Roleid not set';

$string['settings'] = 'Settings';
$string['solaissits:config'] = 'Configure enrolment on payment enrol instances';
$string['solaissits:enrol'] = 'Enrol users on course';
$string['solaissits:manage'] = 'Manage enrolled users';
$string['solaissits:unenrol'] = 'Unenrol users from course';
$string['suspend'] = 'Suspend';

$string['timeend'] = 'Enrolment ends';
$string['timemodified'] = 'Last modified';
$string['timestart'] = 'Enrolment begins';

$string['unenrolusers'] = 'Unenrol users';
$string['unsuspend'] = 'Unsuspend';
$string['userdoesntexist'] = 'User doesn\'t exist: {$a}';
$string['useridnotset'] = 'Userid not set';

$string['wscannotenrol'] = 'Plugin instance cannot enrol a user in the course id = {$a->courseid}';
$string['wscannotunenrol'] = 'Plugin instance cannot unenrol a user in the course {$a->courseshortname}';
$string['wsnoinstance'] = 'SITS enrolment plugin instance doesn\'t exist or is disabled for the course (id = {$a->courseid})';
$string['wsusercannotassign'] = 'You don\'t have the permission to assign this role ({$a->roleid}) to this user ({$a->userid}) in this course ({$a->courseid}).';
