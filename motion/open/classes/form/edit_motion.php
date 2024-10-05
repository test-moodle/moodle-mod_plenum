<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin edit form for motion
 *
 * @package     plenumtype_open
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_open\form;

use context;
use context_user;
use core_form\dynamic_form;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;
use moodleform;

/**
 * Plugin edit form for motion
 *
 * @package     plenumtype_open
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_motion extends \mod_plenum\form\edit_motion {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('html', get_string('createmotion', 'plenumtype_open'));

        parent::definition();
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        $mform = $this->_form;

        parent::set_data_for_dynamic_submission();

        $context = $this->get_context_for_dynamic_submission();
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);

        $hook = new \plenumtype_open\hook\after_data($context, $cm, $mform);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
    }
}
