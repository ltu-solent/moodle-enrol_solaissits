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
     * @var int
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
     * @param mixed $record
     * @return stdClass
     */
    public function create_queued_item($record) {
        global $DB;
        $this->qicount++;
        $record = (object)(array)$record;
        if (!isset($record->roleid)) {
            throw new moodle_exception('roleidnotset', 'enrol_solaissits');
        }
        $DB->record_exists('role', ['id' => $record->roleid], MUST_EXIST);
        if (!isset($record->courseid)) {
            throw new moodle_exception('courseidnotset', 'enrol_solaissits');
        }
        $DB->record_exists('course', ['id' => $record->courseid], MUST_EXIST);
        if (!isset($record->userid)) {
            throw new moodle_exception('useridnotset', 'enrol_solaissits');
        }
        $DB->record_exists('user', ['id' => $record->userid], MUST_EXIST);
        $record->action = $record->action ?? 'add';
        $record->timestart = $record->timestart ?? 0;
        $record->timeend = $record->timeend ?? 0;
        $record->timemodified = $record->timemodified ?? time();
        $groups = $record->groups ?? [];
        unset($record->groups);
        $insertid = $DB->insert_record('enrol_solaissits', $record);
        $record->id = $insertid;
        foreach ($groups as $key => $group) {
            $group = (object)(array)$group;
            $group->solaissitsid = $insertid;
            $group->action = $group->action ?? 'add';
            $group->groupname = $group->groupname ?? 'group' . $this->qicount;
            $ginsertid = $DB->insert_record('enrol_solaissits_groups', $group);
            $group->id = $ginsertid;
            $groups[$key] = $group;
        }
        $record->groups = $groups;
        return $record;
    }

    /**
     * Create enrolment method entity
     *
     * @param array|stdClass $record
     * @return void
     */
    public function create_enrolment_method($record) {
        global $DB;
        $record = (object)(array)$record;
        if (!isset($record->course)) {
            throw new moodle_exception('coursenotset', 'enrol_solaissits');
        }
        $course = $DB->get_record('course', ['shortname' => $record->course]);
        /** @var \enrol_plugin $enrol */
        $enrol = enrol_get_plugin($record->method);
        if (!$enrol) {
            throw new moodle_exception('noenrol', 'enrol_solaissits');
        }

        $enabled = enrol_get_plugins(true);
        $enabled[$record->method] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
        $instance = null;
        $enrolinstances = enrol_get_instances($course->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == $record->method) {
                $instance = $courseenrolinstance;
                break;
            }
        }
        if (empty($instance)) {
            // Create an instance if it doesn't exist.
            $instanceid = $enrol->add_instance($course);
            $instances = enrol_get_instances($course->id, true);
            $instance = $instances[$instanceid];
        }
    }
}
