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
final class get_enrolments_test extends externallib_advanced_testcase {
    use helper_trait;

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
        enrol_users::execute([
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

        $enrolments = get_enrolments::execute('ABC101', 'Student1');
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

        $enrolments = get_enrolments::execute('ABC102', 'Student1');
        $this->assertCount(0, $enrolments->enrolments);
        $this->assertCount(1, $enrolments->queueditems);
        $this->assertEquals(5, $enrolments->queueditems[0]->roleid);
        $this->assertCount(1, $enrolments->queueditems[0]->groups);
        $groups = groups_get_all_groups($course2->id);
        // Group count is zero because enrolments have been queued but not actioned.
        $this->assertCount(0, $groups);
    }
}
