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
 * Plugin edit form for motion
 *
 * @package     plenumtype_resolve
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_resolve\form;

use context;
use context_user;
use core_form\dynamic_form;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;

/**
 * Plugin edit form for motion
 *
 * @package     plenumtype_resolve
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_motion extends \mod_plenum\form\edit_motion {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('textarea', 'resolution', get_string('resolution', 'plenumtype_resolve'));
        $mform->setType('resolution', PARAM_TEXT);
        $mform->addRule('resolution', get_string('required'), 'required');
        $mform->addHelpButton('resolution', 'resolution', 'plenumtype_resolve');

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $options = [];
        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'mod_plenum'), $options);
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
         require_capability('mod/plenum:meet', $this->get_context_for_dynamic_submission());
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
        $draftid = $data->attachments;
        unset($data->attachments);
        $identifier = '_qf__' . $this->get_form_identifier();
        unset($data->$identifier);
        if (!empty($data->warning)) {
            $motion = $this->current_offer();
            $motion->from_record($data);
            $motion->update();
        } else {
            $motion = motion::make($context, $data);
        }

        file_save_draft_area_files(
            $draftid,
            $context->id,
            'mod_plenum',
            'attachments',
            $motion->get('id'),
            [
                'maxfiles' => 20,
            ]
        );

        return '';
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        $mform = $this->_form;

        $context = $this->get_context_for_dynamic_submission();
        $mform->setDefault('contextid', $context->id);
        $mform->setDefault('type', $this->_ajaxformdata['type']);

        $params = $this->_ajaxformdata;
        foreach ($params as $field => $value) {
            $mform->setDefault($field, $value);
        }

        if ($current = $this->current_offer()) {
            $mform->addElement('checkbox', 'warning', '', get_string('replacemotion', 'mod_plenum'));
            $draftitemid = $params['attachments'];
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'mod_plenum',
                'attachments',
                $current->get('id'),
                [
                    'maxfiles' => 20,
                ]
            );
            $mform->setDefault('attachments', $draftitemid);
        }
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
