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
 * Behat steps for enrol solaissits
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Behat steps for local solsits
 */
class behat_enrol_solaissits extends behat_base {

    /**
     * Create new sits queued enrolment record
     *
     * @Given /^the following sits queued enrolments exist:$/
     * @param TableNode $data
     * @return void
     */
    public function the_following_sits_queued_enrolments_exist(TableNode $data) {
        global $DB;
        $sitsenrolments = $data->getColumnsHash();
        foreach ($sitsenrolments as $enrolment) {
            if (!isset($enrolment['course'])) {
                throw new Exception('The course shortname must be provided in the course field');
            }
            $course = $DB->get_record('course', ['shortname' => $enrolment['course']], '*', MUST_EXIST);
            $enrolment['courseid'] = $course->id;
            unset($enrolment['course']);

            if (!isset($enrolment['role'])) {
                throw new Exception('The role must be specified');
            }
            $role = $DB->get_record('role', ['shortname' => $enrolment['role']], '*', MUST_EXIST);
            $enrolment['roleid'] = $role->id;
            unset($enrolment['role']);

            if (!isset($enrolment['username'])) {
                throw new Exception('Username must be specified');
            }
            $user = $DB->get_record('user', ['username' => $enrolment['username']], '*', MUST_EXIST);
            $enrolment['userid'] = $user->id;
            unset($enrolment['username']);
            /** @var \enrol_solaissits_generator $ssdg */
            $ssdg = behat_util::get_data_generator()->get_plugin_generator('enrol_solaissits');
            $ssdg->create_queued_item($enrolment);
        }
    }
}
