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
 * Assign search unit tests.
 *
 * @package     mod_plenum
 * @category    test
 * @copyright   2016 Eric Merrill {@link http://www.merrilldigital.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\search;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Provides the unit tests for plenum search.
 *
 * @package     mod_plenum
 * @category    test
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_plenum\search\motion
 */
final class search_test extends \advanced_testcase {
    /**
     * Availability.
     *
     * @return void
     */
    public function test_search_enabled(): void {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        \testable_core_search::instance();
        $searchareaid = \core_search\manager::generate_areaid('mod_plenum', 'motion');
        $searcharea = \core_search\manager::get_search_area($searchareaid);
        [$componentname, $varname] = $searcharea->get_config_var_name();

        // Enabled by default once global search is enabled.
        $this->assertTrue($searcharea->is_enabled());

        set_config($varname . '_enabled', 0, $componentname);
        $this->assertFalse($searcharea->is_enabled());

        set_config($varname . '_enabled', 1, $componentname);
        $this->assertTrue($searcharea->is_enabled());
    }

    /**
     * Indexing collaborative page contents.
     *
     * @return void
     */
    public function test_motion_indexing(): void {
        global $DB;

        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        \testable_core_search::instance();
        $searchareaid = \core_search\manager::generate_areaid('mod_plenum', 'motion');

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($searchareaid);

        $this->assertInstanceOf('\mod_plenum\search\motion', $searcharea);

        $activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');
        $course1 = self::getDataGenerator()->create_course();

        $plenum = $this->getDataGenerator()->create_module('plenum', ['course' => $course1->id]);
        $motion1 = $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'type' => 'open',
        ]);
        $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'parent' => $motion1->get('id'),
            'type' => 'resolve',
            'resolution' => 'Hipopotamus',
        ]);

        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);

            // Static caches are working.
            $dbreads = $DB->perf_get_reads();
            $doc = $searcharea->get_document($record);
            $this->assertEquals($dbreads, $DB->perf_get_reads());
            $this->assertInstanceOf('\core_search\document', $doc);
            $nrecords++;
        }
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();

        // We expect 1 (not 2) motions.
        $this->assertEquals(1, $nrecords);

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();
    }

    /**
     * Test group support
     *
     * @return void
     */
    public function test_motion_group_support(): void {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        \testable_core_search::instance();
        $searchareaid = \core_search\manager::generate_areaid('mod_plenum', 'motion');

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($searchareaid);

        $this->assertInstanceOf('\mod_plenum\search\motion', $searcharea);

        $activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');
        $course1 = self::getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'teacher');
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $this->getDataGenerator()->create_group(['courseid' => $course1->id]);

        $plenum = $this->getDataGenerator()->create_module('plenum', [
            'course' => $course1->id,
            'groupmode' => SEPARATEGROUPS,
        ]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $motion1 = $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'type' => 'open',
            'groupid' => $group1->id,
        ]);
        $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'parent' => $motion1->get('id'),
            'type' => 'resolve',
            'groupid' => $group1->id,
        ]);

        // Do the indexing of all 3 pages.
        $rs = $searcharea->get_recordset_by_timestamp(0);
        $results = [];
        foreach ($rs as $rec) {
            $results[] = $rec;
        }
        $rs->close();
        $this->assertCount(1, $results);

        // Check the document has the correct groupid.
        $doc = $searcharea->get_document($results[0]);
        $this->assertTrue($doc->is_set('groupid'));
        $this->assertEquals($group1->id, $doc->get('groupid'));

        // While we're here, also test that the search area requests restriction by group.
        $modinfo = get_fast_modinfo($course1);
        $this->assertTrue($searcharea->restrict_cm_access_by_group($modinfo->get_cm($plenum->cmid)));

        // In visible groups mode, it won't request restriction by group.
        set_coursemodule_groupmode($plenum->cmid, VISIBLEGROUPS);
        $modinfo = get_fast_modinfo($course1);
        $this->assertFalse($searcharea->restrict_cm_access_by_group($modinfo->get_cm($plenum->cmid)));
    }

    /**
     * Check motion check access.
     *
     * @return void
     */
    public function test_motion_check_access(): void {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        \testable_core_search::instance();
        $searchareaid = \core_search\manager::generate_areaid('mod_plenum', 'motion');

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($searchareaid);

        $this->assertInstanceOf('\mod_plenum\search\motion', $searcharea);

        $activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');
        $course1 = self::getDataGenerator()->create_course();

        $plenum = $this->getDataGenerator()->create_module('plenum', ['course' => $course1->id]);
        $motion1 = $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'type' => 'open',
        ]);
        $motion2 = $activitygenerator->create_motion([
            'plenumid' => $plenum->cmid,
            'parent' => $motion1->get('id'),
            'type' => 'resolve',
            'resolution' => 'Hipopotamus',
        ]);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');

        $this->setAdminUser();
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($motion2->get('id')));

        $this->setUser($user1);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($motion2->get('id')));

        $this->setUser($user2);
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($motion2->get('id')));

        $this->assertEquals(\core_search\manager::ACCESS_DELETED, $searcharea->check_access($motion2->get('id') + 10));
    }
}
