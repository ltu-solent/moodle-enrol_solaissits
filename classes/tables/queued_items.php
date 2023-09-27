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
 * Queued items table
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits\tables;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/tablelib.php');

use context_course;
use enrol_solaissits\helper;
use html_writer;
use moodle_url;
use table_sql;

/**
 * Table to display queued enrolment requests from SITS
 */
class queued_items extends table_sql {
    /**
     * Constructor
     *
     * @param string $uniqid Used to store table sorting preferences
     */
    public function __construct($uniqid) {
        parent::__construct($uniqid);
        $this->useridfield = 'userid';
        // Set up columns and headings.
        $columns = [
            'id',
            'action',
            'role',
            'fullname',
            'idnumber',
            'course',
            'timestart',
            'timeend',
            'groups',
            'timemodified',
        ];
        $this->define_columns($columns);
        $headers = [
            get_string('id', 'enrol_solaissits'),
            get_string('action'),
            get_string('role'),
            get_string('fullname'),
            get_string('idnumber'),
            get_string('course'),
            get_string('timestart', 'enrol_solaissits'),
            get_string('timeend', 'enrol_solaissits'),
            get_string('groups'),
            get_string('timemodified', 'enrol_solaissits'),
        ];
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->no_sorting('groups');
        $this->no_sorting('timestart');
        $this->no_sorting('timeend');
        $this->no_sorting('role');
        $this->no_sorting('action');
        $this->define_baseurl(new moodle_url("/enrol/solaissits/queueditems.php"));
        // phpcs:disable
        // $userfieldsapi = \core_user\fields::for_identity(context_system::instance(), false)->with_userpic();
        // $userfields = $userfieldsapi->get_sql('u', false, '', $this->useridfield, false)->selects;
        // phpcs:enable
        // Manually specify userfields as the \core_user\fields API isn't available in M3.9, but switch on when running M4.
        $userfields = ' u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename ';
        $fields = 's.id, s.action, s.roleid, s.userid, s.courseid, c.shortname course, s.timestart, s.timeend, s.timemodified, ' .
            ' u.idnumber, ' . $userfields;
        $from = "{enrol_solaissits} s
        JOIN {user} u ON u.id = s.userid
        LEFT JOIN {course} c ON c.id = s.courseid";
        $where = '1=1';
        $this->set_sql($fields, $from, $where);
        $this->showdownloadbuttonsat = [TABLE_P_BOTTOM];
    }

    /**
     * Display action
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_action($row) {
        global $DB;
        // If this user is not already enrolled, or has no prior queued item, change this to add.
        $context = context_course::instance($row->courseid, IGNORE_MISSING);
        if (!$context) {
            return get_string($row->action, 'enrol_solaissits');
        }
        if ($row->action == 'unsuspend') {
            if (!is_enrolled($context, $row->userid)) {
                return get_string('add', 'enrol_solaissits');
            }
            if (!$DB->record_exists_select('enrol_solaissits', "id < :id AND userid = :userid AND courseid = :courseid",
                    ['id' => $row->id, 'userid' => $row->userid, 'courseid' => $row->courseid])) {
                return get_string('add', 'enrol_solaissits');
            }
        }
        return get_string($row->action, 'enrol_solaissits');
    }

    /**
     * Display role shortname
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_role($row) {
        global $DB;
        // Get rolename for roleid.
        $role = $DB->get_record('role', ['id' => $row->roleid]);
        if ($role) {
            return $role->shortname;
        }
        return get_string('roledoesntexist', 'enrol_solaissits', $row->roleid);
    }

    /**
     * Display course shortname
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_course($row) {
        global $DB;
        $course = $DB->get_record('course', ['id' => $row->courseid]);
        if (!$course) {
            return get_string('coursedoesntexist', 'enrol_solaissits', $row->courseid);
        }
        if ($this->is_downloading()) {
            return $course->shortname;
        }

        $html = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            $course->shortname
        );
        $extra = [];
        if (!$course->visible) {
            $extra[] = get_string('notvisible', 'enrol_solaissits');
        }

        if (!helper::istemplated($row->courseid)) {
            $extra[] = get_string('nottemplated', 'enrol_solaissits');
        }

        if ($extra) {
            $html .= '<br /><small>' . join(' - ', $extra) . '</small>';
        }
        return $html;
    }

    /**
     * Display enrolment timestart
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_timestart($row) {
        if ($row->timestart == 0) {
            return '-';
        }
        return userdate($row->timestart);
    }

    /**
     * Display enrolment timeend
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_timeend($row) {
        if ($row->timeend == 0) {
            return '-';
        }
        return userdate($row->timeend);
    }

    /**
     * Display group membership changes with this enrolment request
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_groups($row) {
        global $DB;
        $groups = $DB->get_records('enrol_solaissits_groups', ['solaissitsid' => $row->id]);
        $lines = [];
        foreach ($groups as $group) {
            $lines[] = get_string($group->action, 'enrol_solaissits') . ': ' . $group->groupname;
        }
        // Make this into a link if there are groups.
        return html_writer::alist($lines);
    }

    /**
     * Display enrolment timemodified
     *
     * @param object $row
     * @return string HTML for cell
     */
    protected function col_timemodified($row) {
        return userdate($row->timemodified);
    }
}
