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
 * Privacy Subsystem implementation for mod_plenum.
 *
 * @package    mod_plenum
 * @category   privacy
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\privacy;

use core_grades\component_gradeitem as gradeitem;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for mod_plenum.
 *
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        // The 'plenum_grades' table stores grade data.
        $collection->add_database_table('plenum_grades', [
            'userid' => 'privacy:metadata:plenum_grades:userid',
            'plenum' => 'privacy:metadata:plenum_grades:plenum',
            'grade' => 'privacy:metadata:plenum_grades:grade',
            'grader' => 'privacy:metadata:plenum_grades:grader',
            'feedback' => 'privacy:metadata:plenum_grades:feedback',
            'feedbackformat' => 'privacy:metadata:plenum_grades:feedbackformat',
            'timecreated' => 'privacy:metadata:plenum_grades:timecreated',
            'timemodified' => 'privacy:metadata:plenum_grades:timemodified',
        ], 'privacy:metadata:plenum_grades');

        return $collection->add_database_table(
            'plenum_motion',
            [
                'plugindata' => 'privacy:metadata:plenum_motion:plugindata',
                'status' => 'privacy:metadata:plenum_motion:status',
                'timecreated' => 'privacy:metadata:plenum_motion:timecreated',
                'timemodified' => 'privacy:metadata:plenum_motion:timemodified',
                'type' => 'privacy:metadata:plenum_motion:type',
                'usercreated' => 'privacy:metadata:plenum_motion:usercreated',
                'usermodified' => 'privacy:metadata:plenum_motion:usermodified',
            ],
            'privacy:metadata:plenum_motion'
        );
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {plenum_motion} pm
                  JOIN {modules} m
                    ON m.name = :activityname
                  JOIN {course_modules} cm
                    ON cm.instance = pm.plenum
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE pm.usercreated = :usercreated OR pm.usermodified = :usermodified";

        $params = [
            'activityname' => 'plenum',
            'modlevel' => CONTEXT_MODULE,
            'usercreated' => $userid,
            'usermodified' => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        // Plenum meeting grades.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {plenum} p ON p.id = cm.instance
                  JOIN {plenum_grades} pg ON pg.plenum = p.id
                 WHERE pg.userid = :userid
        ";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'plenum',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT pm.usercreated
                  FROM {plenum_motion} pm
                  JOIN {modules} m
                    ON m.name = 'plenum'
                  JOIN {course_modules} cm
                    ON cm.instance = pm.plenum
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";

        $params = ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id];

        $userlist->add_from_sql('usercreated', $sql, $params);

        $sql = "SELECT pm.usermodified
                  FROM {plenum_motion} pm
                  JOIN {modules} m
                    ON m.name = 'plenum'
                  JOIN {course_modules} cm
                    ON cm.instance = pm.plenum
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";

        $userlist->add_from_sql('usermodified', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // Remove contexts different from CONTEXT_MODULE.
        $contexts = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        // Get motion data.
        [$insql, $inparams] = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $sql = "SELECT pm.id,
                       pm.plugindata,
                       pm.status,
                       pm.type,
                       pm.timecreated,
                       pm.timemodified,
                       pm.usercreated,
                       pm.usermodified,
                       cm.id AS cmid,
                       ctx.id as contextid
                  FROM {plenum_motion} pm
                  JOIN {course_modules} cm
                    ON cm.instance = pm.plenum
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                       AND (pm.usercreated = :usercreated OR pm.usermodified = :usermodified)
              ORDER BY cmid, pm.timecreated";
        $params = array_merge($inparams, [
            'usercreated' => $userid,
            'usermodified' => $userid,
        ]);

        $motiondata = [];
        $motions = $DB->get_recordset_sql($sql, $params);

        // Plenary meeting grades.
        $sql = "SELECT
                    c.id AS contextid,
                    pg.grade AS grade,
                    p.grade AS gradetype,
                    p.feedback,
                    p.feedbackformat
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {plenum} p ON p.id = cm.instance
                  JOIN {plenum_grades} pg ON pg.plenum = p.id
                 WHERE (
                    pg.userid = :userid AND
                    c.id {$insql}
                )
        ";
        $params['userid'] = $userid;
        $grades = $DB->get_records_sql_menu($sql, $params);

        $lastcmid = null;
        foreach ($motions as $motion) {
            if ($lastcmid != $motion->cmid) {
                if (!empty($motiondata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_motion_data_for_user($motiondata, $context, $user);
                }
                $motiondata = [
                    'motions' => [],
                    'cmid' => $motion->cmid,
                ];
                $lastcmid = $motion->cmid;
            }
            $motiondata['motions'][] = (object)[
                'plugindata' => $motion->plugindata,
                'status' => $motion->status,
                'usercreated' => $motion->usercreated,
                'usermodified' => $motion->usermodified,
                'timecreated' => transform::datetime($motion->timecreated),
                'timemodified' => transform::datetime($motion->timemodified),
                'type' => $motion->type,
            ];
        }

        // Write last activity.
        if (!empty($motiondata)) {
            $context = \context_module::instance($lastcmid);
            self::export_motion_data_for_user($motiondata, $context, $user);
        }

        $motions->close();

        $sql = "SELECT
                    c.id AS contextid,
                    p.*,
                    cm.id AS cmid
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {plenum} p ON p.id = cm.instance
                 WHERE (
                    c.id {$insql}
                )
        ";

        $plenums = $DB->get_recordset_sql($sql, $inparams);
        foreach ($plenums as $plenum) {
            if (key_exists($plenum->contextid, $grades)) {
                static::export_grading_data($userid, $plenum, $grades[$plenum->contextid]);
            }
        }
    }

    /**
     * Export the supplied personal data for a single plenary meeting activity, along with any generic data or area files.
     *
     * @param array $motiondata the personal data to export for the choice.
     * @param \context_module $context the context of the choice.
     * @param \stdClass $user the user record
     */
    protected static function export_motion_data_for_user(array $motiondata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the plenary meeting.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with motion data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $motiondata);
        writer::with_context($context)->export_data([
            get_string('privacy:motions', 'mod_plenum'),
        ], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Get the course module.
        if (!$cm = get_coursemodule_from_id('forum', $context->instanceid)) {
            return;
        }

        $DB->delete_records_select(
            'plenum_motion',
            "plenum IN (
                SELECT cm.instance
                  FROM {course_module}
                  JOIN {module} m ON m.id = cm.module
                  JOIN {context} c ON c.instanceid = cm.id
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid",
            [
                'contextlevel' => CONTEXT_MODULE,
                'contextid' => $context->id,
            ]
        );

        // Delete advanced grading information.
        $gradingmanager = get_grading_manager($context, 'mod_plenum', 'plenum');
        $controller = $gradingmanager->get_active_controller();
        if (isset($controller)) {
            \core_grading\privacy\provider::delete_instance_data($context);
        }

        $DB->delete_records('plenum_grades', ['plenum' => $cm->instance]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $userids = $userlist->get_userids();
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
            'modulename' => 'plenum',
        ] + $userparams;
        $DB->delete_records_select(
            'plenum_motion',
            "status = 0 AND plenum IN (
                SELECT cm.instance
                  FROM {course_module}
                  JOIN {module} m ON m.id = cm.module
                  JOIN {context} c ON c.instanceid = cm.id
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid
                   AND m.name = :modulename
             ) AND usercreated $usersql",
            $params
        );

        // Delete advanced grading information.
        $sql = "plenum IN (
                SELECT cm.instance
                  FROM {course_module}
                  JOIN {module} m ON m.id = cm.module
                  JOIN {context} c ON c.instanceid = cm.id
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid
                   AND m.name = :modulename
             ) AND userid $usersql";
        $grades = $DB->get_records_select('plenum_grades', $sql, $params);
        $DB->delete_records_select('plenum_grades', $sql, $params);
        $gradeids = array_keys($grades);
        $gradingmanager = get_grading_manager($context, 'mod_plenum', 'plenum');
        $controller = $gradingmanager->get_active_controller();
        if (isset($controller)) {
            // Careful here, if no gradeids are provided then all data is deleted for the context.
            if (!empty($gradeids)) {
                \core_grading\privacy\provider::delete_data_for_instances($context, $gradeids);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $contextids = [];
        foreach ($contextlist->get_contexts() as $context) {
            $contextids[] = $context->id;
        }
        [$sql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $params += [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'modulename' => 'plenum',
        ];
        $DB->delete_records_select(
            'plenum_motion',
            "status = 0 AND plenum IN (
                SELECT cm.instance
                  FROM {course_module}
                  JOIN {module} m ON m.id = cm.module
                  JOIN {context} c ON c.instanceid = cm.id
                 WHERE c.contextlevel = :contextlevel
                   AND c.id $sql
                   AND m.name = :modulename
             ) AND userid = :userid",
            $params
        );

        // Handle any advanced grading method data first.
        $sql = "plenum IN (
                SELECT cm.instance
                  FROM {course_module}
                  JOIN {module} m ON m.id = cm.module
                  JOIN {context} c ON c.instanceid = cm.id
                 WHERE c.contextlevel = :contextlevel
                   AND c.id $sql
                   AND m.name = :modulename
             ) AND userid = :userid";
        $grades = $DB->get_records('plenum_grades', $sql, $params);
        $gradingmanager = get_grading_manager($context, 'plenum_grades', 'plenum');
        $controller = $gradingmanager->get_active_controller();
        foreach ($grades as $grade) {
            // Delete advanced grading information.
            if (isset($controller)) {
                \core_grading\privacy\provider::delete_instance_data($context, $grade->id);
            }
        }
        // Advanced grading methods have been cleared, lets clear our module now.
        $DB->delete_records('plenum_grades', $sql, $params);
    }

    /**
     * Export grade data for activity
     *
     * @param int $userid User id
     * @param \stdClass $plenum Activity record
     * @param int $grade Grade
     *
     */
    protected static function export_grading_data(int $userid, \stdClass $plenum, int $grade) {
        global $USER;
        if (null !== $grade) {
            $context = \context_module::instance($plenum->cmid);
            $exportpath = array_merge(
                [],
                [get_string('privacy:metadata:plenum_grades', 'mod_plenum')]
            );
            $gradingmanager = get_grading_manager($context, 'mod_plenum', 'plenum');
            $controller = $gradingmanager->get_active_controller();

            // Check for advanced grading and retrieve that information.
            if (isset($controller)) {
                $gradeduser = \core_user::get_user($userid);
                // Fetch the gradeitem instance.
                $gradeitem = gradeitem::instance($controller->get_component(), $context, $controller->get_area());
                $grade = $gradeitem->get_grade_for_user($gradeduser, $USER);
                $controllercontext = $controller->get_context();
                \core_grading\privacy\provider::export_item_data($controllercontext, $grade->id, $exportpath);
            } else {
                self::export_grade_data($grade, $context, $plenum, $exportpath);
            }
            // The user has a grade for this plenum.
            writer::with_context(\context_module::instance($plenum->cmid))->export_metadata(
                $exportpath,
                'gradingenabled',
                1,
                get_string('privacy:metadata:plenum_grades:grade', 'mod_plenum')
            );

            return true;
        }

        return false;
    }

    /**
     * Export data for simple grading
     *
     * @param int $grade
     * @param \context $context
     * @param \stdClass $plenum Activity record
     * @param array $path
     */
    protected static function export_grade_data(int $grade, \context $context, \stdClass $plenum, array $path) {
        $gradedata = (object)[
            'plenum' => $plenum->name,
            'grade' => $grade,
        ];

        writer::with_context($context)
            ->export_data($path, $gradedata);
    }
}
