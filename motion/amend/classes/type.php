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
 * Plenary meeting motion type definition
 *
 * @package   plenumtype_amend
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_amend;

use context_module;
use mod_plenum\base_type;
use mod_plenum\motion;
use renderer_base;

/**
 * Plenary meeting motion type definition
 *
 * @package   plenumtype_amend
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type extends base_type {
    /** Plugin component */
    const COMPONENT = 'plenumtype_amend';

    /** Whether information should be shown on motion */
    const DETAIL = true;

    /** @var $component */
    public string $component = 'plenumtype_amend';

    /** @var $debatable */
    public bool $debatable = true;

    /**
     * Get plugin identifier
     *
     * @return string
     */
    public function get_name() {
        return get_string('name', $this->component);
    }

    /**
     * Whether motion is currently in order
     *
     * @param context_module $context The context for plenary meeting
     * @param base_type|null $immediate The immediately pending question
     * @return bool
     */
    public static function in_order($context, $immediate) {

        if (
            !$immediate
            || !has_capability('mod/plenum:meet', $context)
            || $immediate->motion->has_child('call', motion::STATUS_ADOPT)
        ) {
            return false;
        }
        return !$immediate->needs_second() && (
            ($immediate->motion->get('type') == 'resolve')
            || (
                ($preceding = new motion($immediate->motion->get('parent')))
                && ($immediate->motion->get('type') == 'amend')
                && ($preceding->get('type') == 'resolve')
            )
        );
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $options = [];
        $data = [
            'content' => $this->motion->get_data()->amendment ?? '',
        ] + parent::export_for_template($output);

        return $data;
    }

    /**
     * Whether second is required
     *
     * @return bool
     */
    public function needs_second() {
        return get_config('plenumtype_amend', 'requiresecond')
            && get_config('plenumtype_second', 'enabled')
            && !$this->motion->has_child('second', motion::STATUS_ADOPT);
    }
}
