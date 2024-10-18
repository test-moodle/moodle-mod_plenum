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
 * Library of interface functions and constants.
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_plenum\file_info_container;
use mod_plenum\motion;
use mod_plenum\output\motions;
use mod_plenum\plugininfo\plenum;

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function plenum_supports($feature) {
    switch ($feature) {
        case FEATURE_ADVANCED_GRADING:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return null;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_plenum into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_plenum_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function plenum_add_instance($moduleinstance, $mform = null) {
    $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum(context_module::instance($moduleinstance->coursemodule));

    return $plenum->add_instance($moduleinstance, $mform);
}

/**
 * Updates an instance of the mod_plenum in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_plenum_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function plenum_update_instance($moduleinstance, $mform = null) {
    $id = $moduleinstance->instance;
    $cm = get_coursemodule_from_instance('plenum', $id, 0, false, MUST_EXIST);
    $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum(context_module::instance($cm->id), $cm);

    return $plenum->update_instance($moduleinstance, $mform);
}

/**
 * Removes an instance of the mod_plenum from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function plenum_delete_instance($id) {
    if (!$cm = get_coursemodule_from_instance('plenum', $id)) {
        return false;
    }

    $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum(context_module::instance($cm->id), $cm);

    return $plenum->delete_instance();
}

/**
 * Is a given scale used by the instance of mod_plenum?
 *
 * This function returns if a scale is being used by one mod_plenum
 * if it has support for grading and scales.
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by the given mod_plenum instance.
 */
function plenum_scale_used($moduleinstanceid, $scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('plenum', ['id' => $moduleinstanceid, 'grade' => -$scaleid])) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of mod_plenum.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any mod_plenum instance.
 */
function plenum_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('plenum', ['grade' => -$scaleid])) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given mod_plenum instance.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param bool $reset Reset grades in the gradebook.
 * @return void.
 */
function plenum_grade_item_update($moduleinstance, $reset = false) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => get_string('gradeitemnameforplenum', 'plenum', $moduleinstance),
        'itemid' => 0,
    ];

    if ($moduleinstance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moduleinstance->grade;
        $item['grademin']  = 0;
    } else if ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('/mod/plenum', $moduleinstance->course, 'mod', 'plenum', $moduleinstance->id, 0, null, $item);

    $item = [
        'itemname' => get_string('gradeitemnameformotion', 'plenum', $moduleinstance),
        'itemid' => 1,
        'gradetype' => GRADE_TYPE_NONE,
    ];
    grade_update('/mod/plenum', $moduleinstance->course, 'mod', 'plenum', $moduleinstance->id, 1, null, $item);
}

/**
 * Delete grade item for given mod_plenum instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return grade_item.
 */
function plenum_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        '/mod/plenum',
        $moduleinstance->course,
        'mod',
        'plenum',
        $moduleinstance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Update mod_plenum grades in the gradebook.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function plenum_update_grades($moduleinstance, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($moduleinstance->grade) {
        $sql = <<<EOF
SELECT
    g.userid,
    0 as datesubmitted,
    g.grade as rawgrade,
    g.timemodified as dategraded,
    g.feedback,
    g.feedbackformat
  FROM {plenum} p
  JOIN {plenum_grades} g ON g.plenum = p.id
 WHERE p.id = :plenumid
EOF;

        $params = [
            'plenumid' => $moduleinstance->id,
        ];

        if ($userid) {
            $sql .= " AND g.userid = :userid";
            $params['userid'] = $userid;
        }

        $plenumgrades = [];
        if ($grades = $DB->get_recordset_sql($sql, $params)) {
            foreach ($grades as $userid => $grade) {
                if ($grade->rawgrade != -1) {
                    $plenumgrades[$userid] = $grade;
                }
            }
            $grades->close();
        }

        // Populate array of grade objects indexed by userid.
        grade_update('/mod/plenum', $moduleinstance->course, 'mod', 'plenum', $moduleinstance->id, 0, $plenumgrades);
    }
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@see file_browser::get_file_info_context_module()}.
 *
 * @package     mod_plenum
 * @category    files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return string[].
 */
function plenum_get_file_areas($course, $cm, $context) {
    return [
        'attachments' => get_string('motionattachments', 'mod_plenum'),
        'speaker' => get_string('recordings', 'mod_plenum'),
    ];
}

/**
 * File browsing support for mod_plenum file areas.
 *
 * @package     mod_plenum
 * @category    files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info Instance or null if not found.
 */
function plenum_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return file_info_container::get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename);
}

/**
 * Serves the files from the mod_plenum file areas.
 *
 * @package     mod_plenum
 * @category    files
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param stdClass $context The mod_plenum's context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 */
function mod_plenum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);
    require_capability('mod/plenum:meet', $context);

    $id = array_shift($args);

    if (!in_array($filearea, ['attachments'])) {
        send_file_not_found();
    }

    $motion = new motion($id);
    if (
        !has_capability('moodle/site:accessallgroups', $context)
        && !groups_is_member($motion->get('groupid'))
        && (groups_get_activity_groupmode($cm) != NOGROUPS)
    ) {
        send_file_not_found();
    }

    $fs = get_file_storage();

    foreach ($fs->get_area_files($context->id, 'mod_plenum', $filearea, $id) as $file) {
        if (!$file->is_directory() && $file->get_filename() == reset($args)) {
            $lifetime = DAYSECS;
            send_stored_file($file, $lifetime, 0, true, $options);
        }
    }

    send_file_not_found();
}

/**
 * Extends the global navigation tree by adding mod_plenum nodes if there is a relevant content.
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $plenumnode An object representing the navigation tree node.
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function plenum_extend_navigation($plenumnode, $course, $module, $cm) {
}

/**
 * Extends the settings navigation with the mod_plenum settings.
 *
 * This function is called when the context for the page is a mod_plenum module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@see settings_navigation}
 * @param navigation_node $plenumnode {@see navigation_node}
 */
function plenum_extend_settings_navigation($settingsnav, $plenumnode = null) {
    global $PAGE;

    if (has_capability('mod/plenum:preside', $PAGE->context)) {
        $url = new \moodle_url('/mod/plenum/motion.php', ['cmid' => $PAGE->context->instanceid]);
        $plenumnode->add(get_string('sessions', 'mod_plenum'), $url);
    }
}

/**
 * Serve a motion for viewing
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_plenum_output_fragment_motion($args) {
    global $DB, $OUTPUT;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    require_capability('mod/plenum:view', $context);

    [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'plenum');

    $id = $args['id'];

    $motion = new motion($id);

    if ($groupid = $motion->get('groupid')) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            if (!groups_is_member($groupid)) {
                throw new moodle_exception('notmemberofgroup');
            }
        }
    }

    $type = $motion->get('type');

    $classname = "plenumtype_$type\\output\\main";

    $mainview = new $classname($context, $motion);

    $event = \mod_plenum\event\motion_viewed::create([
        'objectid' => $motion->get('id'),
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    $form = $DB->get_field('plenum', 'form', ['id' => $motion->get('plenum')]);
    $pluginclass = "\\plenumform_$form\\output\\motion";
    if (class_exists($pluginclass)) {
        $speakers = new $pluginclass($motion, $context);
        return $OUTPUT->render($mainview) .  $OUTPUT->render($speakers);
    }

    return $OUTPUT->render($mainview);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the H5P activity.
 *
 * @param MoodleQuickForm $mform form passed by reference
 */
function plenum_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'plenumheader', get_string('modulenameplural', 'mod_plenum'));
    $mform->addElement('advcheckbox', 'reset_plenum', get_string('deleteallsessions', 'mod_plenum'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course the course object
 * @return array
 */
function plenum_reset_course_form_defaults(stdClass $course): array {
    return ['reset_plenum' => 1];
}


/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * This function will remove all H5P attempts in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array of reseting status
 */
function plenum_reset_userdata(stdClass $data): array {
    global $DB;
    $componentstr = get_string('modulenameplural', 'mod_plenum');
    $status = [];
    if (!empty($data->reset_plenum)) {
        $params = ['courseid' => $data->courseid];
        $sql = "SELECT a.id FROM {plenum} a WHERE a.course=:courseid";
        if ($activities = $DB->get_records_sql($sql, $params)) {
            foreach ($activities as $activity) {
                mod_plenum\motion::delete_all($activity->id);
            }
        }

        // Remove all grades from gradebook.
        if (empty($data->reset_gradebook_grades)) {
            plenum_reset_gradebook($data->courseid);
        }

        $status[] = [
            'component' => $componentstr,
            'item' => get_string('deleteallsessions', 'mod_plenum'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function plenum_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = [
        'mod-plenum-*'       => get_string('page-mod-plenum-x', 'plenum'),
        'mod-plenum-view'    => get_string('page-mod-plenum-view', 'plenum'),
    ];

    return $modulepagetype;
}

/**
 * Add a get_coursemodule_info function in case any plenum type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function plenum_get_coursemodule_info($coursemodule) {
    return \mod_plenum\plenum::get_coursemodule_info($coursemodule);
}

/**
 * Lists all gradable areas for the advanced grading methods gramework.
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function plenum_grading_areas_list() {
    return [
        'plenum' => get_string('grade_plenum_header', 'plenum'),
    ];
}


/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string $type optional
 */
function plenum_reset_gradebook($courseid, $type = '') {
    global $DB;

    $DB->delete_records_select(
        'plenum_grades',
        "plenum IN (SELECT id FROM {plenum} WHERE course = ?)",
        [$courseid]
    );

    foreach ($DB->get_records('plenum', ['course' => $courseid]) as $record) {
        plenum_grade_item_update($record, true);
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_plenum_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['plenum'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/plenum/view.php', ['id' => $cm->id]),
        1,
        true
    );
}
