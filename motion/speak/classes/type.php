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
 * @package   plenumtype_speak
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_speak;

use context_module;
use mod_plenum\base_type;
use mod_plenum\motion;
use renderer_base;

/**
 * Plenary meeting motion type definition
 *
 * @package   plenumtype_speak
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type extends base_type {
    /** Plugin component */
    const COMPONENT = 'plenumtype_speak';

    /** @var $component */
    public string $component = 'plenumtype_speak';

    /**
     * Whether motion is currently in order
     *
     * @param context_module $context The context for plenary meeting
     * @param base_type|null $immediate The immediately pending question
     * @return bool
     */
    public static function in_order($context, $immediate) {
        return $immediate
            && has_capability('mod/plenum:meet', $context)
            && !$immediate->needs_second()
            && $immediate->is_debatable()
            && in_array($immediate->motion->get('type'), ['resolve', 'amend', 'speak'])
            && !$immediate->motion->has_child('call', motion::STATUS_ADOPT);
    }

    /**
     * Change the status of the motion
     *
     * @param int $state
     */
    public function change_status(int $state) {
        global $DB;

        if (
            ($state == motion::STATUS_PENDING)
            && ($immediate = motion::immediate_pending($this->get_context()))
            && ($immediate->get('type') == 'speak')
            && !$DB->get_record('plenum', [
                'id' => $this->motion->get('plenum'),
                'moderate' => 1,
            ])
        ) {
            $speaker = new \plenumtype_speak\type($immediate, $this->get_context(), $this->get_course_module());
            $speaker->change_status(motion::STATUS_CLOSED);
        }
        parent::change_status($state);
    }

    /**
     * Whether user can withdraw
     *
     * @return bool
     */
    public function can_close(): bool {
        global $USER;

        return ($this->motion->get('usercreated') == $USER->id)
            && ($this->motion->get('status') == motion::STATUS_PENDING);
    }
}
