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

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__ . '/helper_trait.php');

/**
 * Test the main enrolment class
 */
class plugin_test extends advanced_testcase {
    use helper_trait;

    /**
     * Get user enrolments
     *
     * @covers \enrol_solaissits_plugin::get_enrolments_for
     * @return void
     */
    public function test_get_user_enrolments() {
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
}
