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

namespace enrol_solaissits\external;

use enrol_solaissits\helper_trait;
use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once(__DIR__ . '/../helper_trait.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for SITS enrolments
 *
 * @package    enrol_solaissits
 * @category   test
 * @copyright  2024 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_course_enrolments_test extends externallib_advanced_testcase {
    use helper_trait;

    /**
     * Test get course enrolments
     *
     * @covers \enrol_solaissits_external::get_course_enrolments
     * @return void
     */
    public function test_get_course_enrolments(): void {
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
        enrol_users::execute($enrol['abc101']);
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
        enrol_users::execute($enrol['abc102']);

        $enrolments = get_course_enrolments::execute($course1->idnumber);
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
            $matches = array_filter($enrolments->enrolments, function ($enrolment) use ($idnumber) {
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
        $enrolments = get_course_enrolments::execute($course2->idnumber);
        $this->assertSame($course2->idnumber, $enrolments->course->idnumber);
        $this->assertSame($course2->fullname, $enrolments->course->fullname);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(4, $enrolments->queueditems);
        foreach ($users as $idnumber => $user) {
            $u = $user['user'];
            $matches = array_filter($enrolments->queueditems, function ($queueditem) use ($u) {
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
                    unenrol_users::execute($unenrol);
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
                unenrol_users::execute($unenrol);
            }
        }
        $enrolments = get_course_enrolments::execute($course2->idnumber);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(5, $enrolments->queueditems);

        // Do the same for ABC101.
        $enrolments = get_course_enrolments::execute($course1->idnumber);
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
                    unenrol_users::execute($unenrol);
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
                unenrol_users::execute($unenrol);
            }
        }
        $enrolments = get_course_enrolments::execute($course1->idnumber);
        $this->assertCount(2, $enrolments->enrolments);
        $this->assertCount(0, $enrolments->queueditems);

        $enrol['unitleader2-abc101'] = [
            [
                'roleshortname' => 'unitleader',
                'useridnumber' => 'UnitLeader2',
                'courseidnumber' => 'ABC101',
            ],
        ];
        enrol_users::execute($enrol['unitleader2-abc101']);
        $enrolments = get_course_enrolments::execute($course1->idnumber);
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
        enrol_users::execute($enrol['unitleader2-abc102']);
        $enrolments = get_course_enrolments::execute($course2->idnumber);
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(6, $enrolments->queueditems);
    }
}
