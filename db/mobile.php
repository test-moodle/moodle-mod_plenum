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
 * Mobile definition for Plenary meeting module
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_plenum' => [
        'handlers' => [
            'plenum' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/plenum/pix/monologo.svg',
                    'class' => '',
                ],

                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_mod_view',
                'offlinefunctions' => [
                    'mobile_mod_view' => [],
                    'mobile_motion_list' => [],
                ],
                'init' => 'init',
            ],
        ],
        'lang' => [
            ['cancel', 'core'],
            ['confirm', 'core'],
            ['content', 'mod_plenum'],
            ['denymotion', 'mod_plenum'],
            ['group', 'core'],
            ['pluginname', 'mod_plenum'],
            ['move', 'mod_plenum'],
            ['name', 'core'],
            ['needssecond', 'mod_plenum'],
            ['offeredmotions', 'mod_plenum'],
            ['nomotionoffered', 'mod_plenum'],
            ['pendingmotions', 'mod_plenum'],
            ['potentialmotions', 'mod_plenum'],
            ['replacemotion', 'mod_plenum'],
            ['view', 'core'],
        ],
    ],
];
