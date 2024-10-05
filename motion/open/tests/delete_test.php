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

namespace plenumtype_open;

use advanced_testcase;
use mod_plenum\motion;

/**
 * Unit tests for (some of) mod/plenum/lib.php.
 *
 * @package    plenumtype_open
 * @copyright  2024 Daniel Thiess
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delete_test extends advanced_testcase {
    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/mod/plenum/lib.php");
        parent::setUpBeforeClass();
    }

    /**
     * Test that delete_session removes data.
     *
     * @covers \plenumtype_open::delete_session
     */
    public function test_plenum_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('plenum', ['course' => $course]);

        /** @var \mod_plenum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');

        $motion = $generator->create_motion(['plenumid' => $activity->cmid]);

        // Check the Plenary meeting activity exist.
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));

        // Check motion exists.
        $this->assertEquals(1, count(motion::get_records([])));

        // Check the session and its associated data is removed.
        \plenumtype_open\type::delete_session($motion);
        $this->assertEquals(0, count(motion::get_records([])));

        // Check the activity still exists.
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));
    }
}
