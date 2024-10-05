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
 * Class containing data for Plenary meeting
 *
 * @package     plenumform_deft
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumform_deft\output;

use cache;
use context_module;
use core_user;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;
use plenumform_deft\janus_room;
use plenumform_deft\socket;

/**
 * Class containing data for Plenary meeting
 *
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controls extends \mod_plenum\output\motions {
    /**
     * @var $data The JSON plugin data stored for room
     */
    protected string $data = '{}';

    /**
     * Constructor.
     *
     * @param context_module $context The context of the activity.
     */
    public function __construct(context_module $context) {
        global $DB, $USER;

        $this->cm = get_coursemodule_from_id('plenum', $context->instanceid);

        $this->motions = motion::instances($context);

        $room = new janus_room($this->cm->instance);
        $this->data = $room->get_data();

        $this->context = $context;

        $pending = motion::immediate_pending($context);
        $this->pending = !empty($pending) && ($pending->get('usercreated') == $USER->id);
        $this->floor = empty($pending) ? 0 : $pending->get('usercreated');
        $this->motions = motion::instances($context);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;

        $issharingvideo = $this->share_video() && $DB->get_record('plenumform_deft_peer', [
            'plenum' => $this->cm->instance,
            'status' => 0,
            'usermodified' => $USER->id,
        ]);

        return [
            'contextid' => $this->context->id,
            'pending' => $this->pending,
            'sharevideo' => $this->share_video(),
            'viewvideo' => $this->view_video(),
            'issharingvideo' => $this->share_video() && $DB->get_record('plenumform_deft_peer', [
                'plenum' => $this->cm->instance,
                'status' => 0,
                'usermodified' => $USER->id,
            ]),
        ];
    }

    /**
     * Whether to share video
     *
     * @return bool
     */
    public function share_video(): bool {
        return has_capability('mod/plenum:meet', $this->context)
            &&  has_capability('plenumform/deft:sharevideo', $this->context)
            && (
                has_capability('mod/plenum:preside', $this->context)
                || $this->pending
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
