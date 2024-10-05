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

namespace plenumform_jitsi\event;

use core\event\base;

/**
 * The video_ended event class.
 *
 * @package     plenumform_jitsi
 * @category    event
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_ended extends base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'plenum';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'plenum', 'restore' => 'plenum'];
    }

    /**
     * Returns localised general event name.
     *
     * Override in subclass, we can not make it static and abstract at the same time.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventvideoended', 'plenumform_jitsi');
    }


    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if (!empty($this->other['motion'])) {
            return new \moodle_url('/mod/plenum/motion.php', ['id' => $this->other['motion']]);
        }
        return new \moodle_url('/mod/plenum/view.php', ['p' => $this->objectid]);
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' ended video in the Plenary meeting with id '$this->objectid'.";
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'motion' => ['db' => 'plenum_motion', 'restore' => 'motion'],
        ];
    }
}
