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
 * External lib function declarations
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'enrol_solaissits_enrol_users' => array(
        'classname'   => 'enrol_solaissits_external',
        'methodname'  => 'enrol_users',
        'classpath'   => 'enrol/solaissits/externallib.php',
        'description' => 'Enrol users via the AIS-SITS interface',
        'capabilities' => 'enrol/solaissits:enrol',
        'type'        => 'write',
    ),
    'enrol_solaissits_unenrol_users' => array(
        'classname'   => 'enrol_solaissits_external',
        'methodname'  => 'unenrol_users',
        'classpath'   => 'enrol/solaissits/externallib.php',
        'description' => 'Unenrol users via the AIS-SITS interface',
        'capabilities' => 'enrol/solaissits:unenrol',
        'type'        => 'write',
    ),
    'enrol_solaissits_get_enrolments' => [
        'classname' => 'enrol_solaissits_external',
        'methodname' => 'get_enrolments',
        'classpath' => 'enrol/solaissits/externallib.php',
        'description' => 'Get enrolments for given user on given course',
        'capabilities' => 'enrol/solaissits/enrol',
        'type' => 'read'
    ]
];
