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

namespace plenumform_deft;

use cache;
use cm_info;
use context_module;
use moodleform;
use MoodleQuickForm;
use moodle_url;
use stdClass;
use mod_plenum\motion;
use mod_plenum\options_base;

/**
 * Class handling options Plenary meeting plugin
 *
 * @package     plenumform_deft
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options extends options_base {
    /**
     * Called mform mod_form after_data to add form specific options
     *
     * @param MoodleQuickForm $mform Form to which to add fields
     */
    public static function create_settings_elements(MoodleQuickForm $mform) {
    }

    /**
     * Removes plugin data from the database when activity deleted.
     *
     * @param int $plenum Instance id
     * @return bool True if successful, false on failure.
     */
    public static function delete_instance(int $plenum): bool {
        global $DB;

        if (class_exists('\\block_deft\\janus_room')) {
            foreach ($DB->get_fieldset('plenumform_deft_room', 'id', ['plenum' => $plenum]) as $id) {
                \block_deft\janus_room::remove('plenumform_deft', $id);
            }
        }
        $DB->delete_records('plenumform_deft_room', ['plenum' => $plenum]);
        $DB->delete_records('plenumform_deft_peer', ['plenum' => $plenum]);

        return parent::delete_instance($plenum);
    }
}
