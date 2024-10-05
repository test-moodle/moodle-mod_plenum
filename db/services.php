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
 * File description.
 *
 * @package   mod_plenum
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_plenum_get_fragment' => [
        'classname' => 'mod_plenum\external\get_fragment',
        'methodname' => 'execute',
        'description' => 'Get template fragment',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_plenum_update_content' => [
        'classname' => '\\mod_plenum\\external\\update_content',
        'methodname' => 'execute',
        'description' => 'Get updated meeting information',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_plenum_view_module' => [
        'classname' => '\\mod_plenum\\external\\view_module',
        'methodname' => 'execute',
        'description' => 'Record module view',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
