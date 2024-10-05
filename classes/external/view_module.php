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

namespace mod_plenum\external;

use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_user;
use mod_plenum\motion;
use mod_plenum\output\motions;

/**
 * External function for recording course module view
 *
 * @package    mod_plenum
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_module extends external_api {
    /**
     * Get parameter definition for get room
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Context id for Plenary meeting activity module'),
            ]
        );
    }

    /**
     * Get fragment
     *
     * @param int $contextid Plenary meeting module context id
     * @param string $fragment Fragment identifier
     * @param int $id Optional id param for fragment
     * @return array
     */
    public static function execute($contextid): array {
        global $DB, $OUTPUT, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $course = get_course($context->get_course_context()->instanceid);

        $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);

        $event = \mod_plenum\event\course_module_viewed::create([
            'objectid' => $moduleinstance->id,
            'context' => $context,
        ]);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('plenum', $moduleinstance);
        $event->trigger();

        // Completion.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        return [
            'status' => true,
        ];
    }

    /**
     * Get return definition
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
        ]);
    }
}
