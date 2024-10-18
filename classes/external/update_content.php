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

use mod_plenum\motion;
use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_plenum\output\motions;

/**
 * External updating meeting information
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_content extends external_api {
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
     * Get update meeting information
     *
     * @param int $contextid Plenary meeting module context id
     * @return array
     */
    public static function execute(int $contextid): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        $motions = new motions($context);

        return [
            'motions' => $OUTPUT->render($motions),
            'javascript' => '',
        ];
    }

    /**
     * Get return definition
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'motions' => new external_value(PARAM_RAW, 'HTML for motions'),
            'javascript' => new external_value(PARAM_RAW, 'Javascript to be executed after loading'),
        ]);
    }
}
