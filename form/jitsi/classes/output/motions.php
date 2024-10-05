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
 * @package     plenumform_jitsi
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumform_jitsi\output;

use cache;
use context_module;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;
use plenumform_jitsi\socket;

/**
 * Class containing data for Plenary meeting
 *
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motions extends \mod_plenum\output\motions {
    /**
     * Constructor.
     *
     * @param context_module $context The context of the activity.
     */
    public function __construct(context_module $context) {
        global $USER;

        $this->motions = motion::instances($context);

        $this->context = $context;

        $pending = motion::immediate_pending($context);
        $this->pending = !empty($pending) && ($pending->get('usercreated') == $USER->id);
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
            'pending' => $this->pending,
        ] + parent::export_for_template($output);
    }
}
