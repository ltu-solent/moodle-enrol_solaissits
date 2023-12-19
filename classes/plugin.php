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
 * Main enrolment class
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');

/**
 * SOL AIS-SITS enrolment class
 */
class enrol_solaissits_plugin extends enrol_plugin {
    /**
     * Add new instance of enrol plugin.
     * @param stdClass $course
     * @param array $fields instance fields
     * @return int|null id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        if ($DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'solaissits'])) {
            // Only one instance allowed.
            return null;
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Does this plugin allow enrolments?
     *
     * @param stdClass $instance course enrol instance
     *
     * @return bool - true means user with 'enrol/solaissits:enrol' may enrol others freely.
     */
    public function allow_enrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow unenrolments?
     *
     * @param stdClass $instance course enrol instance
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * @return bool - true means user with 'enrol/solaissits:unenrol' may unenrol others freely.
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user,
     * false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * All plugins allowing this must implement 'enrol/xxx:manage' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means it is possible to change enrol period and status in user_enrolments table
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin assign protected roles are can they be manually removed?
     * @return bool - false means anybody may tweak roles, it does not use itemid and component when assigning roles
     */
    public function roles_protected() {
        // Maybe add capability check here.
        return true;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return false;
    }

    /**
     * External function tries to enrol user. If course not ready, this will queue the request.
     *
     * @param object $data
     * @return bool
     */
    public function external_enrol_user($data) {
        global $DB;
        // Validate data and set sensible defaults if missing values.
        if (!isset($data->courseid)) {
            throw new moodle_exception('missingcourseid', 'enrol_solaissits');
        }
        if (!isset($data->roleid)) {
            throw new moodle_exception('missingroleid', 'enrol_solaissits');
        }
        if (!isset($data->userid)) {
            throw new moodle_exception('missinguserid', 'enrol_solaissits');
        }
        $data->action = $data->action ?? 'add';
        $data->timestart = $data->timestart ?? 0;
        $data->timeend = $data->timeend ?? 0;
        $data->groups = $data->groups ?? [];

        $course = $DB->get_record('course', ['id' => $data->courseid]);
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
            // Create an instance if it doesn't exist.
            $instanceid = $this->add_instance($course);
            $instances = enrol_get_instances($course->id, true);
            $instance = $instances[$instanceid];
        }

        // We only actually enrol when a course has had its template applied.
        // Otherwise the enrolment would be deleted when the template is applied.
        $iscourseready = \enrol_solaissits\helper::istemplated($course->id);
        // Are there already queued actions for this enrolment. We don't want these to be applied out of turn.
        // If there are, we enqueue to retain the ordering of actions.
        $hasqueueditems = $this->get_queued_items_for($data->userid, $data->courseid);
        if (!$iscourseready || count($hasqueueditems) > 0) {
            $this->enqueue_enrolment($data);
            return true;
        }

        // Finally proceed with the enrolment.
        $status = ENROL_USER_ACTIVE;
        if ($data->action == 'suspend') {
            $status = ENROL_USER_SUSPENDED;
        }
        // If the user exists and the timestart, timeend or status is different, this automatically changes to an update.
        $this->enrol_user($instance, $data->userid, $data->roleid,
                $data->timestart, $data->timeend, $status);
        $this->process_groups($data->userid, $data->courseid, $data->groups);

        return true;
    }

    /**
     * External function to unenrol a user. If there already items queued this will be queued.
     * Also depends on custom course fields.
     *
     * @param object $data
     * @return void
     */
    public function external_unenrol_user($data) {
        global $DB;
        if (!isset($data->courseid)) {
            throw new moodle_exception('missingcourseid', 'enrol_solaissits');
        }
        if (!isset($data->roleid)) {
            throw new moodle_exception('missingroleid', 'enrol_solaissits');
        }
        if (!isset($data->userid)) {
            throw new moodle_exception('missinguserid', 'enrol_solaissits');
        }
        $course = $DB->get_record('course', ['id' => $data->courseid]);
        // Check enrolment plugin instance is enabled/exists.
        $instance = null;
        $enrolinstances = enrol_get_instances($data->courseid, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "solaissits") {
                $instance = $courseenrolinstance;
                break;
            }
        }
        if (empty($instance)) {
            // Create an instance if it doesn't exist.
            $instanceid = $this->add_instance($course);
            $instances = enrol_get_instances($course->id, true);
            $instance = $instances[$instanceid];
        }

        // We only actually enrol when a course has had its template applied.
        // Otherwise the enrolment would be deleted when the template is applied.
        $iscourseready = \enrol_solaissits\helper::istemplated($data->courseid);
        // Are there already queued actions for this enrolment. We don't want these to be applied out of turn.
        // If there are, we enqueue to retain the ordering of actions.
        $hasqueueditems = $this->get_queued_items_for($data->userid, $data->courseid);
        if (!$iscourseready || count($hasqueueditems) > 0) {
            $this->enqueue_enrolment($data);
            return true;
        }
        $this->process_unenrol($data);
    }

    /**
     * Do the unenrolment process and check for course readiness.
     *
     * @param object $data
     * @param progress_trace $trace If running as cron, output to trace, otherwise output nothing.
     * @return void
     */
    private function process_unenrol($data, $trace = null) {
        global $DB;
        if (!isset($data->courseid)) {
            throw new moodle_exception('missingcourseid', 'enrol_solaissits');
        }
        if (!isset($data->roleid)) {
            throw new moodle_exception('missingroleid', 'enrol_solaissits');
        }
        if (!isset($data->userid)) {
            throw new moodle_exception('missinguserid', 'enrol_solaissits');
        }
        $coursecontext = context_course::instance($data->courseid);
        $default = json_encode([
            'course' => ENROL_EXT_REMOVED_UNENROL,
            'module' => ENROL_EXT_REMOVED_UNENROL,
        ]);
        // Unenrol doesn't have any role associated with the action. TODO: What to do?
        // You don't need to know the role to do an unenrol, however, we want to do different things
        // with different roles. Before this was done by restricting roles to certain enrolment plugins,
        // but now we are using a unified method.
        // We could ask up-front, though usually this info is thrown away and optional (in manual enrolments, but not flatfile).
        // It might be the safest route forward as it saves inferring anything.
        // Alternatively, get all roles for this individual for this enrolment method (there should only be one?)
        // Perhaps there should be a back-up.
        // Do we want all enrolments to be deleted?
        $configuredaction = json_decode($this->get_config('roleactions_' . $data->roleid, $default));
        $pagetype = \enrol_solaissits\helper::get_customfield($data->courseid, 'pagetype');
        // Page type should not be empty. But if it is, the user will simply be unenrolled.
        $action = $configuredaction->{$pagetype} ?? ENROL_EXT_REMOVED_UNENROL;
        if ($action == ENROL_EXT_REMOVED_KEEP) {
            return;
        }
        // Loops through all enrolment methods, try to unenrol if roleid matches.
        $instances = $DB->get_records('enrol', ['courseid' => $data->courseid]);
        $unenrolled = false;
        // Not convinced we need to roll through all the instances.
        foreach ($instances as $instance) {
            if (!$ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $data->userid])) {
                continue;
            }
            if ($instance->enrol === 'solaissits') {
                $plugin = $this;
            } else {
                if (!enrol_is_enabled($instance->enrol)) {
                    continue;
                }
                if (!$plugin = enrol_get_plugin($instance->enrol)) {
                    continue;
                }
                if (!$plugin->allow_unenrol_user($instance, $ue)) {
                    continue;
                }
            }
            // Does this user have any other roles?
            $componentroles = [];
            $manualroles = [];
            $ras = $DB->get_records('role_assignments', ['userid' => $data->userid, 'contextid' => $coursecontext->id]);
            foreach ($ras as $ra) {
                if ($ra->component === '') {
                    $manualroles[$ra->roleid] = $ra->roleid;
                } else if ($ra->component === 'enrol_' . $instance->enrol && $ra->itemid == $instance->id) {
                    $componentroles[$ra->roleid] = $ra->roleid;
                }
            }
            if ($componentroles && !isset($componentroles[$data->roleid])) {
                // Do not unenrol using this method, user has some other protected role!
                continue;

            } else if (empty($ras)) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                // If user does not have any roles then let's just suspend as many methods as possible.

            } else if (!$plugin->roles_protected()) {
                if (!$componentroles && $manualroles && !isset($manualroles[$data->roleid])) {
                    // Most likely we want to keep users enrolled because they have some other course roles.
                    continue;
                }
            }
            if ($action == ENROL_EXT_REMOVED_UNENROL) {
                $unenrolled = true;
                // Unenrol should remove all roles.
                $plugin->unenrol_user($instance, $data->userid);
                if ($trace) {
                    $trace->output("User $data->userid was unenrolled from course $data->courseid (enrol_$instance->enrol)", 1);
                }

            } else if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                if ($plugin->allow_manage($instance)) {
                    $unenrolled = true;
                    if ($ue->status == ENROL_USER_ACTIVE) {
                        $plugin->update_user_enrol($instance, $data->userid, ENROL_USER_SUSPENDED);
                    }
                    // Check if roles are protected?
                    role_unassign_all([
                        'contextid' => $coursecontext->id,
                        'userid' => $data->userid,
                        'component' => 'enrol_' . $instance->enrol,
                        'itemid' => $instance->id,
                    ], true);
                    if ($trace) {
                        $trace->output("User $data->userid enrolment was suspended in
                            course $data->courseid (enrol_$instance->enrol)", 1);
                    }
                }
            } else if ($action == ENROL_EXT_REMOVED_SUSPEND) {
                if ($plugin->allow_manage($instance)) {
                    if ($ue->status == ENROL_USER_ACTIVE) {
                        $plugin->update_user_enrol($instance, $data->userid, ENROL_USER_SUSPENDED);
                    }
                }
            }
        }
        if (!$unenrolled) {
            if (0 == $DB->count_records('role_assignments', ['userid' => $data->userid, 'contextid' => $coursecontext->id])) {
                role_unassign_all([
                    'contextid' => $coursecontext->id,
                    'userid' => $data->userid,
                    'component' => '',
                    'itemid' => 0,
                ], true);
            }
            if ($trace) {
                $trace->output("User $data->userid (with role $data->roleid) not unenrolled from course $data->courseid", 1);
            }
        }
        return;
    }

    /**
     * If we can't process an enrolment or unenrolment straight away, queue it for later.
     *
     * @param stdClass $data
     * @return void
     */
    private function enqueue_enrolment($data) {
        global $DB;
        $groups = $data->groups;
        unset($data->groups);
        // Do I check to see if there's already a record for this user, course and role?
        // Or do we just add to the queue and deal with changes in turn?
        // We'll get an "add" if there's a change to the group membership.
        $data->timemodified = time();
        $insertid = $DB->insert_record('enrol_solaissits', $data);
        foreach ($groups as $group) {
            $record = new stdClass();
            $record->solaissitsid = $insertid;
            $record->action = $group['action'];
            $record->groupname = $group['name'];
            $DB->insert_record('enrol_solaissits_groups', $record);
        }
        $data->groups = $groups;
    }

    /**
     * Once an item from the queue has been processed, remove it.
     *
     * @param int $itemid
     * @return void
     */
    private function dequeue_enrolment($itemid) {
        global $DB;
        $DB->delete_records('enrol_solaissits_groups', ['solaissitsid' => $itemid]);
        $DB->delete_records('enrol_solaissits', ['id' => $itemid]);
    }

    /**
     * Update any group data associated with this enrolment
     *
     * @param int $userid
     * @param int $courseid
     * @param array $usergroups [Group name, Action]
     * @return void
     */
    private function process_groups($userid, $courseid, $usergroups) {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid]);
        // Add user to groups, if set, or move if group membership has changed.
        // This requires that we are told of membership changes, not just additions.
        // Unenrolements will automatically deal with group membership.
        $coursegroups = groups_get_course_data($courseid);
        foreach ($usergroups as $usergroup) {
            $existinggroups = array_filter($coursegroups->groups, function($coursegroup) use ($usergroup) {
                return ($usergroup['name'] == $coursegroup->name);
            });
            // Does the group exist?
            $group = null;
            if (count($existinggroups) == 0) {
                // Create the group.
                $group = new stdClass();
                $group->name = $usergroup['name'];
                $group->courseid = $courseid;
                $groupid = groups_create_group($group);
                if ($groupid) {
                    $group->id = $groupid;
                }
            } else if (count($existinggroups) === 1) {
                $group = reset($existinggroups);
            } else {
                // This shouldn't happen. Is it possible to have two groups with the same name?
                // Not through the UI, but possible programatically. Get the first one anyway.
                $group = reset($existinggroups);
            }
            $action = $usergroup['action'] ?? 'add'; // Default action.
            // Do the appropriate group membership action.
            $ismember = groups_is_member($group->id, $user->id);
            if ($action == 'add' && !$ismember) {
                groups_add_member($group->id, $user);
            }
            if ($action == 'del' && $ismember) {
                groups_remove_member($group->id, $user);
            }
        }
    }

    /**
     * Get any queued enrolments for given user or course or both
     *
     * @param int $userid
     * @param int $courseid
     * @return array List of enrolment records
     */
    public function get_queued_items_for($userid = 0, $courseid = 0) {
        global $DB;
        if ($userid == 0 && $courseid == 0) {
            return [];
        }
        $sql = "SELECT e.*, r.shortname roleshortname, u.idnumber useridnumber
            FROM {enrol_solaissits} e
            JOIN {user} u ON u.id = e.userid
            JOIN {role} r ON r.id = e.roleid
            WHERE ";
        $params = [];
        $where = [];
        if ($userid > 0) {
            $params['userid'] = $userid;
            $where[] = 'e.userid = :userid';
        }
        if ($courseid > 0) {
            $params['courseid'] = $courseid;
            $where[] = 'e.courseid = :courseid';
        }
        $sql .= join(' AND ', $where);
        $items = $DB->get_records_sql($sql, $params);
        foreach ($items as $key => $item) {
            $items[$key]->groups = [];
            $groupactions = $DB->get_records('enrol_solaissits_groups', ['solaissitsid' => $item->id]);
            foreach ($groupactions as $groupaction) {
                $items[$key]->groups[] = [
                    'id' => $groupaction->id,
                    'name' => $groupaction->groupname,
                    'action' => $groupaction->action,
                ];
            }
        }
        return array_values($items);
    }

    /**
     * Get enrolments for a given user on a given course
     *
     * @param int $userid
     * @param int $courseid
     * @return stdClass
     */
    public function get_enrolments_for($userid, $courseid) {
        global $DB;
        $enrol = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'solaissits']);
        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        $context = context_course::instance($course->id);
        $results = new stdClass();
        $results->user = (object)user_get_user_details($user);
        $results->course = $course;
        $results->enrolments = [];

        $sql = "SELECT ue.id, ue.status, ue.enrolid, ue.timestart, ue.timeend
        FROM {enrol} e
        JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
        WHERE e.courseid = :courseid AND e.enrol = 'solaissits'";
        $params = [
            'userid' => $userid,
            'courseid' => $courseid,
        ];
        $enrolments = $DB->get_records_sql($sql, $params);
        foreach ($enrolments as $enrolment) {
            $roleassignments = $DB->get_records_sql(
                "SELECT ra.id, ra.roleid, r.shortname
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                WHERE ra.contextid = :contextid
                    AND ra.userid = :userid
                    AND ra.component = 'enrol_solaissits'
                    AND ra.itemid = :enrolid",
                [
                    'contextid' => $context->id,
                    'enrolid' => $enrolment->enrolid,
                    'userid' => $user->id,
                ]
            );
            $enrolment->roles = array_values($roleassignments);
            $results->enrolments[] = $enrolment;
        }
        $results->queueditems = $this->get_queued_items_for($user->id, $course->id);
        return $results;
    }

    /**
     * Get course enrolemtns
     *
     * @param int $courseid
     * @return array
     */
    public function get_course_enrolments($courseid): array {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid]);
        $context = context_course::instance($course->id);
        $sql = "SELECT ue.id, ue.status, ue.enrolid, ue.timestart, ue.timeend, u.idnumber useridnumber, ue.userid
        FROM {enrol} e
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        JOIN {user} u ON u.id = ue.userid
        WHERE e.courseid = :courseid AND e.enrol = 'solaissits'";
        $params = [
            'courseid' => $courseid,
        ];
        $enrolments = $DB->get_records_sql($sql, $params);
        $coursegroups = groups_get_course_data($courseid);
        foreach ($enrolments as $key => $enrolment) {
            $roleassignments = $DB->get_records_sql(
                "SELECT ra.id, ra.roleid, r.shortname
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                WHERE ra.contextid = :contextid
                    AND ra.userid = :userid
                    AND ra.component = 'enrol_solaissits'
                    AND ra.itemid = :enrolid",
                [
                    'contextid' => $context->id,
                    'enrolid' => $enrolment->enrolid,
                    'userid' => $enrolment->userid,
                ]
            );
            $enrolment->roles = array_values($roleassignments);
            $usergroups = [];
            foreach ($coursegroups->groups as $coursegroup) {
                if (groups_is_member($coursegroup->id, $enrolment->userid)) {
                    $usergroups[] = (object)[
                        'id' => $coursegroup->id,
                        'name' => $coursegroup->name,
                    ];
                }
            }
            $enrolment->groups = $usergroups;
            $enrolments[$key] = $enrolment;
        }
        return $enrolments;
    }

    /**
     * Get list of queued items to be processed
     *
     * @param integer $limit
     * @param boolean $includegroups
     * @param int|null $templateapplied
     * @return array List of records to be processed.
     */
    private function get_queued_items($limit = 0, $includegroups = false, $templateapplied = null) {
        global $DB;
        // We could reduce the number of items that are returned by only selecting records
        // that have had their template applied.
        $params = [];
        $join = '';
        $where = '';
        $limit = '';
        if ($templateapplied !== null) {
            $join = "
            JOIN {customfield_data} cfd ON cfd.instanceid = sas.courseid
            JOIN {customfield_field} cff ON cff.id = cfd.fieldid AND cff.shortname = 'templateapplied' ";
            $where = "WHERE cfd.value = :templateapplied";
            $params['templateapplied'] = $templateapplied;
        }
        if ($limit > 0) {
            $limit = "LIMIT :limit";
            $params['limit'] = $limit;
        }
        $sql = "SELECT sas.*
        FROM {enrol_solaissits} sas
        {$join} {$where} {$limit}";
        $items = $DB->get_records_sql($sql, $params);
        if ($includegroups) {
            foreach ($items as $item) {
                $item->groups = [];
                $groupactions = $DB->get_records('enrol_solaissits_groups', ['solaissitsid' => $item->id]);
                foreach ($groupactions as $groupaction) {
                    $item->groups[] = [
                        'id' => $groupaction->id,
                        'name' => $groupaction->groupname,
                        'action' => $groupaction->action,
                    ];
                }
            }
        }
        return $items;
    }

    /**
     * Called by cron to process the queue
     *
     * @param progress_trace $trace
     * @return mixed
     */
    public function sync(progress_trace $trace) {
        if (!enrol_is_enabled('solaissits')) {
            return 2;
        }
        $processed = false;

        $processed = $this->process_queued_items($trace) || $processed;

        $processed = $this->process_deleted_courses($trace) || $processed;
        $trace->finished();
        return true;
    }

    /**
     * Process each queued item
     *
     * @param progress_trace $trace
     * @return boolean
     */
    private function process_queued_items(progress_trace $trace): bool {
        global $DB;
        // We may need more memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        // We might want to put limits here. Depends how many records pile up. At least chunk.
        // Watch for failures that might put processing out of sync.
        $queueditems = $this->get_queued_items(0, false, 1);
        $itemcount = count($queueditems);
        if ($itemcount == 0) {
            $trace->output("No items found to process.");
            return false;
        }
        $trace->output($itemcount . " enrolment items found to process.");
        foreach ($queueditems as $data) {
            $course = $DB->get_record('course', ['id' => $data->courseid]);
            $user = $DB->get_record('user', ['id' => $data->userid, 'deleted' => 0]);
            if (!$user) {
                $trace->output("User id {$data->userid} does not exist. Enrolment item removed for course id {$data->courseid}.");
                $this->dequeue_enrolment($data->id);
                continue;
            }
            $role = $DB->get_record('role', ['id' => $data->roleid]);
            // We only actually enrol when a course has had its template applied.
            // Otherwise the enrolment will be deleted when the template is applied.
            // But unenrolments can be applied no matter.
            $iscourseready = \enrol_solaissits\helper::istemplated($course->id);
            if (!$iscourseready && $data->action == 'add') {
                $trace->output($course->shortname . " hasn't had its template applied yet.");
                continue;
            }
            $instance = $this->get_enrol_instance($course);

            if (!isset($data->groups)) {
                // Get groups associated with the enrolment.
                $groupactions = $DB->get_records('enrol_solaissits_groups', ['solaissitsid' => $data->id]);
                foreach ($groupactions as $groupaction) {
                    $data->groups[] = [
                        'name' => $groupaction->groupname,
                        'action' => $groupaction->action,
                    ];
                }
            }

            // Finally proceed with the enrolment or unenrolment.
            // Enrolments will have an action: add, suspend, unsuspend.
            // Unenrolments will have an action: del.
            if ($data->action == 'del') {
                // A full unenrol will remove all groups.
                // This will also take care of special rules for the page type and role.
                $this->process_unenrol($data, $trace);
            } else {
                $status = ENROL_USER_ACTIVE;
                if ($data->action == 'suspend') {
                    $status = ENROL_USER_SUSPENDED;
                }
                // If the user exists and the timestart, timeend or status is different, this automatically changes to an update.
                $timestart = ($data->timestart > 0) ? date('Y-m-d', $data->timestart) : '';
                $timeend = ($data->timeend > 0) ? date('Y-m-d', $data->timeend) : '';
                $trace->output("{$data->action}, {$user->idnumber}, {$course->shortname}," .
                    " {$role->shortname}, {$timestart} - {$timeend}");
                $this->enrol_user($instance, $data->userid, $data->roleid,
                        $data->timestart, $data->timeend, $status);
                $groups = $data->groups ?? [];
                $this->process_groups($data->userid, $data->courseid, $groups);
            }
            $this->dequeue_enrolment($data->id);
        }
        return true;
    }

    /**
     * Dequeue any entries for deleted courses
     *
     * @param progress_trace $trace
     * @return boolean
     */
    private function process_deleted_courses(progress_trace $trace): bool {
        global $DB;
        // Get list of queued items where the courseid does not exist in the course table.
        // Dequeue those items.
        $deletedcourseitems = $DB->get_records_sql("SELECT sas.id, sas.courseid, sas.userid
            FROM {enrol_solaissits} sas
            LEFT JOIN {course} c ON c.id = sas.courseid
            WHERE c.id IS NULL");
        foreach ($deletedcourseitems as $item) {
            $trace->output("Course id {$item->courseid} not found. Enrolment item for user id {$item->userid} has been removed");
            $this->dequeue_enrolment($item->id);
        }
        return true;
    }

    /**
     * Gets the enrol instance for this course, or creates it if it doesn't.
     *
     * @param stdClass $course
     * @return stdClass
     */
    private function get_enrol_instance($course) {
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
            // Create an instance if it doesn't exist.
            $instanceid = $this->add_instance($course);
            $instances = enrol_get_instances($course->id, true);
            $instance = $instances[$instanceid];
        }
        return $instance;
    }
}
