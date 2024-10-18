<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * All the steps to restore mod_plenum are defined here.
 *
 * @package     mod_plenum
 * @category    backup
 * @copyright   2023 Daniel Thies <dthies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure step to restore one mod_plenum activity.
 */
class restore_plenum_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the structure to be restored.
     *
     * @return restore_path_element[].
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $plenum = new restore_path_element('plenum', '/activity/plenum');
        $paths[] = $plenum;

        if ($userinfo) {
            $paths[] = new restore_path_element('motion', '/activity/plenum/motions/motion');
            $paths[] = new restore_path_element('plenum_grade', '/activity/plenum/grades/grade');
        }

        // A chance for subplugins to set up their data.
        $this->add_subplugin_structure('plenumform', $plenum);

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes the plenum restore data.
     *
     * @param array $data Parsed element data.
     */
    protected function process_plenum($data) {
        global $DB;

        $data = (object)$data;

        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('plenum', $data);
        $this->apply_activity_instance($newitemid);

        return;
    }

    /**
     * Processes the motion restore data.
     *
     * @param array $data Parsed element data.
     */
    protected function process_motion($data) {
        global $DB;

        $data = (object)$data;

        $oldid = $data->id;
        $data->plenum = $this->get_new_parentid('plenum');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usercreated = $this->get_mappingid('user', $data->usercreated);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('plenum_motion', $data);
        $this->set_mapping('plenum_motion', $oldid, $newitemid, true);

        return;
    }

    /**
     * Processes the grade restore data.
     *
     * @param array $data Parsed element data.
     */
    protected function process_plenum_grade($data) {
        global $DB;

        $data = (object)$data;

        $oldid = $data->id;
        $data->plenum = $this->get_new_parentid('plenum');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->grader = $this->get_mappingid('user', $data->grader);

        $newitemid = $DB->insert_record('plenum_grades', $data);
        $this->set_mapping('grade', $oldid, $newitemid, true);
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('plenum'), $oldid, $newitemid);

        return;
    }

    /**
     * Defines post-execution actions.
     */
    protected function after_execute() {
        global $DB;

        foreach ($DB->get_records('plenum_motion', ['plenum' => $this->get_new_parentid('plenum')]) as $record) {
            if (!empty($record->parent)) {
                $record->parent = $this->get_mappingid('plenum_motion', $record->parent);
                $DB->update_record('plenum_motion', $record);
            }
        }

        $this->add_related_files('mod_plenum', 'intro', null);
        $this->add_related_files('mod_plenum', 'attachments', 'plenum_motion');
        $plugins = \mod_plenum\plugininfo\plenumform::get_enabled_plugins();

        foreach ($plugins as $plugin) {
            $this->add_related_files('mod_plenum', 'speaker', "plenumform_$plugin");
        }

        return;
    }
}
