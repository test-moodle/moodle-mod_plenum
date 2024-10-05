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
 * Backup steps for mod_plenum are defined here.
 *
 * @package     mod_plenum
 * @category    backup
 * @copyright   2023 Daniel Thies <dthies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete structure for backup, with file and id annotations.
 */
class backup_plenum_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure of the resulting xml file.
     *
     * @return backup_nested_element The structure wrapped by the common 'activity' element.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $attributes = ['id'];
        $finalelements = [
            'name',
            'timecreated',
            'timemodified',
            'completionmotions',
            'form',
            'grade',
            'intro',
            'introformat',
            'moderate',
        ];
        $root = new backup_nested_element('plenum', $attributes, $finalelements);

        $motions = new backup_nested_element('motions');

        $attributes = ['id'];
        $finalelements = [
            'plenum',
            'type',
            'parent',
            'plugindata',
            'groupid',
            'status',
            'timecreated',
            'timemodified',
            'usercreated',
            'usermodified',
        ];
        $motion = new backup_nested_element('motion', $attributes, $finalelements);

        $grades = new backup_nested_element('grades');
        $attributes = ['id'];
        $finalelements = [
            'itemnumber',
            'userid',
            'timecreated',
            'timemodified',
            'feedback',
            'feedbackformat',
            'grader',
            'grade',
        ];
        $grade = new backup_nested_element('grade', $attributes, $finalelements);

        // Build the tree with these elements with $root as the root of the backup tree.
        $root->add_child($motions);
        $motions->add_child($motion);
        $root->add_child($grades);
        $grades->add_child($grade);

        // Define elements for form subplugin settings.
        $this->add_subplugin_structure('plenumform', $root, true);

        // Define the source tables for the elements.
        $root->set_source_table('plenum', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $motion->set_source_table('plenum_motion', ['plenum' => backup::VAR_PARENTID], 'id ASC');
            $grade->set_source_table('plenum_grades', ['plenum' => backup::VAR_PARENTID], 'id ASC');
        }

        // Define id annotations.
        $motion->annotate_ids('user', 'usercreated');
        $motion->annotate_ids('user', 'usermodified');
        $motion->annotate_ids('group', 'groupid');

        // Define file annotations.
        $root->annotate_files('mod_plenum', 'intro', null); // This file area hasn't itemid.
        $motion->annotate_files('mod_plenum', 'attachments', 'id');

        return $this->prepare_activity_structure($root);
    }
}
