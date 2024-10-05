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

/**
 * PHPUnit plenum generator testcase
 *
 * @package    mod_plenum
 * @category   test
 * @copyright  2024 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends \advanced_testcase {
    /**
     * Test on Plenary meeting activity creation.
     */
    public function test_generator() {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('plenum'));

        $course = $this->getDataGenerator()->create_course();

        /** @var mod_plenum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_plenum');
        $this->assertInstanceOf('mod_plenum_generator', $generator);
        $this->assertEquals('plenum', $generator->get_modulename());

        $generator->create_instance(['course' => $course->id]);
        $generator->create_instance(['course' => $course->id]);
        $plenum = $generator->create_instance(['course' => $course->id]);
        $this->assertEquals(3, $DB->count_records('plenum'));

        $cm = get_coursemodule_from_instance('plenum', $plenum->id);
        $this->assertEquals($plenum->id, $cm->instance);
        $this->assertEquals('plenum', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = \context_module::instance($cm->id);
        $this->assertEquals($plenum->cmid, $context->instanceid);
    }
}
