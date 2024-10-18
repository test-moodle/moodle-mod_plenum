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
 * WebSocket manager
 *
 * @package    plenumform_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumform_deft;

use context;
use context_module;
use mod_plenum\hook\after_motion_updated;
use moodle_exception;
use stdClass;
use mod_plenum\motion;

/**
 * Web socket manager
 *
 * @package    plenumform_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class socket extends \block_deft\socket {
    /**
     * @var Component
     */
    protected const COMPONENT = 'plenumform_deft';

    /**
     * @var Component
     */
    protected const FORM = 'deft';

    /**
     * Constructor
     *
     * @param context_module $context Context of block
     * @param int|null $itemid Optional item id
     */
    public function __construct(context_module $context, ?int $itemid = null) {
        if (is_null($itemid)) {
            $cm = get_coursemodule_from_id('plenum', $context->instanceid);
            $itemid = $itemid ?: (int)groups_get_activity_group($cm);
        }

        parent::__construct($context, $itemid);
    }

    /**
     * Validate context and availabilty
     */
    public function validate() {
        if (!get_config(static::COMPONENT, 'enabled')) {
            throw new moodle_exception('plugindisabled');
        }
        if (
            $this->context->contextlevel != CONTEXT_MODULE
        ) {
            throw new moodle_exception('invalidcontext');
        }
        if (
            !get_coursemodule_from_id('plenum', $this->context->instanceid)
        ) {
            throw new moodle_exception('invalidcontext');
        }
    }

    /**
     * Update content after motion modified
     *
     * @param after_motion_updated $hook Hook
     */
    public static function after_motion_updated(after_motion_updated $hook) {
        global $DB;

        if (
            empty($DB->get_record('plenum', [
                'id' => $hook->get_coursemodule()->instance,
                'form' => static::FORM,
            ]))
        ) {
            return;
        }

        $socket = new static($hook->get_context());
        $socket->dispatch();

        $cm = $hook->get_coursemodule();
        if (
            $room = new janus_room($cm->instance)
        ) {
            $data = json_decode($room->get_data());
            $pending = motion::immediate_pending($hook->get_context());
            if (
                !empty($data->floor) && (
                    empty($pending) ||
                    ($DB->get_field('plenumform_deft_peer', 'usermodified', ['id' => $data->floor]) != $pending->get('usercreated'))
                )
            ) {
                $data->floor = 0;
                $room->set_data($data);
            }
        }
    }
}
