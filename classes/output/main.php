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
use cm_info;
use context_module;
use moodleform;
use MoodleQuickForm;
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
    /** @var $currentgroup Current group */
    protected $currentgroup;

    /** @var $motion Motions */
    protected $motions;

    /**
     * Constructor.
     *
     * @param context_module $context Module context
     * @param cm_info $cm Course module record
     * @param stdClass $instance Instance record
     */
    public function __construct(
        /** @var context_module $context Module context */
        protected readonly context_module $context,
        /** @var cm_info $cm Course module record */
        protected readonly cm_info $cm,
        /** @var stdClass $instance Instance record */
        protected readonly stdClass $instance
    ) {
        $this->currentgroup = groups_get_activity_group($cm);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $motions = new motions($this->context);
        return [
            'contextid' => $this->context->id,
            'cangrade' => has_capability('mod/plenum:grade', $this->context),
            'grade' => $this->instance->grade,
            'instance' => json_encode($this->instance),
            'name' => $this->instance->name,
        ] + $motions->export_for_template($output);
    }

    /**
     * Called mform mod_form after_data to add form specific options
     *
     * @param MoodleQuickForm $mform Form to which to add fields
     */
    public static function create_settings_elements(MoodleQuickForm $mform) {
    }
}
