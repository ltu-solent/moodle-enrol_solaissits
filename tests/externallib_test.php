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
        $this->setup_enrol();

        $course1 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $course2 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC102']);
        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $student2 = $this->getDataGenerator()->create_user(['idnumber' => 'Student2']);
        $unitleader = $this->getDataGenerator()->create_user(['idnumber' => 'UnitLeader1']);
        $externalexaminer = $this->getDataGenerator()->create_user(['idnumber' => 'ExternalExaminer1']);
        // Set up customfields required for enrolments.
        $fieldgenerator = $this->setup_customfields();
        // Course 2 has had a template applied, and is a module pagetype.
        // This is the minimum required for enrolments to happen using this method.
        $this->set_customfields($course2->id, [
                'templateapplied' => 1,
                'pagetype' => 'module',
            ],
            $fieldgenerator
        );

        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);
        // The instances don't automatically exist. They are created when an attempt to enrol someone happens.
        try {
            $instance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'solaissits'], '*', MUST_EXIST);
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
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC102'],
        ]);
        $instance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'solaissits'], '*', MUST_EXIST);
        $instance2 = $DB->get_record('enrol', ['courseid' => $course2->id, 'enrol' => 'solaissits'], '*', MUST_EXIST);
        $this->assertIsObject($instance1);
        // The template hasn't been applied to course1, so these are queued, but course2 enrolments have been successful.
        $this->assertEquals(0, $DB->count_records('user_enrolments', ['enrolid' => $instance1->id]));
        $this->assertEquals(3, $DB->count_records('enrol_solaissits'));
        $this->assertEquals(3, $DB->count_records('user_enrolments', ['enrolid' => $instance2->id]));
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
                        'name' => 'L4',
                    ],
                ],
            ],
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
                        'name' => 'L5', // Default action is 'add'.
                    ],
                    [
                        'name' => 'L4',
                        'action' => 'del',
                    ],
                ],
            ],
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
                'suspend' => 1,
            ],
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
        $this->setup_enrol();
        $enrol = enrol_get_plugin('solaissits');
        // Create a course.
        $course = self::getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $enrol->add_instance($course);
        $coursecontext = \context_course::instance($course->id);
        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'solaissits'], '*', MUST_EXIST);

        $fieldgenerator = $this->setup_customfields();
        // Course has had a template applied, and is a module pagetype.
        // This is the minimum required for unenrolments to happen using this method.
        $this->set_customfields($course->id, [
                'templateapplied' => 1,
                'pagetype' => 'module',
            ],
            $fieldgenerator,
        );

        // Create a student and enrol them into the course.
        $student = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $enrol->enrol_user($enrolinstance, $student->id);
        $this->assertTrue(is_enrolled($coursecontext, $student));
        // Call the web service to unenrol.
        \enrol_solaissits_external::unenrol_users([
            [
                'useridnumber' => $student->idnumber,
                'courseidnumber' => $course->idnumber,
                'roleshortname' => 'student',
            ],
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

        $this->setup_enrol();
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
                        'action' => 'add',
                    ],
                ],
            ],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC102',
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add',
                    ],
                ],
            ],
            ['roleshortname' => 'student', 'useridnumber' => 'Student2', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC102'],
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

    /**
     * Test get course enrolments
     *
     * @covers \enrol_solaissits_external::get_course_enrolments
     * @return void
     */
    public function test_get_course_enrolments() {
        $this->resetAfterTest();

        $this->setup_enrol();
        $course1 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC101']);
        $course2 = $this->getDataGenerator()->create_course(['idnumber' => 'ABC102']);
        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $student2 = $this->getDataGenerator()->create_user(['idnumber' => 'Student2']);
        $unitleader = $this->getDataGenerator()->create_user(['idnumber' => 'UnitLeader1']);
        $unitleader2 = $this->getDataGenerator()->create_user(['idnumber' => 'UnitLeader2']);
        $externalexaminer = $this->getDataGenerator()->create_user(['idnumber' => 'ExternalExaminer1']);
        // ABC101 is a templated page. This means enrolments will be actioned.
        // ABC102 remains untemplated, so all actions will be queued.
        $customfields = $this->setup_customfields();
        $this->set_customfields($course1->id, ['pagetype' => 'module', 'templateapplied' => 1], $customfields);
        // Call the external function. Course1 enrolments will happen. Course2 enrolments will queue.
        $enrol = [];
        $enrol['abc101'] = [
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC101',
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add',
                    ],
                ],
            ],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC101'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC101'],
        ];
        \enrol_solaissits_external::enrol_users($enrol['abc101']);
        $enrol['abc102'] = [
            ['roleshortname' => 'student', 'useridnumber' => 'Student1', 'courseidnumber' => 'ABC102',
                'groups' => [
                    [
                        'name' => 'L4',
                        'action' => 'add',
                    ],
                ],
            ],
            ['roleshortname' => 'student', 'useridnumber' => 'Student2', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'unitleader', 'useridnumber' => 'UnitLeader1', 'courseidnumber' => 'ABC102'],
            ['roleshortname' => 'externalexaminer', 'useridnumber' => 'ExternalExaminer1', 'courseidnumber' => 'ABC102'],
        ];
        \enrol_solaissits_external::enrol_users($enrol['abc102']);

        $enrolments = \enrol_solaissits_external::get_course_enrolments($course1->idnumber);
        $this->assertSame($course1->idnumber, $enrolments->course->idnumber);
        $this->assertSame($course1->fullname, $enrolments->course->fullname);
        $this->assertCount(3, $enrolments->enrolments);
        $users = [
            $student1->idnumber => [
                'user' => $student1,
                'role' => 'student',
            ],
            $unitleader->idnumber => [
                'user' => $unitleader,
                'role' => 'unitleader',
            ],
            $externalexaminer->idnumber => [
                'user' => $externalexaminer,
                'role' => 'externalexaminer',
            ],
        ];
        foreach ($users as $idnumber => $user) {
            $matches = array_filter($enrolments->enrolments, function($enrolment) use ($idnumber) {
                return $idnumber == $enrolment->useridnumber;
            });
            $match = reset($matches);
            $this->assertEquals($idnumber, $match->useridnumber);
            $this->assertCount(1, $match->roles);
            $role = reset($match->roles);
            $this->assertSame($user['role'], $role->shortname);
        }
        $this->assertCount(0, $enrolments->queueditems);
        $users[$student2->idnumber] = [
            'user' => $student2,
            'role' => 'student',
        ];
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course2->idnumber);
        $this->assertSame($course2->idnumber, $enrolments->course->idnumber);
        $this->assertSame($course2->fullname, $enrolments->course->fullname);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(4, $enrolments->queueditems);
        foreach ($users as $idnumber => $user) {
            $u = $user['user'];
            $matches = array_filter($enrolments->queueditems, function($queueditem) use ($u) {
                return $u->id == $queueditem->userid;
            });
            $match = reset($matches);
            $this->assertEquals($u->id, $match->userid);
            $this->assertSame($idnumber, $match->useridnumber);
            $this->assertSame($user['role'], $match->roleshortname);
        }

        // Switch module leaders. Find the old module leader, unenrol them.
        foreach ($enrolments->enrolments as $enrolment) {
            foreach ($enrolment->roles as $role) {
                if ($role->shortname == 'unitleader') {
                    $unenrol = [
                        [
                            'useridnumber' => $enrolment->useridnumber,
                            'courseidnumber' => 'ABC102',
                            'roleshortname' => 'unitleader',
                        ],
                    ];
                    \enrol_solaissits_external::unenrol_users($unenrol);
                }
            }
        }
        // Just in case their enrolment has been queued.
        foreach ($enrolments->queueditems as $queueditem) {
            if ($queueditem->roleshortname == 'unitleader') {
                $unenrol = [
                    [
                        'useridnumber' => $queueditem->useridnumber,
                        'courseidnumber' => 'ABC102',
                        'roleshortname' => 'unitleader',
                    ],
                ];
                // The unenrol action will be queued for ABC102.
                // But the queue will be actioned sequentially, so this user will be enrolled,
                // then eventually unenrolled.
                \enrol_solaissits_external::unenrol_users($unenrol);
            }
        }
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course2->idnumber);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(5, $enrolments->queueditems);

        // Do the same for ABC101.
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course1->idnumber);
        $this->assertCount(0, $enrolments->queueditems);
        $this->assertCount(3, $enrolments->enrolments);
        foreach ($enrolments->enrolments as $enrolment) {
            foreach ($enrolment->roles as $role) {
                if ($role->shortname == 'unitleader') {
                    $unenrol = [
                        [
                            'useridnumber' => $enrolment->useridnumber,
                            'courseidnumber' => 'ABC101',
                            'roleshortname' => 'unitleader',
                        ],
                    ];
                    \enrol_solaissits_external::unenrol_users($unenrol);
                }
            }
        }
        // Just in case their enrolment has been queued.
        foreach ($enrolments->queueditems as $queueditem) {
            if ($queueditem->roleshortname == 'unitleader') {
                $unenrol = [
                    [
                        'useridnumber' => $queueditem->useridnumber,
                        'courseidnumber' => 'ABC101',
                        'roleshortname' => 'unitleader',
                    ],
                ];
                \enrol_solaissits_external::unenrol_users($unenrol);
            }
        }
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course1->idnumber);
        $this->assertCount(2, $enrolments->enrolments);
        $this->assertCount(0, $enrolments->queueditems);

        $enrol['unitleader2-abc101'] = [
            [
                'roleshortname' => 'unitleader',
                'useridnumber' => 'UnitLeader2',
                'courseidnumber' => 'ABC101',
            ],
        ];
        \enrol_solaissits_external::enrol_users($enrol['unitleader2-abc101']);
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course1->idnumber);
        $this->assertCount(3, $enrolments->enrolments);
        $this->assertCount(0, $enrolments->queueditems);

        foreach ($enrolments->enrolments as $enrolment) {
            foreach ($enrolment->roles as $role) {
                if ($role->shortname == 'unitleader') {
                    $this->assertSame($unitleader2->idnumber, $enrolment->useridnumber);
                }
            }
        }

        $enrol['unitleader2-abc102'] = [
            [
                'roleshortname' => 'unitleader',
                'useridnumber' => 'UnitLeader2',
                'courseidnumber' => 'ABC102',
            ],
        ];
        \enrol_solaissits_external::enrol_users($enrol['unitleader2-abc102']);
        $enrolments = \enrol_solaissits_external::get_course_enrolments($course2->idnumber);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(6, $enrolments->queueditems);
    }
}
