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

namespace plenumtype_open\hook;

use stdClass;
use context_module;
use cm_info;
use moodleform;
use MoodleQuickForm;

/**
 * Hook after data in form to add more elements
 *
 * @package    plenumtype_open
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins or features to perform actions after a motion is changed in a meeting.')]
#[\core\attribute\tags('mod_plenum')]
final class after_data {
    /**
     * Constructor for the hook.
     *
     * @param stdClass $enrolinstance The enrol instance.
     * @param stdClass $userenrolmentinstance The user enrolment instance.
     */
    public function __construct(
        /** @var context_module $context Module context of meeting */
        private readonly context_module $context,
        /** @var context_module $cm Course module record */
        private readonly stdClass|cm_info $cm,
        /** @var MoodleQuickForm $mform Form for motion definition */
        private readonly MoodleQuickForm $mform,
    ) {
    }

    /**
     * Get the context
     *
     * @return context_module The meeting module context
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Get the course module record
     *
     * @return context_module The meeting module context
     */
    public function get_coursemodule(): cm_info {
        return cm_info::create($this->cm);
    }

    /**
     * Get the course module record
     *
     * @return context_module The meeting module context
     */
    public function get_form(): MoodleQuickForm {
        return $this->mform;
    }
}
