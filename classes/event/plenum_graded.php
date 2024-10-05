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

namespace mod_plenum\event;

use core\event\base;
use mod_plenum\plenum;

/**
 * The plenum_graded event class.
 *
 * @package     mod_plenum
 * @category    event
 * @copyright   2024 Daniel Thies <dthies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plenum_graded extends base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;


    /**
     * Create instance of event.
     *
     * @param \plenum $plenum
     * @param \stdClass $grade
     * @return plenum_graded
     */
    public static function create_from_grade(plenum $plenum, \stdClass $grade) {
        $data = [
            'context' => $plenum->get_context(),
            'objectid' => $grade->id,
            'relateduserid' => $grade->userid,
        ];
        self::$preventcreatecall = false;
        $event = self::create($data);
        self::$preventcreatecall = true;
        $event->add_record_snapshot('plenum_grades', $grade);
        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'plenum_grades';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('plenum_graded', 'mod_plenum');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with the id '$this->userid' has graded plenary meeting with id '$this->objectid'" .
                " for Plenary meeting activity with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/plenum/view.php', [
            'id' => $this->contextinstanceid,
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception(
                'cannot call plenum_graded::create() directly, use plenum_graded::create_from_grade() instead.'
            );
        }

        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'plenum_grades', 'restore' => 'grade'];
    }
}
