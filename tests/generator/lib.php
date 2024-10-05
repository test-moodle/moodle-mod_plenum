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

use mod_plenum\motion;

/**
 * Plenary meeting module data generator class
 *
 * @package    mod_plenum
 * @category   test
 * @copyright  2024 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_plenum_generator extends testing_module_generator {
    /**
     * Creates new plenum module instance.
     *
     * @param array|stdClass $record data for module being generated. Requires 'course' key
     *     (an id or the full object). Also can have any fields from add module form.
     * @param null|array $options general options for course module. Since 2.6 it is
     *     possible to omit this argument by merging options into $record
     * @return stdClass record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, ?array $options = null): stdClass {
        global $CFG;

        $record = (object)(array)$record;

        if (!isset($record->form)) {
            $record->form = 'basic';
        }
        $record->grade = $record->grade ?? 0;

        $instance  = parent::create_instance($record, (array)$options);

        return $instance;
    }

    /**
     * Create motion
     *
     * @param array $options
     * @return motion
     */
    public function create_motion($options = []): motion {
        global $USER;

        $record = new stdClass();
        $options = (object)$options;

        $record->plenum = get_coursemodule_from_id('plenum', $options->plenumid)->instance;
        $record->type = $options->type ?? 'open';
        $record->status = $options->status ?? motion::STATUS_DRAFT;
        $record->timecreated = $options->timecreated ?? time();
        $record->timemodified = $options->timecreated ?? $record->timecreated;
        $record->usercreated = $options->userid ?? $USER->id;
        $record->usermodified = $options->userid ?? $USER->id;
        $record->plugindata = json_encode($options->data ?? []);
        $record->groupid = $options->groupid ?? 0;

        $context = context_module::instance($options->plenumid);
        if ($pending = motion::immediate_pending($context)) {
            $record->parent = $pending->get('parent');
        }

        $motion = new motion(0, $record);

        $motion->create();

        return $motion;
    }
}
