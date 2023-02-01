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
$params = [
    'page' => optional_param('page', 0, PARAM_INT),
    'tdir' => optional_param('tdir', null, PARAM_INT),
    'thide' => optional_param('thide', null, PARAM_ALPHANUMEXT),
    'tifirst' => optional_param('tifirst', '', PARAM_RAW),
    'tilast' => optional_param('tilast', null, PARAM_RAW),
    'treset' => optional_param('treset', null, PARAM_BOOL),
    'tshow' => optional_param('tshow', null, PARAM_ALPHANUMEXT),
    'tsort' => optional_param('tsort', null, PARAM_ALPHANUMEXT),

];
$pageurl = new moodle_url('/enrol/solaissits/queueditems.php', $params);
admin_externalpage_setup('enrol_solaissits_queueditems');

$PAGE->set_context(context_system::instance());

$download = optional_param('download', '', PARAM_ALPHA);
$PAGE->set_title(get_string('queueditemsheading', 'enrol_solaissits'));
$PAGE->set_heading(get_string('queueditemsheading', 'enrol_solaissits'));

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
