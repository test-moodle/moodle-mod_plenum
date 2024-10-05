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
 * @package   plenumform_deft
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'plenumform_deft_join_room' => [
        'classname' => '\\plenumform_deft\\external\\join_room',
        'methodname' => 'execute',
        'description' => 'Regiter entry in Janus room',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'plenumform_deft_renew_token' => [
        'classname' => '\\plenumform_deft\\external\\renew_token',
        'methodname' => 'execute',
        'description' => 'Get new token to access message service',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'plenumform_deft_publish_feed' => [
        'classname' => '\\plenumform_deft\\external\\publish_feed',
        'methodname' => 'execute',
        'description' => 'Publish a video feed',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'plenumform_deft_get_room' => [
        'classname' => '\\plenumform_deft\\external\\get_room',
        'methodname' => 'execute',
        'description' => 'Get room settigs',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'plenumform_deft_update_content' => [
        'classname' => '\\plenumform_deft\\external\\update_content',
        'methodname' => 'execute',
        'description' => 'Get updated meeting information',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
