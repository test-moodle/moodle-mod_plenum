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
 * Class for Plenary meeting media elements
 *
 * @package     plenumform_basic
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumform_basic\output;

use cache;
use cm_info;
use context_module;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;
use plenumform_basic\socket;

/**
 * Class for Plenary meeting media elements
 *
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main extends \mod_plenum\output\main {
    /**
     * Constructor.
     *
     * @param context_module $context The context of the module.
     * @param stdClass $cm Course module info for activity
     * @param stdClass $instance Activity record
     */
    public function __construct(
        /** @var $context Module context */
        protected readonly context_module $context,
        /** @var $cm Course module record */
        protected readonly cm_info $cm,
        /** @var stdClass $instance Instance record */
        protected readonly stdClass $instance
    ) {
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $motions = new motions($this->context, $this->cm, $this->instance);

        return [
            'chair' => has_capability('mod/plenum:preside', $this->context),
            'delay' => get_config('plenumform_basic', 'delay') * 1000,
            'throttle' => get_config('block_deft', 'throttle'),
        ] + $motions->export_for_template($output) + parent::export_for_template($output);
    }
}
