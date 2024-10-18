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
 * Form to change motion status
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\form;

defined('MOODLE_INTERNAL') || die();

use context;
use context_user;
use core_form\dynamic_form;
use core_user;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;
use mod_plenum\output\activity_report;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Form to forum
 *
 * @package     mod_plenum
 * @copyright   2024 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grader extends dynamic_form {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('header', 'report', get_string('report'));
    }

    /**
     * Return form context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {

        $contextid = json_decode($this->_ajaxformdata['contextid']);

        return context::instance_by_id($contextid);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/plenum:grade', $context);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $data = (object)$this->_ajaxformdata;
        $context = $this->get_context_for_dynamic_submission();

        $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum($context);
        $plenum->save_grade($data->userid, $data);

        return '';
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        global $OUTPUT;

        $mform = $this->_form;

        $context = $this->get_context_for_dynamic_submission();
        $mform->setDefault('contextid', $context->id);
        $data = (object)$this->_ajaxformdata;

        $users = array_map(function ($user) {
            return fullname($user);
        }, get_enrolled_users($context));
        $data->userid = $data->userid ?? array_keys($users)[0];
        if (!$data->userid) {
            return;
        }

        $mform->insertElementBefore(
            $mform->createElement(
                'autocomplete',
                'userid',
                get_string('user'),
                $users
            ),
            'contextid'
        );

        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $report = new activity_report($context, $data->userid);

        $mform->addElement('html', $OUTPUT->render($report));

        $mform->setDefault('userid', $data->userid);
        $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum($context, $cm);
        $params = [
            'plenum' => $cm->instance,
            'userid' => $data->userid,
        ];
        $plenum->add_grade_form_elements($mform, $data, $params);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $context = $this->get_context_for_dynamic_submission();

        $url = new moodle_url('/mod/plenum/view.php', ['id' => $context->instanceid]);

        return $url;
    }
}
