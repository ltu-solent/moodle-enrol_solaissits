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
use externallib_advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/solaissits/externallib.php');
require_once(__DIR__ . '/helper_trait.php');

/**
 * Test externallib functions
 */
class externallib_test extends externallib_advanced_testcase {
    use helper_trait;

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
        // Set up customfields required for enrolments.
        $fieldgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $fieldcat = $fieldgenerator->create_category(
            [
                'name' => 'Student Records System',
                'contextid' => context_system::instance()->id
            ]
        );
        $templateappliedfield = $fieldgenerator->create_field([
            'shortname' => 'templateapplied',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        $pagetypefield = $fieldgenerator->create_field([
            'shortname' => 'pagetype',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        // Course 2 has had a template applied, and is a module pagetype.
        // This is the minimum required for enrolments to happen using this method.
        $fieldgenerator->add_instance_data($templateappliedfield, $course2->id, '1');
        $fieldgenerator->add_instance_data($pagetypefield, $course2->id, 'module');

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
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC102']
        ]);
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'solaissits'), '*', MUST_EXIST);
        $instance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'solaissits'), '*', MUST_EXIST);
        $this->assertIsObject($instance1);
        // The template hasn't been applied to course1, so these are queued, but course2 enrolments have been successful.
        $this->assertEquals(0, $DB->count_records('user_enrolments', array('enrolid' => $instance1->id)));
        $this->assertEquals(3, $DB->count_records('enrol_solaissits'));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array('enrolid' => $instance2->id)));
        $this->assertTrue(is_enrolled($context2, $student1, '', true));
        $this->assertTrue(is_enrolled($context2, $unitleader, '', true));
        $this->assertTrue(is_enrolled($context2, $externalexaminer, '', true));
        $this->assertFalse(is_enrolled($context1, $student1, '', true));
        $this->assertFalse(is_enrolled($context1, $unitleader, '', true));
        $this->assertFalse(is_enrolled($context1, $externalexaminer, '', true));
        // No-one's been added to a group, so no groups exist.
        // I've not switched on group mode for this course, but I don't think it matters.
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(0, $groups);
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC102',
                'groups' => [
                    [
                        'name' => 'L4'
                    ]
                ]
            ]
        ]);
        $groups = groups_get_all_groups($course2->id);
        $this->assertCount(1, $groups);
        $firstgroup = reset($groups);
        $this->assertTrue(groups_is_member($firstgroup->id, $student1->id));
        $this->assertFalse(groups_is_member($firstgroup->id, $unitleader->id));
        // Move Student1 from L4 to L5. Two groups now exist, but student is only a member of L5.
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC102',
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
        // Suspend an enrolment.
        \enrol_solaissits_external::enrol_users([
            [
                'roleshortname' => 'student',
                'useridnumber' => 'Student1',
                'courseidnumber' => 'ABC102',
                'suspend' => 1
            ]
        ]);
        $this->assertFalse(is_enrolled($context2, $student1, '', true)); // Excludes suspended users.

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

        // Set up customfields required for enrolments.
        $fieldgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $fieldcat = $fieldgenerator->create_category(
            [
                'name' => 'Student Records System',
                'contextid' => context_system::instance()->id
            ]
        );
        $templateappliedfield = $fieldgenerator->create_field([
            'shortname' => 'templateapplied',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        $pagetypefield = $fieldgenerator->create_field([
            'shortname' => 'pagetype',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        // Course has had a template applied, and is a module pagetype.
        // This is the minimum required for unenrolments to happen using this method.
        $fieldgenerator->add_instance_data($templateappliedfield, $course->id, '1');
        $fieldgenerator->add_instance_data($pagetypefield, $course->id, 'module');

        // Create a student and enrol them into the course.
        $student = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $enrol->enrol_user($enrolinstance, $student->id);
        $this->assertTrue(is_enrolled($coursecontext, $student));
        // Call the web service to unenrol.
        \enrol_solaissits_external::unenrol_users([
            ['useridnumber' => $student->idnumber, 'courseidnumber' => $course->idnumber, 'roleshortname' => 'student']
        ]);
        $this->assertFalse(is_enrolled($coursecontext, $student));
    }

    /**
     * Test API call to get enrolment status
     *
     * @covers \enrol_solaissits_external::get_enrolments
     * @return void
     */
    public function test_get_enrolments() {
        global $DB;
        $this->resetAfterTest();

        $enrols = $this->setup_enrol();
        $course1 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $course2 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC102']);
        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $student2 = $this->getDataGenerator()->create_user(['idnumber' => 'Student2']);
        $unitleader = $this->getDataGenerator()->create_user(['idnumber' => 'UnitLeader1']);
        $externalexaminer = $this->getDataGenerator()->create_user(['idnumber' => 'ExternalExaminer1']);
        $customfields = $this->setup_customfields();
        $this->set_customfields($course1->id, ['pagetype' => 'module', 'templateapplied' => 1], $customfields);
        // Call the external function. Course1 enrolments will happen. Course2 enrolments will queue.
        \enrol_solaissits_external::enrol_users([
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC101',
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add'
                    ]
                ]
            ],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC102',
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add'
                    ]
                ]
            ],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC102']
        ]);

        $enrolments = \enrol_solaissits_external::get_enrolments('ABC101', 'Student1');
        $this->assertCount(1, $enrolments->enrolments);
        $this->assertCount(1, $enrolments->enrolments[0]->roles);
        $this->assertEquals('student', $enrolments->enrolments[0]->roles[0]->shortname);
        $this->assertCount(0, $enrolments->queueditems);
        $groups = groups_get_all_groups($course1->id);
        $this->assertCount(1, $groups);
        foreach ($groups as $group) {
            if ($group->name == 'L4') {
                $this->assertTrue(groups_is_member($group->id, $student1->id));
            }
        }

        $enrolments = \enrol_solaissits_external::get_enrolments('ABC102', 'Student1');
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(1, $enrolments->queueditems);
        $this->assertEquals(5, $enrolments->queueditems[0]->roleid);
        $this->assertCount(1, $enrolments->queueditems[0]->groups);
        $groups = groups_get_all_groups($course2->id);
        // Group count is zero because enrolments have been queued but not actioned.
        $this->assertCount(0, $groups);
    }
}
