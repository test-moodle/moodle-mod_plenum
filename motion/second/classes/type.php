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
 * @package   plenumtype_second
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_second;

use context_module;
use mod_plenum\base_type;
use mod_plenum\motion;
use renderer_base;

/**
 * Plenary meeting motion type definition
 *
 * @package   plenumtype_second
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type extends base_type {
    /** Plugin component */
    const COMPONENT = 'plenumtype_second';

    /** @var $component */
    public string $component = 'plenumtype_second';

    /** @var $debatable */
    public bool $debatable = false;

    /**
     * Whether motion is currently in order
     *
     * @param context_module $context The context for plenum
     * @param base_type|null $immediate The immediately pending question
     * @return bool
     */
    public static function in_order($context, $immediate) {
        global $USER;

        return
            has_capability('mod/plenum:meet', $context)
            && $immediate
            && ($USER->id != $immediate->motion->get('usercreated'))
            && $immediate->needs_second();
    }

    /**
     * Change the status of the motion
     *
     * @param int $state
     */
    public function change_status(int $state) {
        if ($state == motion::STATUS_PENDING) {
            parent::change_status(motion::STATUS_ADOPT);
        } else {
            parent::change_status($state);
        }
    }
}
