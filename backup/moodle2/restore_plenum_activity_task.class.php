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

defined('MOODLE_INTERNAL') || die();

/**
 * The task that provides a complete restore of mod_plenum is defined here.
 *
 * @package     mod_plenum
 * @category    backup
 * @copyright   2023 Daniel Thies <dthies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// More information about the backup process: {@link https://docs.moodle.org/dev/Backup_API}.
// More information about the restore process: {@link https://docs.moodle.org/dev/Restore_API}.

require_once($CFG->dirroot . '//mod/plenum/backup/moodle2/restore_plenum_stepslib.php');

/**
 * Restore task for mod_plenum.
 *
 * @package     mod_plenum
 */
class restore_plenum_activity_task extends restore_activity_task {
    /**
     * Defines particular settings that this activity can have.
     */
    protected function define_my_settings() {
        return;
    }

    /**
     * Defines particular steps that this activity can have.
     *
     * @return base_step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_plenum_activity_structure_step('plenum_structure', 'plenum.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     *
     * @return array.
     */
    public static function define_decode_contents() {
        $contents = [];

        // Define the contents.
        $contents[] = new restore_decode_content('plenum', ['intro'], 'plenum');

        return $contents;
    }

    /**
     * Defines the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array.
     */
    public static function define_decode_rules() {
        $rules = [];

        // Define the rules.
        $rules[] = new restore_decode_rule(
            'PLENUMVIEWBYID',
            '/mod/plenum/view.php?id=$1',
            'course_module'
        );
        $rules[] = new restore_decode_rule(
            'PLENUMINDEX',
            '/mod/plenum/index.php?id=$1',
            'course_module'
        );

        return $rules;
    }

    /**
     * Defines the restore log rules that will be applied by the
     * {@see restore_logs_processor} when restoring mod_plenum logs. It
     * must return one array of {@see restore_log_rule} objects.
     *
     * @return array.
     */
    public static function define_restore_log_rules() {
        $rules = [];

        // Define the rules.
        $rules[] = new restore_log_rule('plenum', 'add', 'view.php?id={course_module}', '{plenum}');
        $rules[] = new restore_log_rule('plenum', 'update', 'view.php?id={course_module}', '{plenum}');
        $rules[] = new restore_log_rule('plenum', 'view', 'view.php?id={course_module}', '{plenum}');

        return $rules;
    }
}
