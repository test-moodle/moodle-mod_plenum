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
 * Prints an instance of mod_plenum.
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_plenum\motion;
use mod_plenum\output\sessions;

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

if ($id) {
    $motion = new motion($id);
    $type = $motion->get('type');
    $classname = "plenumtype_$type\\output\\main";
    $moduleinstance = $DB->get_record('plenum', ['id' => $motion->get('plenum')], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('plenum', $moduleinstance->id, $moduleinstance->course, false, MUST_EXIST);
} else {
    $cmid = required_param('cmid', PARAM_INT);
    $cm = get_coursemodule_from_id('plenum', $cmid, null, false, MUST_EXIST);
    $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);
}
$course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

if (!empty($motion)) {
    $event = \mod_plenum\event\motion_viewed::create([
        'objectid' => $motion->get('id'),
        'context' => $modulecontext,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('plenum', $moduleinstance);
    $event->trigger();
}
if ($id) {
    $PAGE->set_url('/mod/plenum/motion.php', ['id' => $motion->get('id')]);
} else {
    $PAGE->set_url('/mod/plenum/motion.php', ['cmid' => $cmid]);
}
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

if (empty($id)) {
    echo $OUTPUT->header();

    groups_print_activity_menu($cm, $PAGE->url);

    $sessions = new sessions($modulecontext, $cm, $moduleinstance);
    echo $OUTPUT->render($sessions);
} else if (class_exists($classname)) {
    if ($groupid = $motion->get('groupid')) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $PAGE->context)) {
            if (!groups_is_member($groupid)) {
                throw new moodle_exception('notmemberofgroup');
            }
        }
    }
    $main = new $classname($modulecontext, $motion);
    echo $OUTPUT->header();

    groups_print_activity_menu($cm, $PAGE->url);

    echo $OUTPUT->render($main);

    if (class_exists("\\plenumform_$moduleinstance->form\\output\\motion")) {
        $classname = "\\plenumform_$moduleinstance->form\\output\\motion";
        $speakers = new $classname($motion, $modulecontext);
        $PAGE->requires->js_call_amd('plenumform_major/view_speaker', 'init', []);

        echo  $OUTPUT->render($speakers);
    }
} else {
    $data = (object)$motion->to_record();
    $previous = new motion($motion->get('parent'));
    $data->previous = [
        'id' => $previous->get('id'),
        'type' => get_string('pluginname', 'plenumtype_' . $previous->get('type')),
        'name' => $previous->get_data()->name ?? '',
        'pluginname' => get_string('pluginname', 'plenumtype_' . $previous->get('type')),
        'url' => (new moodle_url('/mod/plenum/motion.php', ['id' => $previous->get('id')]))->out(),
    ];
    $data->pluginname = get_string('pluginname', 'plenumtype_' . $motion->get('type'));
    $classname = '\\plenumtype_' . $motion->get('type') . '\\type';
    $type = new $classname($motion, $modulecontext, $cm);
    echo $OUTPUT->header();

    groups_print_activity_menu($cm, $PAGE->url);

    echo $OUTPUT->render_from_template(
        'mod_plenum/view_motion',
        (array)$data + $type->export_for_template($OUTPUT),
    );

    if (class_exists("\\plenumform_$moduleinstance->form\\output\\motion")) {
        $classname = "\\plenumform_$moduleinstance->form\\output\\motion";
        $speakers = new $classname($motion, $modulecontext);
        $PAGE->requires->js_call_amd('plenumform_major/view_speaker', 'init', []);

        echo  $OUTPUT->render($speakers);
    }
}

echo $OUTPUT->footer();

if (!empty($motion)) {
    $event = \mod_plenum\event\motion_viewed::create([
        'objectid' => $motion->get('id'),
        'context' => $modulecontext,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->trigger();
}
