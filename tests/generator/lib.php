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
 * Record generator for solaissits
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generator class to generate records to solaissits
 */
class enrol_solaissits_generator extends component_generator_base {
    /**
     * Count of queued items
     *
     * @var integer
     */
    public $qicount = 0;

    /**
     * Reset counters
     *
     * @return void
     */
    public function reset() {
        $this->qicount = 0;
    }

    /**
     * Create a queued item record. Requires roleid, courseid, and userid as a minimum.
     *
     * @param stdClass $record
     * @return stdClass
     */
    public function create_queued_item($record) {
        global $DB;
        $this->qicount++;
        $record = (object)(array)$record;
        if (!isset($record->roleid)) {
            throw new moodle_exception('roleidnotset', 'enrol_solaissits');
        }
        $role = $DB->record_exists('role', ['id' => $record->roleid], MUST_EXIST);
        if (!isset($record->courseid)) {
            throw new moodle_exception('courseidnotset', 'enrol_solaissits');
        }
        $course = $DB->record_exists('course', ['id' => $record->courseid], MUST_EXIST);
        if (!isset($record->userid)) {
            throw new moodle_exception('useridnotset', 'enrol_solaissits');
        }
        $user = $DB->record_exists('user', ['id' => $record->userid], MUST_EXIST);
        $record->action ?? 'add';
        $record->timestart ?? 0;
        $record->timeend ?? 0;
        $record->timemodified ?? time();
        $groups = $record->groups ?? [];
        unset($record->groups);
        $insertid = $DB->insert_record('enrol_solaissits', $record);
        $record->id = $insertid;
        foreach ($groups as $key => $group) {
            $group = (object)(array)$group;
            $group->solaissitsid = $insertid;
            $group->action ?? 'add';
            $group->groupname ?? 'group' . $this->qicount;
            $ginsertid = $DB->insert_record('enrol_solaissits_groups', $group);
            $group->id = $ginsertid;
            $groups[$key] = $group;
        }
        $record->groups = $groups;
        return $record;
    }
}
