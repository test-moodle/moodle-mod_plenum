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
 * Plugin capabilities are defined here.
 *
 * @package     plenumform_deft
 * @category    access
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'plenumform/deft:sharevideo' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    'plenumform/deft:viewvideo' => [
        'captype' => 'view',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'guest' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],
];
