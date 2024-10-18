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

namespace mod_plenum\output;

use cache;
use context_module;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;
use mod_plenum\plenum;

/**
 * Class for user activity report
 *
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_plenum
 */
class activity_report implements renderable, templatable {
    /** @var $motions User motions */
    protected $motions = null;

    /**
     * Constructor.
     *
     * @param context_module $context Module context
     * @param int $userid User id
     * @param null|int $groupid Group id
     */
    public function __construct(
        /** @var context_module $context Module context */
        protected context_module $context,
        /** @var int $userid User id */
        protected int $userid,
        /** @var null|int $groupid Group id */
        protected ?int $groupid = null
    ) {
        $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum($context);

        $this->motions = motion::get_records([
            'plenum' => $plenum->get_id(),
            'usercreated' => $userid,
        ], 'timecreated');
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $motions = [];
        foreach ($this->motions as $motion) {
            $motions[] = $motion->export_for_template($output) + [
                'parent' => $motion->get('parent'),
            ];
        }

        $data = [
            'contextid' => $this->context->id,
            'motions' => $motions,
            'groupid' => $this->groupid,
        ];

        return $data;
    }
}
