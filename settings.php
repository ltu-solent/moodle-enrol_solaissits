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
 * Settings for SOL AIS-SITS integration
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('enrolments', new admin_category('enrolsolaissitsfolder', new lang_string('pluginname', 'enrol_solaissits'),
    $this->is_enabled() === false));
$ADMIN->add('enrolsolaissitsfolder', new admin_externalpage(
    'enrol_solaissits_queueditems',
    get_string('queueditems', 'enrol_solaissits'),
    new moodle_url('/enrol/solaissits/queueditems.php')));
// Prevent duplicate admin settings page error.
$settings = null;
if ($ADMIN->fulltree) {
    $settings_page = new admin_settingpage($section, new lang_string('settings', 'enrol_solaissits'));
    $ADMIN->add('enrolsolaissitsfolder', $settings_page);
    if (!during_initial_install()) {
        $settings_page->add(new admin_setting_heading('enrol_solaissits_roleactions',
            new lang_string('roleactions', 'enrol_solaissits'),
            new lang_string('roleactions_desc', 'enrol_solaissits')));
        $roles = \enrol_solaissits\helper::get_roles();
        foreach ($roles as $role) {
            $settings_page->add(new \enrol_solaissits\admin\role_actions_setting($role));
        }
        unset($roles);
    }
}
