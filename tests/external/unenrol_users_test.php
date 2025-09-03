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
final class unenrol_users_test extends externallib_advanced_testcase {
    use helper_trait;

    /**
     * Test for unerolling users.
     * @covers \enrol_solaissits_plugin::unenrol_users()
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_unenrol_users(): void {
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
        $this->set_customfields($course->id, ['templateapplied' => 1, 'pagetype' => 'module'], $fieldgenerator);

        // Create a student and enrol them into the course.
        $student = $this->getDataGenerator()->create_user(['idnumber' => 'Student1']);
        $enrol->enrol_user($enrolinstance, $student->id);
        $this->assertTrue(is_enrolled($coursecontext, $student));
        // Call the web service to unenrol.
        unenrol_users::execute([
            [
                'useridnumber' => $student->idnumber,
                'courseidnumber' => $course->idnumber,
                'roleshortname' => 'student',
            ],
        ]);
        $this->assertFalse(is_enrolled($coursecontext, $student));
    }
}
