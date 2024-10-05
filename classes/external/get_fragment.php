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
 * External function for getting Janus gateway room information
 *
 * @package    mod_plenum
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_fragment extends external_api {
    /**
     * Get parameter definition for get room
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Context id for Plenary meeting activity module'),
                'fragment' => new external_value(PARAM_TEXT, 'Name of fragment to server'),
                'id' => new external_value(PARAM_INT, 'Optional id', VALUE_DEFAULT, 0),
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
    public static function execute($contextid, $fragment, $id): array {
        global $OUTPUT, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'fragment' => $fragment,
            'id' => $id,
        ]);

        $context = context::instance_by_id($contextid);
        self::validate_context($context);

        $output = $PAGE->get_renderer('mod_plenum');
        $html = '';
        $js = '';
        if ($id) {
            $motion = new motion($id);
            $type = $motion->get('type');
            $classname = "plenumtype_$type\\type";
            $instance = new $classname($motion, $context);
        }

        switch ($fragment) {
            case 'confirmadopt':
                require_capability('mod/plenum:preside', $context);
                $instance->change_status(motion::STATUS_ADOPT);
                break;
            case 'confirmallow':
                require_capability('mod/plenum:preside', $context);
                $instance->change_status(motion::STATUS_PENDING);
                break;
            case 'confirmdecline':
                require_capability('mod/plenum:preside', $context);
                $instance->change_status(motion::STATUS_DECLINE);
                break;
            case 'confirmdeny':
                $instance->change_status(motion::STATUS_CLOSED);
                break;
            case 'adopt':
            case 'allow':
            case 'decline':
            case 'deny':
                $motion = new motion($id);

                $type = $motion->get('type');
                $user = core_user::get_user($motion->get('usercreated'));

                $data = (array)$motion->to_record() + [
                    'action' => $fragment,
                    'fullname' => fullname($user),
                    'user' => $user,
                ];
                $html = $OUTPUT->render_from_template('mod_plenum/mobile_change_status', $data);
                $js = '';
                break;
            case 'motion':
                $motion = new motion($id);

                $type = $motion->get('type');

                $classname = "plenumtype_$type\\output\\main";

                $mainview = new $classname($context, $motion);

                $data = $mainview->export_for_template($output);
                $html = $output->render_from_template("plenumtype_$type/mobile", $data);
                $js = '';

                $event = \mod_plenum\event\motion_viewed::create([
                    'objectid' => $motion->get('id'),
                    'context' => $context,
                ]);
                $event->trigger();
                break;
            case 'content':
                $motions = new motions($context);
                $data = $motions->export_for_template($OUTPUT);

                $html = $OUTPUT->render_from_template('mod_plenum/mobile_motions', $data);
                $js = '';
                break;
        }

        return [
            'html' => $html,
            'js' => $js,
        ];
    }

    /**
     * Get return definition
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Fragment content'),
            'js' => new external_value(PARAM_RAW, 'Fragment javascript'),
        ]);
    }
}
