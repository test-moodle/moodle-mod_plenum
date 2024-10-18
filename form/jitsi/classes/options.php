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

namespace plenumform_jitsi;

use cache;
use cm_info;
use context_module;
use moodleform;
use MoodleQuickForm;
use moodle_url;
use stdClass;
use mod_plenum\motion;
use mod_plenum\options_base;
use plenumtype_open\hook\after_data;

/**
 * Class handling options Plenary meeting plugin
 *
 * @package     plenumform_jitsi
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options extends options_base {
    /**
     * Table to save options
     */
    protected const TABLE = 'plenumform_jitsi';

    /**
     * Called mform mod_form after_data to add form specific options
     *
     * @param MoodleQuickForm $mform Form to which to add fields
     */
    public static function create_settings_elements(MoodleQuickForm $mform) {
    }

    /**
     * Modify open motion form
     *
     * @param after_data $hook Hook for open motion definition
     */
    public static function form_elements(after_data $hook) {
        $mform = $hook->get_form();

        $cm = $hook->get_coursemodule();

        if (
            ($cm->customdata['form'] != 'jitsi')
            || groups_get_activity_groupmode($cm) == NOGROUPS
        ) {
            return;
        }

        $mform->addElement('text', 'room', get_string('room', 'plenumform_jitsi'));
        $mform->setType('room', PARAM_TEXT);
        $mform->addHelpButton('room', 'room', 'plenumform_jitsi');
    }

    /**
     * Prepares the form before data are set
     *
     * @param  array $defaultvalues
     * @param  int $instance
     */
    public static function data_preprocessing(array &$defaultvalues, int $instance) {
        global $DB;

        if (!empty($instance)) {
            $defaultvalues['room'] = $DB->get_field('plenumform_jitsi', 'room', ['plenum' => $instance]);
        }
    }
}
