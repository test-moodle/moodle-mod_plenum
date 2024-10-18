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
 * External function test for view_module.
 *
 * @package    mod_plenum
 * @category   external
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_external\external_api;
use externallib_advanced_testcase;
use stdClass;
use context_module;
use course_modinfo;

/**
 * External function test for view_module
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_plenum\external\view_module
 */
final class view_module_test extends externallib_advanced_testcase {
    /**
     * Test test_view_module invalid id.
     */
    public function test_view_module_invalid_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException('moodle_exception');
        view_module::execute(0);
    }

    /**
     * Test test_view_module user not enrolled.
     */
    public function test_view_module_user_not_enrolled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();
        $cm = get_coursemodule_from_instance('plenum', $scenario->plenum->id);
        $context = context_module::instance($cm->id);

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        $this->expectException('moodle_exception');
        view_module::execute($context->id);
    }

    /**
     * Test test_view_module user student.
     */
    public function test_view_module_user_student(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        $cm = get_coursemodule_from_instance('plenum', $scenario->plenum->id);
        $this->setUser($scenario->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $context = context_module::instance($cm->id);
        $result = view_module::execute($context->id);
        $result = external_api::clean_returnvalue(view_module::execute_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_plenum\event\course_module_viewed', $event);
        $this->assertEquals($scenario->contextmodule, $event->get_context());
        $plenum = new \moodle_url('/mod/plenum/view.php', ['id' => $cm->id]);
        $this->assertEquals($plenum, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test test_view_module user missing capabilities.
     */
    public function test_view_module_user_missing_capabilities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();
        $cm = get_coursemodule_from_instance('plenum', $scenario->plenum->id);
        $context = context_module::instance($cm->id);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/plenum:view', CAP_PROHIBIT, $studentrole->id, $scenario->contextmodule->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->setUser($scenario->student);
        $this->expectException('moodle_exception');
        view_module::execute($context->id);
    }

    /**
     * Create a scenario to use into the tests.
     *
     * @return stdClass $scenario
     */
    protected function setup_scenario() {

        $course = $this->getDataGenerator()->create_course();
        $plenum = $this->getDataGenerator()->create_module('plenum', ['course' => $course]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $contextmodule = context_module::instance($plenum->cmid);

        $scenario = new stdClass();
        $scenario->contextmodule = $contextmodule;
        $scenario->student = $student;
        $scenario->plenum = $plenum;

        return $scenario;
    }
}
