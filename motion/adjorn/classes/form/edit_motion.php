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
 * Plugin version and other meta-data are defined here.
 *
 * @package     plenumtype_adjorn
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_adjorn\form;

use context;
use context_user;
use core_form\dynamic_form;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;

/**
 * Editing form to create motion
 *
 * @package     plenumtype_adjorn
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_motion extends \mod_plenum\form\edit_motion {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('html', get_string('createmotion', 'plenumtype_adjorn'));

        parent::definition();
    }
}
