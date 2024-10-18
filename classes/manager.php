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

namespace mod_plenum;

use context;
use cm_info;
use context_module;
use moodle_exception;
use stdClass;

/**
 * Plenary meeting manager
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Constructor
     *
     * @param \core\clock $clock System clock
     * @param \moodle_database $db Database manager
     */
    public function __construct(
        /** @var readonly \core\clock $clock System clock */
        protected readonly \core\clock $clock,
        /** @var readonly \moodle_database $db Database manager */
        protected readonly \moodle_database $db
    ) {
    }

    /**
     * Saves a new instance of the mod_plenum into the database.
     *
     * Given an object containing all the necessary data, (defined by the form
     * in mod_form.php) this function will create a new instance and return the id
     * number of the instance.
     *
     * @param context_module $context
     * @param null|stdClass|cm_info $cm Course module
     * @param null|stdClass $course Course record
     * @param null|stdClass $instance Activity instance record
     * @return plenum
     */
    public function get_plenum(
        context_module $context,
        null|stdClass|cm_info $cm = null,
        ?stdClass $course = null,
        ?stdClass $instance = null
    ) {
        return new plenum($context, $cm, $course, $instance, $this->clock, $this->db);
    }
}
