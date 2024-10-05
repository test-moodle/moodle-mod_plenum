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
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_plenum\output;

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
class motions implements renderable, templatable {
    /** @var $motions Pending motions */
    protected $motions = null;

    /** @var $pending Whether current user author immediate motion */
    protected $pending = null;

    /**
     * Constructor.
     *
     * @param context_module $context The context of the meeting
     */
    public function __construct(
        /** @var $context Module context */
        protected context_module $context,
        /** @var $groupid Group id */
        protected $groupid = null
    ) {
        $this->motions = motion::instances($context, $groupid);
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
            $motions[] = [
                'motion' => $motion->export_for_template($output),
                'type' => $motion->component,
            ];
        }
        $offeredmotions = array_filter(
            array_column($motions, 'motion'),
            function ($motion) {
                return !$motion['pending'];
            }
        );
        $availablemotions = [];
        foreach (motion::available_motions($this->context, $this->groupid) as $type => $name) {
            $availablemotions[] = [
                'type' => $type,
                'name' => $name,
            ];
        }

        $data = [
            'contextid' => $this->context->id,
            'motions' => $motions,
            'offeredmotions' => $offeredmotions,
            'availablemotions' => $availablemotions,
            'groupid' => $this->groupid,
        ];

        return $data;
    }
}
