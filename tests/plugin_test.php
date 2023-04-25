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
 * Solaissits enrol plugin test
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits;

use context_course;
use externallib_advanced_testcase;
use null_progress_trace;
use stdClass;
use text_progress_trace;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/solaissits/externallib.php');
require_once(__DIR__ . '/helper_trait.php');

/**
 * Test the main enrolment class
 */
class plugin_test extends externallib_advanced_testcase {
    use helper_trait;

    /**
     * Get user enrolments
     *
     * @covers \enrol_solaissits_plugin::get_enrolments_for
     * @return void
     */
    public function test_get_enrolments_for() {
        global $DB;
        $this->resetAfterTest();
        $enrol = enrol_get_plugin('solaissits');
        $this->enable_plugin();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        // This is usually accessed by a user with permissions to view certain user fields.
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $solgen = $this->getDataGenerator()->get_plugin_generator('enrol_solaissits');
        $qi = $solgen->create_queued_item([
            'roleid' => $studentrole->id,
            'userid' => $user->id,
            'courseid' => $course->id,
            'groups' => [
                'groupname' => 'daftpunk'
            ]
        ]);
        $instanceid = $enrol->add_instance($course);
        $instances = enrol_get_instances($course->id, true);
        $instance = $instances[$instanceid];
        $enrolments = $enrol->get_enrolments_for($user->id, $course->id);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(1, $enrolments->queueditems);
        $this->assertEquals($user->username, $enrolments->user->username);
        $this->assertEquals($course->shortname, $enrolments->course->shortname);

        $enrol->enrol_user($instance, $user->id, $studentrole->id);
        $enrolments = $enrol->get_enrolments_for($user->id, $course->id);
        $this->assertCount(1, $enrolments->enrolments);
        $this->assertEquals('student', $enrolments->enrolments[0]->roles[0]->shortname);
    }

    /**
     * Test external_enrol_user
     * @covers \enrol_solaissits_plugin::external_enrol_user
     *
     * @return void
     */
    public function test_external_enrol_user() {
        global $DB;
        $this->resetAfterTest();
        $this->enable_plugin();
        $enrol = enrol_get_plugin('solaissits');
        $customfields = $this->setup_customfields();

        $course1 = $this->getDataGenerator()->create_course();
        $course1context = context_course::instance($course1->id);
        $course2 = $this->getDataGenerator()->create_course();
        $course2context = context_course::instance($course2->id);
        $this->set_customfields($course1->id,
            [
                'pagetype' => 'module',
                'templateapplied' => 0
            ],
            $customfields
        );
        $this->set_customfields($course2->id,
            [
                'pagetype' => 'module',
                'templateapplied' => 1
            ],
            $customfields
        );
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $studentrole = helper::get_role_by_shortname('student');
        $data = new stdClass();
        $data->courseid = $course1->id;
        $data->userid = $student1->id;
        $data->action = 'add'; // Actions: add, suspend, unsuspend.
        $data->timestart = 0;
        $data->timeend = 0;
        $data->roleid = $studentrole->id;
        $data->groups = [
            [
                'action' => 'add',
                'name' => 'L4'
            ]
        ];
        $enrol->external_enrol_user($data);

        $this->assertFalse(is_enrolled($course1context, $student1));
        // User is queued.
        $this->assertCount(1, $DB->get_records('enrol_solaissits', [
            'courseid' => $course1->id,
            'userid' => $student1->id,
            'roleid' => $studentrole->id]));
        // User is not a member of any group.
        $this->assertCount(0, groups_get_all_groups($course1->id, $student1->id));

        // Course2 is templated, so enrolments occur.
        $data->courseid = $course2->id;
        $enrol->external_enrol_user($data);
        $this->assertTrue(is_enrolled($course2context, $student1));
        $this->assertCount(0, $DB->get_records('enrol_solaissits', [
            'courseid' => $course2->id,
            'userid' => $student1->id,
            'roleid' => $studentrole->id]));
        // User is a member of the L4 group.
        $groups = groups_get_all_groups($course2->id, $student1->id);
        $this->assertCount(1, $groups);
        foreach ($groups as $group) {
            if ($group->name == 'L4') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
            }
            if ($group->name == 'L5') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
            }
        }
        // User is a member of the L5 group and removed from L4.
        $data->groups = [
            [
                'action' => 'add',
                'name' => 'L5'
            ], [
                'action' => 'del',
                'name' => 'L4'
            ]
        ];
        $enrol->external_enrol_user($data);
        $groups = groups_get_all_groups($course2->id);
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            if ($group->name == 'L4') {
                $this->assertFalse(groups_is_member($group->id, $student1->id));
            }
            if ($group->name == 'L5') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
            }
        }
        // Suspend enrolment.
        $data->action = 'suspend';
        $enrol->external_enrol_user($data);
        $this->assertFalse(is_enrolled($course2context, $student1, '', true));
    }

    /**
     * Unenrol user called by external api
     * @covers \enrol_solaissits_plugin::external_unenrol_user
     *
     * @return void
     */
    public function test_external_unenrol_user() {
        $this->resetAfterTest();
        $this->setup_enrol();
        $enrol = enrol_get_plugin('solaissits');
        $customfields = $this->setup_customfields();
        $studentrole = helper::get_role_by_shortname('student');
        $unitleaderrole = helper::get_role_by_shortname('unitleader'); // This is setup by setup_enrol.
        // We want to suspend the student on Modules, and totally unenrol them from courses.
        $enrol->set_config('roleactions_' . $studentrole->id, json_encode([
            'module' => ENROL_EXT_REMOVED_SUSPEND,
            'course' => ENROL_EXT_REMOVED_UNENROL
        ]));
        $enrol->set_config('roleactions_' . $unitleaderrole->id, json_encode([
            'module' => ENROL_EXT_REMOVED_UNENROL,
            'course' => ENROL_EXT_REMOVED_KEEP
        ]));

        $student = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $teacher = $this->getDataGenerator()->create_user(['idnumber' => 'Teacher1']);

        $module = $this->getDataGenerator()->create_course(['idnumber' => 'Module1']);
        $moduleinstance = helper::get_enrol_instance($module);
        $modulecontext = context_course::instance($module->id);
        $this->set_customfields(
            $module->id,
            [
                'templateapplied' => 1,
                'pagetype' => 'module'
            ],
            $customfields
        );

        $course = $this->getDataGenerator()->create_course(['idnumber' => 'Course1']);
        $courseinstance = helper::get_enrol_instance($course);
        $coursecontext = context_course::instance($course->id);
        $this->set_customfields(
            $course->id,
            [
                'templateapplied' => 1,
                'pagetype' => 'course'
            ],
            $customfields
        );

        $enrol->enrol_user($moduleinstance, $student->id, $studentrole->id);
        $enrol->enrol_user($moduleinstance, $teacher->id, $unitleaderrole->id);
        $enrol->enrol_user($courseinstance, $student->id, $studentrole->id);
        $enrol->enrol_user($courseinstance, $teacher->id, $unitleaderrole->id);
        // Both users should be enrolled on both pages.
        $this->assertTrue(is_enrolled($modulecontext, $student));
        $this->assertTrue(is_enrolled($modulecontext, $teacher));
        $this->assertTrue(is_enrolled($coursecontext, $student));
        $this->assertTrue(is_enrolled($coursecontext, $teacher));

        $data = new stdClass();
        $data->userid = $student->id;
        $data->courseid = $module->id;
        $data->roleid = $studentrole->id;
        $enrol->external_unenrol_user($data);
        // Student should be suspended but still have role on Module.
        $enrolments = $enrol->get_enrolments_for($student->id, $module->id);
        $this->assertCount(1, $enrolments->enrolments);
        $this->assertEquals(ENROL_USER_SUSPENDED, $enrolments->enrolments[0]->status);
        $this->assertCount(1, $enrolments->enrolments[0]->roles);

        // Student should be unenrolled from Course.
        $data->courseid = $course->id;
        $enrol->external_unenrol_user($data);
        $enrolments = $enrol->get_enrolments_for($student->id, $course->id);
        $this->assertCount(0, $enrolments->enrolments);

        // Reactivate student enrolment.
        $enrol->update_user_enrol($moduleinstance, $data->userid, ENROL_USER_ACTIVE);
        // We want to suspend and remove role for the student on Modules, and totally unenrol them from courses.
        $enrol->set_config('roleactions_' . $studentrole->id, json_encode([
            'module' => ENROL_EXT_REMOVED_SUSPENDNOROLES,
            'course' => ENROL_EXT_REMOVED_UNENROL
        ]));
        $data = new stdClass();
        $data->userid = $student->id;
        $data->courseid = $module->id;
        $data->roleid = $studentrole->id;
        $enrol->external_unenrol_user($data);
        $enrolments = $enrol->get_enrolments_for($student->id, $module->id);
        $this->assertCount(1, $enrolments->enrolments);
        $this->assertEquals(ENROL_USER_SUSPENDED, $enrolments->enrolments[0]->status);
        $this->assertCount(0, $enrolments->enrolments[0]->roles);

        // Unit leaders will be completely unenrolled from Modules, but nothing happens on Courses.
        $data = new stdClass();
        $data->userid = $teacher->id;
        $data->courseid = $module->id;
        $data->roleid = $unitleaderrole->id;
        $enrol->external_unenrol_user($data);
        $enrolments = $enrol->get_enrolments_for($teacher->id, $module->id);
        $this->assertCount(0, $enrolments->enrolments);

        $data = new stdClass();
        $data->userid = $teacher->id;
        $data->courseid = $course->id;
        $data->roleid = $unitleaderrole->id;
        $enrol->external_unenrol_user($data);
        $enrolments = $enrol->get_enrolments_for($teacher->id, $course->id);
        $this->assertCount(1, $enrolments->enrolments);
        $this->assertEquals(ENROL_USER_ACTIVE, $enrolments->enrolments[0]->status);
        $this->assertCount(1, $enrolments->enrolments[0]->roles);
    }

    /**
     * Get queued items for given user on given course or all users on a given course.
     * @covers \enrol_solaissits_plugin::get_queued_items_for
     *
     * @return void
     */
    public function test_get_queued_items_for() {
        $this->resetAfterTest();
        $this->enable_plugin();
        $enrol = enrol_get_plugin('solaissits');
        $studentrole = helper::get_role_by_shortname('student');
        $qigen = $this->getDataGenerator()->get_plugin_generator('enrol_solaissits');
        $courses = [];
        $courses['course1']  = $this->getDataGenerator()->create_course(['shortname' => 'course1']);
        $courses['course2']  = $this->getDataGenerator()->create_course(['shortname' => 'course2']);
        $users = [];
        $qis = ['course1' => [], 'course2' => []];
        for ($x = 0; $x < 10; $x++) {
            $users[$x] = $this->getDataGenerator()->create_user(['username' => 'username' . $x, 'idnumber' => 'Student' . $x]);
            $qis['course1'][$x] = $qigen->create_queued_item([
                'courseid' => $courses['course1']->id,
                'userid' => $users[$x]->id,
                'roleid' => $studentrole->id,
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add'
                    ]
                ]
            ]);
            $qis['course2'][$x] = $qigen->create_queued_item([
                'courseid' => $courses['course2']->id,
                'userid' => $users[$x]->id,
                'roleid' => $studentrole->id,
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add'
                    ]
                ]
            ]);
        }

        $result = $enrol->get_queued_items_for($users[0]->id, $courses['course1']->id);
        $this->assertCount(1, $result);
        $item = reset($result);
        $this->assertEquals($qis['course1'][0]->id, $item->id);
        // Can now pass in nothing, but get nothing in return.
        $result = $enrol->get_queued_items_for();
        $this->assertEmpty($result);

        // Get queued items for specified user.
        $result = $enrol->get_queued_items_for($users[0]->id);
        $this->assertCount(2, $result);
        $courseuser0 = $qis['course1'][0];
        $matches = array_filter($result, function($item) use ($courseuser0) {
            return $item->id == $courseuser0->id;
        });
        $this->assertCount(1, $matches);
        $match = reset($matches);
        $this->assertEquals($courseuser0->userid, $match->userid);
        $this->assertEquals($users[0]->idnumber, $match->useridnumber);
        $this->assertEquals($courseuser0->courseid, $match->courseid);
        $this->assertEquals($courseuser0->roleid, $match->roleid);
        $this->assertEquals($studentrole->shortname, $match->roleshortname);
        $this->assertEquals($courseuser0->action, $match->action);
        $this->assertEquals($courseuser0->id, $match->id);

        $courseuser0 = $qis['course2'][0];
        $matches = array_filter($result, function($item) use ($courseuser0) {
            return $item->id == $courseuser0->id;
        });
        $this->assertCount(1, $matches);
        $match = reset($matches);
        $this->assertEquals($courseuser0->userid, $match->userid);
        $this->assertEquals($users[0]->idnumber, $match->useridnumber);
        $this->assertEquals($courseuser0->courseid, $match->courseid);
        $this->assertEquals($courseuser0->roleid, $match->roleid);
        $this->assertEquals($studentrole->shortname, $match->roleshortname);
        $this->assertEquals($courseuser0->action, $match->action);
        $this->assertEquals($courseuser0->id, $match->id);

        // Get queued items for specified course.
        $result = $enrol->get_queued_items_for(0, $courses['course1']->id);
        $this->assertCount(10, $result);
        for ($x = 0; $x < 10; $x++) {
            $user = $users[$x];
            $matches = array_filter($result, function($item) use ($user) {
                return $user->id == $item->userid;
            });
            $this->assertCount(1, $matches);
            $match = reset($matches);
            $this->assertEquals($qis['course1'][$x]->userid, $match->userid);
            $this->assertEquals($qis['course1'][$x]->courseid, $match->courseid);
            $this->assertEquals($qis['course1'][$x]->roleid, $match->roleid);
            $this->assertEquals($qis['course1'][$x]->action, $match->action);
        }
    }

    /**
     * Cron sync function test
     * @covers \enrol_solaissits_plugin::sync
     *
     * @return void
     */
    public function test_sync() {
        global $DB;
        $this->resetAfterTest();
        $this->setup_enrol();
        $enrol = enrol_get_plugin('solaissits');
        $customfields = $this->setup_customfields();

        $module1 = $this->getDataGenerator()->create_course();
        $module1context = context_course::instance($module1->id);
        $this->set_customfields($module1->id, [
            'pagetype' => 'module'
        ], $customfields);

        $module2 = $this->getDataGenerator()->create_course();
        $module2context = context_course::instance($module2->id);
        $this->set_customfields($module2->id, [
            'pagetype' => 'module',
            'templateapplied' => 1
        ], $customfields);

        $course1 = $this->getDataGenerator()->create_course();
        $course1context = context_course::instance($course1->id);
        $course1custom = $this->set_customfields($course1->id, [
            'pagetype' => 'course',
            'templateapplied' => 0
        ], $customfields);

        $course2 = $this->getDataGenerator()->create_course();
        $course2context = context_course::instance($course2->id);
        $this->set_customfields($course2->id, [
            'pagetype' => 'course',
            'templateapplied' => 1
        ], $customfields);

        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $student2 = $this->getDataGenerator()->create_user(['idnumber' => 'Student2']);
        $studentrole = helper::get_role_by_shortname('student');

        $trace = new text_progress_trace();
        $enrol->sync($trace);

        $qigen = $this->getDataGenerator()->get_plugin_generator('enrol_solaissits');
        $qigen->create_queued_item([
            'userid' => $student1->id,
            'courseid' => $module1->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student1->id,
            'courseid' => $module2->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student1->id,
            'courseid' => $course1->id,
            'roleid' => $studentrole->id,
            'groups' => [
                ['groupname' => 'L4']
            ]
        ]);
        // Second queued item for student 1 on course 1.
        $qigen->create_queued_item([
            'userid' => $student1->id,
            'courseid' => $course1->id,
            'roleid' => $studentrole->id,
            'groups' => [
                ['action' => 'del', 'groupname' => 'L4'],
                ['action' => 'add', 'groupname' => 'L5']
            ]
        ]);
        $qigen->create_queued_item([
            'userid' => $student1->id,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student2->id,
            'courseid' => $module1->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student2->id,
            'courseid' => $module2->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student2->id,
            'courseid' => $course1->id,
            'roleid' => $studentrole->id
        ]);
        $qigen->create_queued_item([
            'userid' => $student2->id,
            'courseid' => $course2->id,
            'roleid' => $studentrole->id
        ]);
        // 9 Queued items before sync begins.
        $this->assertCount(9, $DB->get_records('enrol_solaissits'));

        // Module 2 and Course 2 both have template applied, so all their queued items are processed (4).
        $enrol->sync(new null_progress_trace());
        $this->assertCount(5, $DB->get_records('enrol_solaissits'));
        $this->assertTrue(is_enrolled($module2context, $student1));
        $this->assertTrue(is_enrolled($module2context, $student2));
        $this->assertTrue(is_enrolled($course2context, $student1));
        $this->assertTrue(is_enrolled($course2context, $student2));

        $this->assertFalse(is_enrolled($module1context, $student1));
        $this->assertFalse(is_enrolled($module1context, $student2));
        $this->assertFalse(is_enrolled($course1context, $student1));
        $this->assertFalse(is_enrolled($course1context, $student2));

        // Module 1 now has template applied, so all their queued items are processed (2).
        $this->set_customfields($module1->id, ['templateapplied' => 1], $customfields);
        $enrol->sync(new null_progress_trace());
        $this->assertCount(3, $DB->get_records('enrol_solaissits'));
        $this->assertTrue(is_enrolled($module1context, $student1));
        $this->assertTrue(is_enrolled($module1context, $student2));

        // Nothing has changed so there's nothing more to process.
        $enrol->sync(new text_progress_trace());

        // Course 2 now has template applied (3).
        // This is a little hacky, but is required because the field already has a value set.
        $course1custom['templateapplied']->set('value', 1)->set('charvalue', 1)->save();
        $enrol->sync(new null_progress_trace());
        $this->assertCount(0, $DB->get_records('enrol_solaissits'));
        $this->assertTrue(is_enrolled($course1context, $student1));
        $this->assertTrue(is_enrolled($course1context, $student2));
        // There are groups in course 2.
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            if ($group->name == 'L4') {
                $this->assertFalse(groups_is_member($group->id, $student1->id));
                $this->assertFalse(groups_is_member($group->id, $student2->id));
            }
            if ($group->name == 'L5') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
                $this->assertFalse(groups_is_member($group->id, $student2->id));
            }
        }
        $this->expectOutputString("No items found to process.\nNo items found to process.\n");
    }
}
