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

namespace mod_plenum\backup;

defined('MOODLE_INTERNAL') || die();

use mod_plenum\motion;

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_plenum_activity_structure_step
 * @covers     \restore_plenum_activity_structure_step
 */
final class restore_date_test extends \restore_date_testcase {
    /**
     * Test restore dates.
     */
    public function test_restore_dates(): void {
        global $DB;

        // Create plenum data.
        $record = [
            'completionexpected' => 100,
        ];
        [$course, $plenum] = $this->create_course_and_module('plenum', $record);
        $activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');

        // Set time fields to a constant for easy validation.
        $timestamp = 100;

        // Add motion.
        $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'type' => 'open',
            'timecreated' => $timestamp,
            'timemodified' => $timestamp,
        ]);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newplenum = $DB->get_record('plenum', ['course' => $newcourseid]);

        $this->assertEquals(1, count(motion::get_records(['plenum' => $newplenum->id])));

        $this->assertFieldsNotRolledForward($plenum, $newplenum, ['timemodified']);
    }
}
