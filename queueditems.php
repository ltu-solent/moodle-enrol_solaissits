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
 * View Queued items
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('enrol/solaissits:manage', context_system::instance());
admin_externalpage_setup('enrol_solaissits_queueditems');
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/enrol/solaissits/queueditems.php');
$PAGE->set_pagelayout('report');
$download = optional_param('download', '', PARAM_ALPHA);
$PAGE->set_title(get_string('queueditemsheading', 'enrol_solaissits'));
$PAGE->set_heading(get_string('queueditemsheading', 'enrol_solaissits'));
// $PAGE->navbar->add(get_string('enrolments', 'enrol'), new moodle_url('/admin/category.php?category=enrolments'));
// $PAGE->navbar->add(get_string('pluginname', 'enrol_solaissits'), new moodle_url('/admin/settings.php?section=enrolsettingssolaissits'));
// $PAGE->navbar->add(get_string('queueditemsheading', 'enrol_solaissits'), new moodle_url('/enrol/solaissits/queueditems.php'));

$table = new \enrol_solaissits\tables\queued_items('solaissitsqueueditems');

$table->is_downloading($download, 'solaissitsqueueditems', get_string('queueditems', 'enrol_solaissits'));

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data.
    // Print the page header.
    echo $OUTPUT->header();
}

$table->out(100, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
