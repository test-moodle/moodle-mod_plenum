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
 * Privacy Subsystem implementation for plenumform_deft.
 *
 * @package    plenumform_deft
 * @category   privacy
 * @copyright  2024 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumform_deft\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for plenumform_deft.
 *
 * @copyright  2024 Daniel Thies
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
        $collection->add_database_table(
            'plenumform_deft_peer',
            [
                'usermodified' => 'privacy:metadata:plenumform_deft_peer:usermodified',
                'timecreated' => 'privacy:metadata:plenumform_deft_peer:timecreated',
                'timemodified' => 'privacy:metadata:plenumform_deft_peer:timemodified',
                'mute' => 'privacy:metadata:plenumform_deft_peer:mute',
                'status' => 'privacy:metadata:plenumform_deft_peer:status',
                'type' => 'privacy:metadata:plenumform_deft_peer:type',
                'uuid' => 'privacy:metadata:plenumform_deft_peer:uuid',
            ],
            'privacy:metadata:plenumform_deft_peer'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {plenumform_deft_peer} p ON p.plenum = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND usermodified = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $sql = "SELECT p.usermodified
                  FROM {plenumform_deft_peer} p
                  JOIN {context} c ON p.plenum = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('usermodified', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            if (
                $context->contextlevel != CONTEXT_MODULE
            ) {
                continue;
            }
            if (
                $records = $DB->get_records('plenumform_deft_peer', [
                    'plenum' => $context->instanceid,
                    'usermodified' => $user->id,
                ], 'id', 'id, mute, status, timecreated, timemodified, type')
            ) {
                foreach ($records as $record) {
                    $record->timecreated = \core_privacy\local\request\transform::datetime($record->timecreated);
                    $record->timemodified = \core_privacy\local\request\transform::datetime($record->timemodified);
                }
                writer::with_context($context)->export_data([
                    get_string('privacy:connections', 'plenumform_deft'),
                ], (object)$records);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $DB->delete_records(
            'plenumform_deft_peer',
            [
                'plenum' => $context->instanceid,
            ]
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'plenumform_deft');

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $userids = $userlist->get_userids();
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'params', true, true);

        $DB->delete_records(
            'plenumform_deft_peer',
            "usermodified $usersql AND plenum = :instanceid",
            [
                'instanceid' => $context->instanceid,
            ] + $userparams
        );
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

        $instanceids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                $instanceids[] = $context->instanceid;
            }
        }
        if (empty($instanceids)) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);

        $DB->delete_records_select(
            'plenumform_deft_peer',
            "plenum $sql AND usermodified = :usermodified",
            ['usermodified' => $user->id] + $params
        );
    }
}
