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
 * Janus room handler
 *
 * @package    plenumform_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumform_deft;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

use cache;
use core_user;
use context;
use context_module;
use block_deft\janus;
use block_deft\janus_room as janus_room_base;
use moodle_exception;
use mod_plenum\hook\before_motion_deleted;
use mod_plenum\motion;
use renderer_base;
use stdClass;
use plenumform_deft\output\user_picture;

/**
 * Janus room handler
 *
 * @package    plenumform_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class janus_room extends janus_room_base {
    /**
     * @var Item id to use
     */
    protected int $itemid = 0;

    /**
     * @var Plugin component using room
     */
    protected string $component = 'plenumform_deft';

    /**
     * @var Module context
     */
    protected ?context_module $context = null;

    /**
     * Constructor
     *
     * @param int $id Instance id
     * @param ?int $groupid Group id
     */
    public function __construct(int $id, $groupid = null) {
        global $DB, $USER;

        if (!get_config('block_deft', 'enablebridge')) {
            return;
        }

        $this->session = new janus();

        $cm = get_coursemodule_from_instance('plenum', $id);
        $this->context = context_module::instance($cm->id);
        $groupid = $groupid ?? groups_get_activity_group($cm);

        if (
            $room = $DB->get_record('plenumform_deft_room', [
               'groupid' => $groupid,
               'plenum' => $id,
            ])
        ) {
            $this->itemid = $room->id;
        } else {
            $this->itemid = $DB->insert_record('plenumform_deft_room', [
                'groupid' => $groupid,
                'plenum' => $id,
            ]);
        }

        if (
            !$record = $DB->get_record('block_deft_room', [
                'itemid' => $this->itemid,
                'component' => $this->component,
            ])
        ) {
            $records = $DB->get_records('block_deft_room', ['itemid' => null]);
            if ($record = reset($records)) {
                $record->itemid = $this->itemid;
                $record->usermodified = $USER->id;
                $record->timemodified = time();
                $record->component = $this->component;
                $DB->update_record('block_deft_room', $record);
            } else {
                $this->create_room();
            }
        }

        $this->record = $record;

        $this->roomid = $record->roomid ?? 0;
        $this->secret = $record->secret ?? '';
        $this->server = $record->server ?? '';

        $this->init_room();
    }

    /**
     * Check room availabity and create if necessary
     */
    protected function init_room() {
        $exists = [
            'request' => 'exists',
            'room' => $this->roomid,
        ];

        $response = $this->videoroom_send($exists);
        if (!$response->plugindata->data->exists) {
            return $this->create_room();
        }

        $this->set_token();
    }

    /**
     * JSON data stored by plugin
     *
     * @return string
     */
    public function get_data() {
        return $this->record->data ?: '{}';
    }

    /**
     * Store room data
     *
     * @param array|stdClass $data Data to store
     * @return bool
     */
    public function set_data(array|stdClass $data): bool {
        global $DB;

        return $DB->set_field(
            'block_deft_room',
            'data',
            json_encode($data),
            ['id' => $this->get_id()]
        );
    }

    /**
     * Get item id for room
     *
     * @return int
     */
    public function get_itemid(): int {
        return $this->itemid;
    }

    /**
     * Get record id for room
     *
     * @return int
     */
    public function get_id(): int {
        return $this->record->id;
    }

    /**
     * Delete peer data after motion deleted
     *
     * @param before_motion_deleted $hook Hook
     */
    public static function before_motion_deleted(before_motion_deleted $hook) {
        global $DB;

        $motion = $hook->get_motion();
        $context = $hook->get_context();
        $fs = get_file_storage();
        $speakers = $DB->get_records('plenumform_deft_peer', ['motion' => $motion->get('id')]);
        foreach ($speakers as $speaker) {
            $files = $fs->get_area_files($context->id, 'mod_plenum', 'speaker', $speaker->id);
            foreach ($files as $file) {
                $file->delete();
            }
        }
        $DB->delete_records('plenumform_deft_peer', ['motion' => $motion->get('id')]);
    }

    /**
     * User info for display
     *
     * @param renderer_base $output Renderer
     * @return array
     */
    public function userinfo(renderer_base $output): array {
        global $DB, $PAGE;

        $data = array_filter(json_decode($this->get_data(), true));

        foreach ($data as $key => $peerid) {
            if ($userid = $DB->get_field('plenumform_deft_peer', 'usermodified', ['id' => $peerid])) {
                $user = core_user::get_user($userid);
                $userpicture = new user_picture($user);
                $data[$key] = [
                    'id' => $peerid,
                    'slot' => ($key == 'feed') ? 'chair' : $key,
                    'name' => fullname($user),
                    'pictureurl' => $userpicture->get_url($PAGE, $output)->out(),
                ];
            }
        }

        if (!$pending = motion::immediate_pending($this->context)) {
            return $data + [
                'feed' => [
                    'id' => 0,
                    'slot' => 'chair',
                    'name' => get_string('chair', 'plenumform_deft'),
                    'pictureurl' => $output->image_url('chair-solid', 'plenumform_deft')->out(),
                ],
                'floor' => [
                    'id' => 0,
                    'slot' => 'floor',
                    'name' => get_string('floor', 'plenumform_deft'),
                    'pictureurl' => $output->image_url('microphone-solid', 'plenumform_deft')->out(),
                ],
            ];
        }

        $user = core_user::get_user($pending->get('usercreated'));
        $userpicture = new user_picture($user);
        $data += [
            'feed' => [
                'id' => 0,
                'slot' => 'chair',
                'name' => get_string('chair', 'plenumform_deft'),
                'pictureurl' => $output->image_url('chair-solid', 'plenumform_deft')->out(),
            ],
            'floor' => [
                'id' => 0,
                'slot' => 'floor',
                'name' => fullname($user),
                'pictureurl' => $userpicture->get_url($PAGE, $output)->out(),
            ],
        ];

        return $data;
    }

    /**
     * Whether to share video
     *
     * @return bool
     */
    public function share_video(): bool {
        global $USER;

        return has_capability('mod/plenum:meet', $this->context)
            &&  has_capability('plenumform/deft:sharevideo', $this->context)
            && (
                has_capability('mod/plenum:preside', $this->context)
                || (
                    ($pending = motion::immediate_pending($this->context))
                    && !empty($pending)
                    && ($pending->get('usercreated') == $USER->id)
                )
            );
    }

    /**
     * Whether able to view video
     *
     * @return bool
     */
    public function view_video(): bool {
        return has_capability('plenumform/deft:viewvideo', $this->context);
    }
}
