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

use block_deft\janus;
use cache;
use context;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use plenumform_deft\janus_room;
use mod_plenum\motion;
use mod_plenum\plugininfo\plenum;
use stdClass;

/**
 * External function to offer feed to venue
 *
 * @package    plenumform_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_feed extends external_api {
    /**
     * Get parameter definition for raise hand
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Peer id for user session'),
                'publish' => new external_value(PARAM_BOOL, 'Whether to publish or not', VALUE_DEFAULT, true),
                'room' => new external_value(PARAM_INT, 'Room id being joined'),
            ]
        );
    }

    /**
     * Publish feed
     *
     * @param string $id Venue peer id
     * @param bool $publish Whether to publish
     * @param int $room Room id being joined
     * @return array
     */
    public static function execute($id, $publish, $room): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'publish' => $publish,
            'room' => $room,
        ]);

        $record = $DB->get_record('block_deft_room', [
            'roomid' => $room,
            'component' => 'plenumform_deft',
        ]);

        $plenum = $DB->get_field('plenumform_deft_room', 'plenum', ['id' => $record->itemid]);
        [$course, $cm] = get_course_and_cm_from_instance($plenum, 'plenum');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/plenum:meet', $context);

        $data = json_decode($record->data ?: '{}');
        if (
            $publish && (($id == ($data->feed ?? 0)) || ($id == $data->floor ?? 0))
        ) {
            return [
                'status' => false,
            ];
        }

        if (
            !$publish && ($peer = $DB->get_record_select(
                'plenumform_deft_peer',
                "type = 'venue' AND usermodified = :userid AND status = 0",
                ['userid' => $USER->id]
            ))
        ) {
            $peer->timemodified = time();
            $peer->status = 1;
            $DB->update_record('plenumform_deft_peer', $peer);
            if ($data->feed && ($data->feed == $peer->id)) {
                $data->feed = 0;
            }
            if ($data->floor && ($data->floor == $peer->id)) {
                $data->floor = 0;
            }

            $kick = $peer->id;
        } else if (
            $publish && $peer = $DB->get_record('plenumform_deft_peer', [
            'id' => $id,
            'status' => 0,
            ])
        ) {
            if (has_capability('mod/plenum:preside', $context)) {
                $kick = $data->feed ?? 0;
                $data->feed = $id;
            } else {
                $immediate = motion::immediate_pending($context);
                if ($immediate->get('usercreated') == $USER->id) {
                    $kick = $data->floor ?? 0;
                    $data->floor = $id;
                }
            }
        } else {
            return [
                'status' => false,
            ];
        }

        $record->timemodified = time();
        $record->data = json_encode($data);
        $DB->update_record('block_deft_room', $record);

        if (!empty($kick)) {
            $janusroom = new janus_room($cm->instance);

            $message = [
                'request' => 'kick',
                'secret' => $janusroom->get_secret(),
                'room' => $room,
                'id' => $kick,
            ];

            $janusroom->videoroom_send($message);
        }

        $hook = new \mod_plenum\hook\after_motion_updated($context, $cm);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        $params = [
            'context' => $context,
            'objectid' => $cm->instance,
            'other' => [
                'motion' => $peer->motion,
            ],
        ];

        if ($publish) {
            $event = \plenumform_deft\event\video_started::create($params);
        } else {
            $event = \plenumform_deft\event\video_ended::create($params);
        }
        $event->trigger();

        return [
            'status' => true,
        ];
    }

    /**
     * Get return definition for hand_raise
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether changed'),
        ]);
    }
}
