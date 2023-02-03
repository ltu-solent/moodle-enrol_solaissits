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
 * Trait with some extra functions to reduce duplication of code.
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits;

use context_system;

trait helper_trait {

    /**
     * Enable SOL AIS-SITS plugin
     *
     * @return void
     */
    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['solaissits'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Disable SOL AIS-SITS plugin
     *
     * @return void
     */
    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['solaissits']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Enables plugin, creates ws user and role and unitleader and ee roles.
     *
     * @return array ['roles', 'users']
     */
    public function setup_enrol() {
        global $DB;
        // Plugin isn't automatically enabled.
        $this->enable_plugin();

        $wsuser = $this->getDataGenerator()->create_user();
        $systemcontext = context_system::instance();
        $this->setUser($wsuser);

        // Set the required capabilities by the external function.
        $wsroleid = $this->assignUserCapability('enrol/solaissits:enrol', $systemcontext->id);
        $this->assignUserCapability('enrol/solaissits:unenrol', $systemcontext, $wsroleid);
        $this->assignUserCapability('moodle/course:view', $systemcontext->id, $wsroleid);
        $this->assignUserCapability('moodle/role:assign', $systemcontext->id, $wsroleid);
        $this->assignUserCapability('moodle/course:viewparticipants', $systemcontext->id, $wsroleid);
        $this->assignUserCapability('moodle/user:viewdetails', $systemcontext->id, $wsroleid);
        set_role_contextlevels($wsroleid, [CONTEXT_SYSTEM, CONTEXT_COURSE]);
        $wsrole = $DB->get_record('role', ['id' => $wsroleid]);
        // Student role already exists, but create unitleader and externalexaminer.
        $unitleaderrole = $this->getDataGenerator()->create_role(['name' => 'Module leader', 'shortname' => 'unitleader']);
        $externalexaminerrole = $this->getDataGenerator()->create_role([
            'name' => 'External Examiner', 'shortname' => 'externalexaminer']);
        core_role_set_assign_allowed($wsroleid, 5); // Student.
        core_role_set_assign_allowed($wsroleid, $unitleaderrole);
        core_role_set_assign_allowed($wsroleid, $externalexaminerrole);

        return [
            'roles' => [
                'unitleader' => $unitleaderrole,
                'externalexaminer' => $externalexaminerrole,
                'ws' => $wsrole
            ],
            'users' => [
                'ws' => $wsuser
            ]
        ];
    }

    /**
     * Setup the course custom fields required for enrolments
     *
     * @return array [fieldgenerator, templateappliedfield, pagetypefield]
     */
    public function setup_customfields() {
        $fieldgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $fieldcat = $fieldgenerator->create_category(
            [
                'name' => 'Student Records System',
                'contextid' => context_system::instance()->id
            ]
        );
        $templateappliedfield = $fieldgenerator->create_field([
            'shortname' => 'templateapplied',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        $pagetypefield = $fieldgenerator->create_field([
            'shortname' => 'pagetype',
            'categoryid' => $fieldcat->get('id'),
            'type' => 'text'
        ]);
        return [
            'generator' => $fieldgenerator,
            'templateappliedfield' => $templateappliedfield,
            'pagetypefield' => $pagetypefield
        ];
    }

    /**
     * Set custom course fields to given values. Can only be used to set initial values.
     * Changes must be managed via the data_controller for the field.
     *
     * @param int $courseid
     * @param array $values Field name keys
     * @param array $fieldgenerator Returned from setup_customfields.
     * @return array Array of data_controllers
     */
    public function set_customfields($courseid, $values, $fieldgenerator) {
        $data = [];
        // This is the minimum required for enrolments to happen using this method.
        if (isset($values['templateapplied'])) {
            $data['templateapplied'] = $fieldgenerator['generator']->add_instance_data(
                $fieldgenerator['templateappliedfield'],
                $courseid,
                $values['templateapplied']);
        }
        if (isset($values['pagetype'])) {
            $data['pagetype'] = $fieldgenerator['generator']->add_instance_data(
                $fieldgenerator['pagetypefield'],
                $courseid,
                $values['pagetype']);
        }
        return $data;
    }
}
