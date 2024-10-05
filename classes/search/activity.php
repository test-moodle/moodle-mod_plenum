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
 * Search area for Plenary meeting activity
 *
 * @package mod_plenum
 * @copyright 2024 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\search;

use context;
use context_block;
use core_search\document;
use core_search\document_icon;
use core_search\manager;
use core_search\moodle_recordset;

/**
 * Search area for Plenary meeting activity
 *
 * @package mod_plenum
 * @copyright 2024 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends \core_search\base_activity {
    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Return the context info required to index files for
     * this search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        $fileareas = ['intro']; // Fileareas.

        return $fileareas;
    }
}
