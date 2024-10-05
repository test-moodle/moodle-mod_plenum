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

namespace mod_plenum;

use advanced_testcase;
use mod_plenum\plenum;
use context_module;

/**
 * Unit tests for (some of) mod/plenum/lib.php.
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thiess
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends advanced_testcase {
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
        $this->setUser($user);

        /** @var \mod_plenum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');

        $motion = $generator->create_motion(['plenumid' => $activity->cmid]);

        // Check the Plenary meeting activity exist.
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));

        // Check motion exists.
        $this->assertNotEmpty($DB->get_record('plenum_motion', ['id' => $motion->get('id')]));

        // Check nothing happens when given activity id doesn't exist.
        plenum_delete_instance($activity->id + 1);
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));
        $this->assertEquals(1, $DB->count_records('plenum_motion'));

        // Check the Plenary meeting instance and its associated data is removed.
        plenum_delete_instance($activity->id);
        $this->assertEmpty($DB->get_record('plenum', ['id' => $activity->id]));
        $this->assertEquals(0, $DB->count_records('plenum_motion'));
    }

    /**
     * Test that plenum_delete_instance removes data.
     *
     * @covers \mod_plenum\plenum
     * @covers ::plenum_grade_item_update
     */
    public function test_plenum_grade(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $activity = $this->getDataGenerator()->create_module('plenum', ['course' => $course, 'grade' => 100]);
        $this->setUser($teacher);

        /** @var \mod_plenum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_plenum', ['grade' => 100]);

        // Check the Plenary meeting activity exist.
        $this->assertNotEmpty($DB->get_record('plenum', ['id' => $activity->id]));

        $cm = get_coursemodule_from_instance('plenum', $activity->id);
        $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum(context_module::instance($cm->id), $cm);

        // Check no grade assigned.
        $grade = $plenum->get_user_grade($user->id, false);
        $this->assertEquals(null, $grade);

        $plenum->save_grade($user->id, (object)[
            'userid' => $user->id,
            'grade' => '75.6',
            'feedback' => [
                'text' => 'OK',
                'format' => FORMAT_MOODLE,
            ],
        ]);

        // Check grade recorded.
        $grade = $plenum->get_user_grade($user->id, false);
        $this->assertEquals(75.6, $grade->grade);
        $this->assertEquals('OK', $grade->feedback);
    }

    /**
     * Check calendar event action
     *
     * @covers ::mod_plenum_core_calendar_provide_event_action
     */
    public function test_plenum_core_calendar_provide_event_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course();
        $plenum = $this->getDataGenerator()->create_module('plenum', ['course' => $course->id]);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $plenum->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_plenum_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('view'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * Check calendar event action already completed
     *
     * @covers ::mod_plenum_core_calendar_provide_event_action
     */
    public function test_plenum_core_calendar_provide_event_action_already_completed(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $plenum = $this->getDataGenerator()->create_module(
            'plenum',
            ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]
        );

        // Get some additional data.
        $cm = get_coursemodule_from_instance('plenum', $plenum->id);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $plenum->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_plenum_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Test mod_plenum_core_calendar_provide_event_action with user override
     *
     * @covers ::mod_plenum_core_calendar_provide_event_action
     */
    public function test_plenum_core_calendar_provide_event_action_user_override(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $plenum = $this->getDataGenerator()->create_module(
            'plenum',
            ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]
        );

        // Get some additional data.
        $cm = get_coursemodule_from_instance('plenum', $plenum->id);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $plenum->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_plenum_core_calendar_provide_event_action($event, $factory, $USER->id);

        // Decorate action with a userid override.
        $actionevent2 = mod_plenum_core_calendar_provide_event_action($event, $factory, $user->id);

        // Ensure result was null because it has been marked as completed for the associated user.
        // Logic was brought across from the "_already_completed" function.
        $this->assertNull($actionevent);

        // Confirm the event was decorated.
        $this->assertNotNull($actionevent2);
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent2);
        $this->assertEquals(get_string('view'), $actionevent2->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent2->get_url());
        $this->assertEquals(1, $actionevent2->get_item_count());
        $this->assertTrue($actionevent2->is_actionable());
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid The course id.
     * @param int $instanceid The instance id.
     * @param string $eventtype The event type.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'plenum';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }
}
