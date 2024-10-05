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

namespace plenumtype_open\event;

use advanced_testcase;
use context_module;
use mod_plenum\motion;

/**
 * Unit tests for (some of) mod/plenum/lib.php.
 *
 * @package    plenumtype_open
 * @copyright  2024 Daniel Thiess
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_plenum\event\motion_created
 * @covers     \mod_plenum\event\motion_deleted
 * @covers     \mod_plenum\event\motion_updated
 * @covers     \mod_plenum\event\motion_viewed
 */
final class motion_events_test extends advanced_testcase {
    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/mod/plenum/lib.php");
        parent::setUpBeforeClass();
    }

    /**
     * Test that plenum_delete_instance removes data.
     *
     * @covers ::plenum_delete_instance
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

        // Capture events.
        $sink = $this->redirectEvents();

        $motion = $generator->create_motion(['plenumid' => $activity->cmid]);

        // Check the Plenary meeting activity exist.
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));

        // Check event.
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_plenum\event\motion_created', $event);
        $this->assertEquals(context_module::instance($activity->cmid), $event->get_context());
        $this->assertEquals($motion->get('id'), $event->objectid);
        $this->assertEventContextNotUsed($event);

        // Update motion to new status.
        $instance = $motion->get_instance();
        $instance = $instance->change_status(motion::STATUS_PENDING);

        // Check event.
        $events = $sink->get_events();
        $this->assertCount(2, $events);
        $event = end($events);

        // Save id.
        $motionid = $motion->get('id');

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_plenum\event\motion_updated', $event);
        $this->assertEquals(context_module::instance($activity->cmid), $event->get_context());
        $this->assertEquals($motionid, $event->objectid);
        $this->assertEventContextNotUsed($event);

        $event = \mod_plenum\event\motion_viewed::create([
            'objectid' => $motion->get('id'),
            'context' => context_module::instance($activity->cmid),
        ]);
        $event->add_record_snapshot('course', $course);
        $event->trigger();

        // Check event.
        $events = $sink->get_events();
        $this->assertCount(3, $events);
        $event = end($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_plenum\event\motion_viewed', $event);
        $this->assertEquals(context_module::instance($activity->cmid), $event->get_context());
        $this->assertEquals($motionid, $event->objectid);
        $this->assertEventContextNotUsed($event);

        // Check the session and its associated data is removed.
        \plenumtype_open\type::delete_session($motion);

        // Check event.
        $events = $sink->get_events();
        $this->assertCount(4, $events);
        $event = end($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_plenum\event\motion_deleted', $event);
        $this->assertEquals(context_module::instance($activity->cmid), $event->get_context());
        $this->assertEquals($motionid, $event->objectid);
        $this->assertEventContextNotUsed($event);
    }
}
