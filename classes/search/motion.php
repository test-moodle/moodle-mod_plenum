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
 * Search area for Plenary meeting motions
 *
 * @package mod_plenum
 * @copyright 2024 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\search;

use context;
use context_module;
use core_search\document;
use core_search\document_icon;
use core_search\manager;
use core_search\moodle_recordset;

/**
 * Search area for Plenary meeting motions
 *
 * @package mod_plenum
 * @copyright 2024 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motion extends \core_search\base_mod {
    /**
     * Returns recordset containing required data for indexing glossary entries.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, ?context $context = null) {
        global $DB;

         [$contextjoin, $contextparams] = $this->get_context_restriction_sql(
             $context,
             'plenum',
             'p'
         );
        if ($contextjoin === null) {
            return null;
        }

        $sql = "SELECT pm.*, p.course, c.id AS contextid FROM {plenum_motion} pm
                  JOIN {plenum} p ON p.id = pm.plenum
          $contextjoin
                  JOIN {course_modules} cm ON p.id = cm.instance
                  JOIN {context} c ON cm.id = c.instanceid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE pm.type IN ('amend', 'resolve')
                       AND pm.timemodified >= ?
                       AND c.contextlevel = ?
                       AND m.name = 'plenum'
              ORDER BY pm.timemodified ASC";
        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom, CONTEXT_MODULE]));
    }

    /**
     * Returns the document related with the provided record.
     *
     * This method receives a record with the document id and other info returned by get_recordset_by_timestamp
     * or get_recordset_by_contexts that might be useful here. The idea is to restrict database queries to
     * minimum as this function will be called for each document to index. As an alternative, use cached data.
     *
     * Internally it should use \core_search\document to standarise the documents before sending them to the search engine.
     *
     * Search areas should send plain text to the search engine, use the following function to convert any user
     * input data to plain text: content_to_text
     *
     * Valid keys for the options array are:
     *     indexfiles => File indexing is enabled if true.
     *     lastindexedtime => The last time this area was indexed. 0 if never indexed.
     *
     * The lastindexedtime value is not set if indexing a specific context rather than the whole
     * system.
     *
     * @param \stdClass $record A record containing, at least, the indexed document id and a modified timestamp
     * @param array     $options Options for document creation
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        // Create empty document.
        $doc = \core_search\document_factory::instance(
            $record->id,
            $this->componentname,
            $this->areaname
        );

        // Get stdclass object with data from DB.
        $data = json_decode($record->plugindata ?: '{}');

        // Get content.
        $content = content_to_text($data->resolution ?? ($data->amendment ?? ''), FORMAT_MOODLE);
        $doc->set('content', $content);

        if (!empty($data->name)) {
            // If there is a name, use it as title.
            $doc->set('title', content_to_text($data->name, false));
        } else {
            // If there is no name, use the content text again.
            $doc->set('title', shorten_text($content));
        }

        // Set standard fields.
        $doc->set('contextid', $record->contextid);
        $doc->set('type', \core_search\manager::TYPE_TEXT);
        $doc->set('courseid', $record->course);
        if ($record->groupid > 0) {
            $doc->set('groupid', $record->groupid);
        }
        $doc->set('modified', $record->timemodified);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);

        // Mark document new if appropriate.
        if (
            isset($options['lastindexedtime']) &&
                ($options['lastindexedtime'] < $record->timecreated)
        ) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Returns a url to the document, it might match self::get_context_url().
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        return new \moodle_url('/mod/plenum/motion.php', ['id' => $doc->get('itemid')]);
    }

    /**
     * Whether the user can access the document or not.
     *
     * @throws \dml_missing_record_exception
     * @throws \dml_exception
     * @param int $id Glossary entry id
     * @return bool
     */
    public function check_access($id) {
        try {
            $motion = new \mod_plenum\motion($id);
            $cm = get_coursemodule_from_instance('plenum', $motion->get('plenum'), 0, false, MUST_EXIST);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $context = context_module::instance($cm->id);
        if (!has_capability('mod/plenum:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns an icon instance for the document.
     *
     * @param \core_search\document $doc
     * @return \core_search\document_icon
     */
    public function get_doc_icon(document $doc): document_icon {
        return new document_icon('i/marker');
    }

    /**
     * Link to the Plenary meeting.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/plenum/view.php', ['id' => $contextmodule->instanceid]);
    }

    /**
     * Return the context info required to index files for
     * this search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        $fileareas = ['attachments'];

        return $fileareas;
    }

    /**
     * Confirms that data entries support group restrictions.
     *
     * @return bool True
     */
    public function supports_group_restriction() {
        return true;
    }
}
