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

/**
 * Mobile output class for Plenary meeting
 *
 * @package     plenumform_deft
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumform_deft\output;

use stdClass;
use context_module;
use plenumform_deft\janus_room;
use plenumform_deft\socket;
use mod_plenum\motion;

/**
 * Mobile output class for Plenary meeting Deft integration
 *
 * @package     plenumform_deft
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns motion mobile content if any
     * @param array $args Arguments from tool_mobile_get_content WS
     */
    public static function mobile_mod_load($args) {
    }

    /**
     * Returns content to int js
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function init($args) {
        global $CFG;

        $js = file_get_contents("$CFG->dirroot/mod/plenum/form/deft/mobile/venue.js") . "
        " . file_get_contents("$CFG->dirroot/blocks/deft/mobile/adapter.js") . "
        " . file_get_contents("$CFG->dirroot/blocks/deft/mobile/janus.js");

        return [
            'templates' => [
            ],
            'javascript' => $js,
        ];
    }

    /**
     * Returns the Plenary meeting course view for the mobile app.
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     * @throws \required_capability_exception
     * @throws \coding_exception
     * @throws \require_login_exception
     * @throws \moodle_exception
     */
    public static function mobile_mod_view($args) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $args = (object) $args;

        $context = context_module::instance($args->cmid);
        $cm = get_coursemodule_from_id('plenum', $args->cmid);

        // Capabilities check.
        require_login($args->courseid, false, $cm, true, true);

        require_capability('mod/plenum:view', $context);

        $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);

        if (groups_get_activity_groupmode($cm)) {
            $groupid = $args->group ?? array_keys(groups_get_activity_allowed_groups($cm))[0] ?? 0;
        } else {
            $groupid = null;
        }

        $room = new janus_room($moduleinstance->id, $groupid);

        $data = [
            'instance' => $moduleinstance,
            'contextid' => $context->id,
            'slots' => [
                [
                    'slot' => 'chair',
                    'posterurl' => $OUTPUT->image_url('chair-solid', 'plenumform_deft'),
                ],
                [
                    'slot' => 'floor',
                    'posterurl' => $OUTPUT->image_url('microphone-solid', 'plenumform_deft'),
                ],
            ],
        ];
        $socket = new socket($context, (int)$groupid);

        $js = file_get_contents("$CFG->dirroot/mod/plenum/form/deft/mobile/motions.js");

        $roomdata = $room->room_data();
        $feed = $roomdata['feed'] ?? 0;
        $floor = $roomdata['floor'] ?? 0;
        $roomid = $room->get_roomid();
        $server = $room->get_server();
        $iceservers = json_encode($socket->ice_servers());
        $js .= ' console.log(this);';
        $js .= "
            const DeftVenue = this.Plenum.Deft;
            this.Plenum.Deft.roomid = $roomid;
            this.Plenum.Deft.contextid = $context->id;
            setTimeout(() => {
                if (DeftVenue.janus) {
                    if (
                        DeftVenue.sources
                        && (DeftVenue.sources.chair == $feed)
                        && (DeftVenue.sources.floor == $floor)
                    ) {
                        return;
                    }
                    DeftVenue.subscribeTo({
                        chair: $feed,
                        floor: $floor
                    });
                    return;
                }
                DeftVenue.webrtcUp = false;
                this.Janus.init({
                    debug: 'none',
                    callback: () => {
                        DeftVenue.janus = new this.Janus({
                            server: '$server',
                            iceServers: {$iceservers},
                            success: () => {
                                DeftVenue.subscribeTo({
                                    chair: $feed,
                                    floor: $floor
                                });
                            },
                            error: error => {
                                DeftVenue.janus = null;
                                DeftVenue.subscriptions = {};
                            }
                        });
                    },
                    error: error => {
                        DeftVenue.janus = null;
                        DeftVenue.subscriptions = {};
                    }
                });
            });";

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('plenumform_deft/mobile_main', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'contextid' => $context->id,
                'throttle' => get_config('block_deft', 'throttle'),
                'token' => $socket->get_token(),
            ],
        ];
    }
}
