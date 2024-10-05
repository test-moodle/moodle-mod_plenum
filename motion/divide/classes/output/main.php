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
 * @package     plenumtype_divide
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumtype_divide\output;

use cache;
use context_module;
use core_user;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;

/**
 * Class containing data for Plenary meeting
 *
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param context_module $context The context of the activity
     * @param motion $motion motion
     */
    public function __construct(
        /** @var $context Module context */
        protected context_module $context,
        /** @var $motion Motion */
        protected motion $motion
    ) {
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $yeas = [];
        $motions = motion::get_records([
            'parent' => $this->motion->get('id'),
            'status' => motion::STATUS_DRAFT,
            'type' => 'yea',
        ], 'timecreated');
        foreach ($motions as $motion) {
            $yeas[] = [
                'id' => $motion->get('usercreated'),
                'name' => fullname(core_user::get_user($motion->get('usercreated'))),
            ];
        }

        $nays = [];
        $motions = motion::get_records([
            'parent' => $this->motion->get('id'),
            'status' => motion::STATUS_DRAFT,
            'type' => 'nay',
        ], 'timecreated');

        foreach ($motions as $motion) {
            $nays[] = [
                'id' => $motion->get('usercreated'),
                'name' => fullname(core_user::get_user($motion->get('usercreated'))),
            ];
        }
        $data = $this->motion->to_record();
        $data->plugindata = $this->motion->get_data();
        $data->yeas = $yeas;
        $data->totalyea = count($yeas);
        $data->nays = $nays;
        $data->totalnay = count($nays);

        $previous = new motion($this->motion->get('parent'));
        $data->previous = [
            'id' => $previous->get('id'),
            'name' => $previous->get_data()->name ?? '',
            'pluginname' => get_string('pluginname', 'plenumtype_' . $previous->get('type')),
            'url' => (new moodle_url('/mod/plenum/motion.php', ['id' => $previous->get('id')]))->out(),
        ];
        $data->contextid = $this->context->id;

        return (array)$data;
    }
}
