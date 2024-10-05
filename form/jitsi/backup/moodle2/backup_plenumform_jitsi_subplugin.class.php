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
 * Defines backup_plenumform_jitsi_subplugin class
 *
 * @package     plenumform_jitsi
 * @copyright   2024 Daniel Thies
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines backup_plenumform_jitsi_subplugin class
 *
 * Provides the step to perform back up of sublugin data
 */
class backup_plenumform_jitsi_subplugin extends backup_subplugin {
    /**
     * Defined suplugin structure step
     */
    protected function define_plenum_subplugin_structure() {

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subplugintablesettings = new backup_nested_element('jitsi_settings', null, [
            'room',
        ]);

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subplugintablesettings);

        // Set source to populate the data.
        $subplugintablesettings->set_source_table('plenumform_jitsi', ['plenum' => backup::VAR_ACTIVITYID]);

        return $subplugin;
    }
}
