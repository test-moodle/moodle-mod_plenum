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

namespace plenumform_deft\external;

use mod_plenum\motion;
use plenumform_deft\socket;
use block_deft\janus;
use block_deft\venue_manager;
use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_user;
use plenumform_deft\output\user_picture;
use mod_plenum\output\motions;
use plenumform_deft\janus_room;

/**
 * External updating meeting information
 *
 * @package    plenumform_deft
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
        global $DB, $OUTPUT, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);

        $room = new janus_room($cm->instance);

        $motions = new motions($context);

        $pending = motion::immediate_pending($context);
        $floor = empty($pending) ? 0 : $pending->get('usercreated');

        $hasfloor = !empty($pending) && ($floor == $USER->id);
        $canshare = has_capability('mod/plenum:meet', $context)
            &&  has_capability('plenumform/deft:sharevideo', $context)
            && (
                has_capability('mod/plenum:preside', $context)
                || $hasfloor
            );
        $issharingvideo = $canshare && $DB->get_record('plenumform_deft_peer', [
            'plenum' => $cm->instance,
            'status' => 0,
            'usermodified' => $USER->id,
        ]);
        $controls = $OUTPUT->render_from_template('plenumform_deft/controls', [
            'sharevideo' => $canshare,
            'issharingvideo' => $issharingvideo,
            'toggles' => [
                [
                    'id' => 'stopvideo',
                    'checked' => $issharingvideo,
                    'dataattributes' => [
                        ['name' => 'contextid', 'value' => $context->id],
                        ['name' => 'action', 'value' => $issharingvideo ? 'unpublish' : 'publish'],
                    ],
                    'disabled' => !$canshare,
                    'title' => get_string('stopvideo', 'plenumform_deft'),
                    'label' => get_string('stopvideo', 'plenumform_deft'),
                ],
                [
                    'id' => 'enableaudio',
                    'checked' => false,
                    'dataattributes' => [
                        ['name' => 'contextid', 'value' => $context->id],
                        ['name' => 'action', 'value' => 'enableaudio'],
                    ],
                    'title' => get_string('enableaudio', 'plenumform_deft'),
                    'label' => get_string('enableaudio', 'plenumform_deft'),
                ],
                [
                    'id' => 'disableaudio',
                    'checked' => true,
                    'dataattributes' => [
                        ['name' => 'contextid', 'value' => $context->id],
                        ['name' => 'action', 'value' => 'disableaudio'],
                    ],
                    'extraclasses' => 'hidden',
                    'title' => get_string('disableaudio', 'plenumform_deft'),
                    'label' => get_string('disableaudio', 'plenumform_deft'),
                ],
            ],
        ]);

        $js = '';

        return [
            'controls' => $controls,
            'userinfo' => $room->userinfo($OUTPUT),
            'motions' => $OUTPUT->render($motions),
            'javascript' => $js,
        ];
    }

    /**
     * Get return definition
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'controls' => new external_value(PARAM_RAW, 'HTML for video controls'),
            'motions' => new external_value(PARAM_RAW, 'HTML for motions'),
            'javascript' => new external_value(PARAM_RAW, 'Javascript to be executed after loading'),
            'userinfo' => new external_multiple_structure(
                new external_single_structure([
                    'slot' => new external_value(PARAM_ALPHA, 'Video slot name'),
                    'id' => new external_value(PARAM_INT, 'Id for the feed'),
                    'name' => new external_value(PARAM_TEXT, 'User display name'),
                    'pictureurl' => new external_value(PARAM_URL, 'User picture'),
                ])
            ),
        ]);
    }
}
