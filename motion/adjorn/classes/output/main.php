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
 * @package     plenumtype_adjorn
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumtype_adjorn\output;

use cache;
use context_module;
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
        $motions = [];
        foreach (motion::get_records(['parent' => $this->motion->get('id')], 'timecreated') as $key => $motion) {
            if (
                in_array($motion->get('type'), [
                'divide',
                'second',
                ])
            ) {
                $motions[] = [
                    'id' => $motion->get('id'),
                    'key' => $key + 1,
                    'name' => $motion->get_data()->name ?? '',
                    'type' => $motion->get('type'),
                    'pluginname' => get_string('pluginname', 'plenumtype_' . $motion->get('type')),
                    'adopted' => $motion->get('status') == motion::STATUS_ADOPT,
                    'declined' => $motion->get('status') == motion::STATUS_DECLINE,
                    'url' => (new moodle_url('/mod/plenum/motion.php', ['id' => $motion->get('id')]))->out(),
                ];
            }
        }
        $data = $this->motion->to_record();
        $data->plugindata = $this->motion->get_data();

        $previous = new motion($this->motion->get('parent'));
        $data->previous = [
            'id' => $previous->get('id'),
            'name' => $previous->get_data()->name,
            'pluginname' => get_string('pluginname', 'plenumtype_' . $previous->get('type')),
            'url' => (new moodle_url('/mod/plenum/motion.php', ['id' => $previous->get('id')]))->out(),
        ];
        $data->motions = $motions;
        $data->contextid = $this->context->id;

        $type = new \plenumtype_adjorn\type($this->motion, $this->context);

        return (array)$data + $type->export_for_template($output);
    }
}
