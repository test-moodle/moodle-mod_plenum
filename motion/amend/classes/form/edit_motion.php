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
 * @package     plenumtype_amend
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumtype_amend\form;

use context;
use context_user;
use core_form\dynamic_form;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;

/**
 * Plugin edit form for motion
 *
 * @package     plenumtype_amend
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

        $mform->addElement('textarea', 'amendment', get_string('amendment', 'plenumtype_amend'));
        $mform->setType('amendment', PARAM_TEXT);
        $mform->addRule('amendment', get_string('required'), 'required', null, 'server');
        $mform->addHelpButton('amendment', 'amendment', 'plenumtype_amend');

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $options = [];
        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'mod_plenum'), $options);
    }

    /**
     * Validate form
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['amendment'])) {
            $errors['amendment'] = get_string('required');
        }
        return $errors;
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
        if ($data->warning) {
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
}
