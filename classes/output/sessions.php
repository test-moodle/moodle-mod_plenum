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
class sessions implements renderable, templatable {
    /** @var $motion Motions */
    protected $motions;

    /**
     * Constructor.
     *
     * @param context_module $context The context of the activity
     * @param stdClass $cm Course module
     * @param stdClass $instance Module record
     */
    public function __construct(
        /** @var context_module $context Module context */
        protected context_module $context,
        /** @var stdClass $cm Course module record */
        protected stdClass $cm,
        /** @var $instance Activity instance record */
        protected stdClass $instance
    ) {
        global $DB;

        $motions = $DB->get_records('plenum_motion', [
            'plenum' => $instance->id,
            'type' => 'open',
            'groupid' => groups_get_activity_group($cm),
        ]);

        $this->motions = [];
        foreach ($motions as $motion) {
            $url = new moodle_url('/mod/plenum/motion.php', ['id' => $motion->id]);
            $this->motions[] = [
                'id' => $motion->id,
                'timecreated' => userdate($motion->timecreated),
                'timemodified' => userdate($motion->timemodified),
                'url' => $url->out(),
            ];
        };
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        return [
            'contextid' => $this->context->id,
            'name' => $this->instance->name,
            'motions' => $this->motions,
        ];
    }
}
