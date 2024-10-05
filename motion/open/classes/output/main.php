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
 * @package     plenumtype_open
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumtype_open\output;

use cache;
use context_module;
use moodle_url;
use mod_plenum\motion;
use renderable;
use renderer_base;
use stdClass;
use templatable;

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
        if (optional_param('delete', null, PARAM_INT)) {
            $url = new moodle_url('/mod/plenum/motion.php', ['cmid' => $context->instanceid]);
            \plenumtype_open\type::delete_session($motion);
            redirect(
                $url,
                get_string('sessiondeleted', 'plenumtype_open'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
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
                'resolve',
                ]) && in_array($motion->get('status'), [
                motion::STATUS_ADOPT,
                motion::STATUS_DECLINE,
                motion::STATUS_PENDING,
                ])
            ) {
                $motions[] = [
                    'id' => $motion->get('id'),
                    'key' => $key + 1,
                    'name' => $motion->get_data()->name,
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
        $data->contextid = $this->context->id;
        $data->motions = $motions;
        $data->candelete = has_capability('plenumtype/open:delete', $this->context);
        $url = new moodle_url('/mod/plenum/motion.php', ['id' => $this->motion->get('id')]);
        $data->url = $url->out();

        $type = new \plenumtype_open\type($this->motion, $this->context);

        return (array)$data + $type->export_for_template($output);
    }
}
