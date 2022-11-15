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
 * Testing externallib functions
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits;

use context_system;
use Exception;
use externallib_advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/solaissits/externallib.php');

/**
 * Test externallib functions
 */
class externallib_test extends externallib_advanced_testcase {

    /**
     * Test enrol users
     * @covers \enrol_solaissits_plugin::enrol_users()
     */
    public function test_enrol_users() {
        global $DB;
        $this->resetAfterTest();
        // Plugin isn't automatically enabled.
        $this->enable_plugin();

        $wsuser = $this->getDataGenerator()->create_user();
        $systemcontext = context_system::instance();
        $this->setUser($wsuser);

        // Set the required capabilities by the external function.
        $wsroleid = $this->assignUserCapability('enrol/solaissits:enrol', $systemcontext->id);
        $this->assignUserCapability('moodle/course:view', $systemcontext->id, $wsroleid);
        $this->assignUserCapability('moodle/role:assign', $systemcontext->id, $wsroleid);
        set_role_contextlevels($wsroleid, [CONTEXT_SYSTEM, CONTEXT_COURSE]);

        // Student role already exists, but create unitleader and externalexaminer.
        $unitleaderrole = $this->getDataGenerator()->create_role(['name' => 'Module leader', 'shortname' => 'unitleader']);
        $externalexaminerrole = $this->getDataGenerator()->create_role([
            'name' => 'External Examiner', 'shortname' => 'externalexaminer']);
        core_role_set_assign_allowed($wsroleid, 5); // Student.
        core_role_set_assign_allowed($wsroleid, $unitleaderrole);
        core_role_set_assign_allowed($wsroleid, $externalexaminerrole);

        $course1 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $course2 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC102']);
        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $student2 = $this->getDataGenerator()->create_user(['idnumber' => 'Student2']);
        $unitleader = $this->getDataGenerator()->create_user(['idnumber' => 'UnitLeader1']);
        $externalexaminer = $this->getDataGenerator()->create_user(['idnumber' => 'ExternalExaminer1']);

        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);
        // The instances don't automatically exist. They are created when an attempt to enrol someone happens.
        try {
            $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'solaissits'), '*', MUST_EXIST);
            $this->fail('Exception expected as the enrolment method hasn\'t been added yet');
        } catch (moodle_exception $ex) {
            $this->assertInstanceOf('dml_missing_record_exception', $ex);
            $this->assertSame('invalidrecord', $ex->errorcode);
        }
        // Call the external function.
        \enrol_solaissits_external::enrol_users([
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC101']
        ]);
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'solaissits'), '*', MUST_EXIST);
        $this->assertIsObject($instance1);
        $this->assertEquals(3, $DB->count_records('user_enrolments', array('enrolid' => $instance1->id)));
        $this->assertTrue(is_enrolled($context1, $student1, '', true));
        $this->assertTrue(is_enrolled($context1, $unitleader, '', true));
        $this->assertTrue(is_enrolled($context1, $externalexaminer, '', true));
        $this->assertFalse(is_enrolled($context1, $student2, '', true));
        // No-one's been added to a group, so no groups exist.
        // I've not switched on group mode for this course, but I don't think it matters.
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(0, $groups);
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC101',
                'groups' => [
                    [
                        'name' => 'L4'
                    ]
                ]
            ]
        ]);
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(1, $groups);
        $firstgroup = reset($groups);
        $this->assertTrue(groups_is_member($firstgroup->id, $student1->id));
        $this->assertFalse(groups_is_member($firstgroup->id, $unitleader->id));
        // Move Student1 from L4 to L5. Two groups now exist, but student is only a member of L5.
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC101',
                'groups' => [
                    [
                        'name' => 'L5' // Default action is 'add'.
                    ],
                    [
                        'name' => 'L4',
                        'action' => 'del'
                    ]
                ]
            ]
        ]);
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            if ($group->name == 'L4') {
                $this->assertFalse(groups_is_member($group->id, $student1->id));
            }
            if ($group->name == 'L5') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
            }
        }
        // Suspend an enrolment.
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC101',
                'suspend' => 1
            ]
        ]);
        $this->assertFalse(is_enrolled($context1, $student1, '', true)); // Excludes suspended users.

        // Tests with some bad data.
        // As it stands if a user, course, role doesn't exist an exception will be thrown.
        // This will halt processing for all entries. If processing one user per call, this is probably ok.
        // If batch processing, this is not ok.
        // This replicates Moodle's manual enrolment plugin style.
        // Incidentally, the same happens with the create_courses function.
    }

    /**
     * Test for unerolling users.
     * @covers \enrol_solaissits_plugin::unenrol_users()
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_unenrol_users() {
        global $DB;

        $this->resetAfterTest(true);
        // Plugin isn't automatically enabled.
        $this->enable_plugin();

        // The user who perform the action.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user); // Log this user in.
        $enrol = enrol_get_plugin('solaissits');
        // Create a course.
        $course = self::getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $enrol->add_instance($course);
        $coursecontext = \context_course::instance($course->id);
        $enrolinstance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'solaissits'), '*', MUST_EXIST);
        // Set the capability for the user.
        $roleid = $this->assignUserCapability('enrol/solaissits:enrol', $coursecontext);
        $this->assignUserCapability('enrol/solaissits:unenrol', $coursecontext, $roleid);
        $this->assignUserCapability('moodle/course:view', $coursecontext, $roleid);
        $this->assignUserCapability('moodle/role:assign', $coursecontext, $roleid);
        // Create a student and enrol them into the course.
        $student = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $enrol->enrol_user($enrolinstance, $student->id);
        $this->assertTrue(is_enrolled($coursecontext, $student));
        // Call the web service to unenrol.
        \enrol_solaissits_external::unenrol_users([
            ['useridnumber' => $student->idnumber, 'courseidnumber' => $course->idnumber]
        ]);
        $this->assertFalse(is_enrolled($coursecontext, $student));
    }

    /**
     * Enable SOL AIS-SITS plugin
     *
     * @return void
     */
    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['solaissits'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }
}
