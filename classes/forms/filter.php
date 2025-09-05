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
 * Filter form for search for user logs
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits\forms;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

use core\context;
use core\user;
use moodleform;

/**
 * Filter form for searching for users over a time period.
 */
class filter extends moodleform {
    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        $mform =& $this->_form;

        $options = [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'noselectionstring' => get_string('selectuser', 'enrol_solaissits'),
            'valuehtmlcallback' => function ($userid) {
                global $OUTPUT;
                $context = context\system::instance();
                $fields = \core_user\fields::for_name()->with_identity($context, false);
                $record = user::get_user($userid, 'id' . $fields->get_sql()->selects, MUST_EXIST);

                $user = (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];

                foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object)[
                        'name' => $extrafield,
                        'value' => s($record->$extrafield),
                    ];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
        $mform->addElement('autocomplete', 'userid', get_string('users'), [], $options)->setHiddenLabel(true);
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('selectcourse', 'enrol_solaissits'),
        ];
        $mform->addElement('course', 'courseid', get_string('course'), $options)->setHiddenLabel(true);

        $mform->addElement('submit', 'submit', get_string('filterqueuedenrolments', 'enrol_solaissits'));
    }

    /**
     * Add display buttons including one each for each type of display
     *
     * @return void
     */
    private function add_display_buttons() {
        $mform =& $this->_form;
        $mform->registerNoSubmitButton('resetfilters');
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'filter', get_string('filterqueuedenrolments', 'enrol_solaissits'));
        $buttonarray[] = &$mform->createElement(
            'submit',
            'filterreset',
            get_string('reset'),
            null,
            null,
            ['customclassoverride' => 'btn-link ms-1']
        );
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false)->setHiddenLabel(true);
        $mform->closeHeaderBefore('buttonar');
    }
}
