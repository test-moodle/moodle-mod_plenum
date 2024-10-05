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

/**
 * Display information about all the mod_plenum modules in the requested course.
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$coursecontext = context_course::instance($course->id);

$event = \mod_plenum\event\course_module_instance_list_viewed::create([
    'context' => $coursecontext,
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/plenum/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'mod_plenum');
echo $OUTPUT->heading($modulenameplural);

$plenums = get_all_instances_in_course('plenum', $course);
$usesections = course_format_uses_sections($course->format);

if (empty($plenums)) {
    notice(get_string('noplenuminstances', 'mod_plenum'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$sessionshdr = has_capability('mod/plenum:preside', $coursecontext) ? get_string('sessions', 'mod_plenum') : '';
if ($course->format == 'weeks') {
    $table->head  = [
        get_string('week'),
        get_string('name'),
        get_string('moduleintro', 'core'),
        $sessionshdr,
    ];
    $table->align = ['center', 'left', 'left', 'center'];
} else if ($course->format == 'topics') {
    $table->head  = [
        get_string('topic'),
        get_string('name'),
        get_string('moduleintro', 'core'),
        $sessionshdr,
    ];
    $table->align = ['center', 'left', 'left', 'center'];
} else if ($usesections) {
    $strsectionname = get_string('sectionname', "format_$course->format");
    $table->head  = [
        $strsectionname,
        get_string('name'),
        get_string('moduleintro', 'core'),
        $sessionshdr,
    ];
    $table->align = ['center', 'left', 'left', 'center'];
} else {
    $table->head  = [get_string('name'), get_string('moduleintro', 'core'), $sessionshdr];
    $table->align = ['left', 'left', 'center'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($plenums as $plenum) {
    $cm = $modinfo->cms[$plenum->coursemodule];

    if ($usesections) {
        $printsection = '';
        if ($plenum->section !== $currentsection) {
            if ($plenum->section) {
                $printsection = get_section_name($course, $plenum->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $plenum->section;
        }
    } else {
        $printsection = html_writer::span(userdate($plenum->timemodified), 'smallinfo');
    }

    if (!$plenum->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/plenum/view.php', ['id' => $plenum->coursemodule]),
            format_string($plenum->name, true),
            ['class' => 'dimmed']
        );
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/plenum/view.php', ['id' => $plenum->coursemodule]),
            format_string($plenum->name, true)
        );
    }

    if (
        has_capability('mod/plenum:preside', $coursecontext)
        && $sessions = count(\mod_plenum\motion::get_records([
            'plenum' => $plenum->id,
            'type' => 'open',
        ]))
    ) {
        $sessions = html_writer::link(
            new moodle_url('/mod/plenum/motion.php', ['cmid' => $plenum->coursemodule]),
            $sessions
        );
    } else {
        $sessions = '';
    }

    if ($course->format == 'weeks') {
        $table->data[] = [$plenum->section, $link, $sessions];
    } else if ($usesections) {
        $table->data[] = [
            $printsection,
            $link,
            format_module_intro('plenum', $plenum, $cm->id),
            $sessions,
        ];
    } else {
        $table->data[] = [$link, $sessions];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
