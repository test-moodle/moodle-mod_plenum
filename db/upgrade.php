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
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_plenum
 * @category    upgrade
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_plenum upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_plenum_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023102810) {
        // Define field form to be added to plenum.
        $table = new xmldb_table('plenum');
        $field = new xmldb_field('form', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'introformat');

        // Conditionally launch add field form.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Plenum savepoint reached.
        upgrade_mod_savepoint(true, 2023102810, 'plenum');
    }

    if ($oldversion < 2023102825) {
        // Define field grade to be added to plenum.
        $table = new xmldb_table('plenum');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field completionmotions to be added to plenum.
        $table = new xmldb_table('plenum');
        $field = new xmldb_field('completionmotions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field completionmotions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table plenum_grades to be created.
        $table = new xmldb_table('plenum_grades');

        // Adding fields to table plenum_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('plenum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('feedbackformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('grader', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table plenum_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('plenum', XMLDB_KEY_FOREIGN, ['plenum'], 'plenum', ['id']);

        // Adding indexes to table plenum_grades.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('plenumusergrade', XMLDB_INDEX_UNIQUE, ['plenum', 'itemnumber', 'userid']);

        // Conditionally launch create table for plenum_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Plenum savepoint reached.
        upgrade_mod_savepoint(true, 2023102825, 'plenum');
    }

    return true;
}
