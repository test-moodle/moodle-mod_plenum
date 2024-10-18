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

namespace plenumform_jitsi\external;

use cache;
use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;
use mod_plenum\output\motions;
use mod_plenum\motion;

/**
 * External function for updating hand raise in Jitsi meeting form
 *
 * @package    plenumform_jitsi
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raise_hand extends external_api {
    /**
     * Get parameter definition for raise hand
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Module context id'),
                'raisehand' => new external_value(PARAM_BOOL, 'Whether hand is raised'),
            ]
        );
    }

    /**
     * Raise
     *
     * @param int $contextid Module context id
     * @param bool $raisehand Whether hand is raised
     * @return array
     */
    public static function execute(int $contextid, bool $raisehand): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'raisehand' => $raisehand,
        ]);

        $context = context::instance_by_id($contextid);
        [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'plenum');
        self::validate_context($context);

        $params = [
            'context' => $context,
            'objectid' => $cm->instance,
            'other' => [
                'raisehand' => $raisehand,
            ],
        ];
        $event = \plenumform_jitsi\event\raise_hand_updated::create($params);

        $event->trigger();

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
            'status' => new external_value(PARAM_BOOL, 'Whether successful'),
        ]);
    }
}
