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

use core\context;
use enrol_solaissits\helper_trait;
use externallib_advanced_testcase;
use moodle_exception;

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
final class enrol_users_test extends externallib_advanced_testcase {
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

        $context1 = context\course::instance($course1->id);
        $context2 = context\course::instance($course2->id);
        // The instances don't automatically exist. They are created when an attempt to enrol someone happens.
        try {
            $instance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'solaissits'], '*', MUST_EXIST);
            $this->fail('Exception expected as the enrolment method hasn\'t been added yet');
        } catch (moodle_exception $ex) {
            $this->assertInstanceOf('dml_missing_record_exception', $ex);
            $this->assertSame('invalidrecord', $ex->errorcode);
        }
        // Call the external function.
        enrol_users::execute([
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
        enrol_users::execute([
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
        enrol_users::execute([
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
        enrol_users::execute([
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
}
