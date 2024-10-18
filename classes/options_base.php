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

use cache;
use cm_info;
use context_module;
use moodleform;
use MoodleQuickForm;
use moodle_url;
use stdClass;
use mod_plenum\motion;

/**
 * Class handling options for Plenary meeting plugin
 *
 * @package     mod_plenum
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options_base {
    /**
     * Table to save options
     */
    protected const TABLE = '';

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

        if (static::TABLE) {
            return $DB->delete_records(static::TABLE, ['plenum' => $plenum]);
        }

        return true;
    }

    /**
     * Updates an instance of the mod_plenum in the database.
     *
     * Given an object containing all the necessary data (defined in mod_form.php),
     * this function will update an existing instance with new data.
     *
     * @param object $moduleinstance An object from the form in mod_form.php.
     * @param mod_plenum_mod_form $mform The form.
     * @return bool True if successful, false otherwise.
     */
    public static function update_instance($moduleinstance, $mform = null) {
        global $DB;

        if (static::TABLE) {
            if ($record = $DB->get_record(static::TABLE, ['plenum' => $moduleinstance->id])) {
                $record = ['id' => $record->id, 'plenum' => $moduleinstance->id] + (array) $moduleinstance + (array) $record;
                $DB->update_record(static::TABLE, $record);
            } else {
                $record = ['id' => null, 'plenum' => $moduleinstance->id]
                    + (array) $moduleinstance;
                $record['id'] = $DB->insert_record(static::TABLE, $record);
            }
        }

        return true;
    }

    /**
     * Prepares the form before data are set
     *
     * @param  array $defaultvalues
     * @param  int $instance
     */
    public static function data_preprocessing(array &$defaultvalues, int $instance) {
    }
}
