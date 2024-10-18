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
 * Role action setting
 *
 * @package   enrol_solaissits
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2023 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_solaissits\admin;

use admin_setting;

/**
 * Custom setting for defining unenrol actions for given roles.
 */
class role_actions_setting extends admin_setting {

    /**
     * Constructor
     *
     * @param object $role Role record
     */
    public function __construct($role) {
        $default = json_encode([
            'course' => ENROL_EXT_REMOVED_UNENROL,
            'module' => ENROL_EXT_REMOVED_UNENROL,
        ]);
        parent::__construct('enrol_solaissits/roleactions_' . $role->id, $role->localname, '', $default);
    }

    /**
     * Get setting from db
     *
     * @return object
     */
    public function get_setting() {
        $setting = json_decode($this->config_read($this->name));
        if (empty($setting)) {
            return null;
        }
        return $setting;
    }

    /**
     * Write setting to DB
     *
     * @param array $data
     * @return string
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return '';
        }
        $setting = json_encode($data);
        $result = $this->config_write($this->name, $setting);
        return ($result ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Read config for given config name
     *
     * @param string $name
     * @return mixed
     */
    public function config_read($name) {
        $value = parent::config_read($name);
        if (is_null($value)) {
            // In other settings NULL means we have to ask user for new value,
            // here we just ignore missing role mappings.
            $value = '';
        }
        return $value;
    }

    /**
     * Used to write config pair
     *
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function config_write($name, $value) {
        if ($value === '') {
            // We do not want empty values in config table,
            // delete it instead.
            $value = null;
        }
        return parent::config_write($name, $value);
    }

    /**
     * Returns the html to output the setting form
     *
     * @param object $data
     * @param string $query
     * @return string html
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        if (empty($data)) {
            $data = json_decode($default);
        }
        $defaultinfo = null;
        $options = [
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ];
        $context = (object)[
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'modules' => array_map(function($value, $name) use ($data) {
                return [
                    'value' => $value,
                    'name' => $name,
                    'selected' => $value == $data->module,
                ];
            }, array_keys($options), array_values($options)),
            'courses' => array_map(function($value, $name) use ($data) {
                return [
                    'value' => $value,
                    'name' => $name,
                    'selected' => $value == $data->course,
                ];
            }, array_keys($options), array_values($options)),
        ];

        $element = $OUTPUT->render_from_template('enrol_solaissits/setting_role_actions', $context);
        return format_admin_setting($this, $this->visiblename, $element, $this->description,
            $this->get_id(), '', $defaultinfo, $query);
    }
}
