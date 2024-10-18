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
 * External function test for update_content.
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
 * External function test for update_content
 *
 * @package    mod_plenum
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_plenum\external\update_content
 */
final class update_content_test extends externallib_advanced_testcase {
    /**
     * Test test_update_content invalid id.
     */
    public function test_update_content_invalid_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException('moodle_exception');
        update_content::execute(0);
    }

    /**
     * Test test_update_content user not enrolled.
     */
    public function test_update_content_user_not_enrolled(): void {
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
        update_content::execute($context->id);
    }

    /**
     * Test test_update_content user student.
     */
    public function test_update_content_user_student(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        $cm = get_coursemodule_from_instance('plenum', $scenario->plenum->id);
        $this->setUser($scenario->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $context = context_module::instance($cm->id);
        $result = update_content::execute($context->id);
        $result = external_api::clean_returnvalue(update_content::execute_returns(), $result);
        $this->assertNotEmpty($result['motions']);

        // View events should not be recorded.
        $events = $sink->get_events();
        $this->assertCount(0, $events);
    }

    /**
     * Test test_update_content user missing capabilities.
     */
    public function test_update_content_user_missing_capabilities(): void {
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
        update_content::execute($context->id);
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
