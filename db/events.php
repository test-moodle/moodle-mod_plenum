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
 * Plugin event observers are registered here.
 *
 * @package     mod_plenum
 * @category    event
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname' => '\\event\comment_created',
        'callback' => 'block_deft\comment::observe',
        'internal' => true,
    ],
    [
        'eventname' => '\block_deft\event\comment_deleted',
        'callback' => 'block_deft\comment::observe',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\user_loggedout',
        'callback' => 'block_deft\venue_manager::logout',
        'internal' => true,
    ],
];
