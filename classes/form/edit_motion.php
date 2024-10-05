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
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\form;

use context;
use context_user;
use core_form\dynamic_form;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;

/**
 * Plugin edit form for motion
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_motion extends dynamic_form {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
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
        $data = new \stdClass();
        $mform = $this->_form;
        foreach ($this->_ajaxformdata as $key => $value) {
            if ($mform->elementExists($key)) {
                $data->$key = $value;
            }
        }
        $context = $this->get_context_for_dynamic_submission();
        if (!empty($data->warning)) {
            $motion = $this->current_offer();
            $data->plugindata = json_encode(array_diff_key((array)$data, [
                'contextid' => null,
                'warning' => null,
                '_qf__' . $this->get_form_identifier() => null,
            ]));
            $motion->from_record($data);
            $motion->update();
        } else {
            if (!$data) {
                $data = (object)$this->_ajaxformdata;
            }
            $identifier = '_qf__' . $this->get_form_identifier();
            unset($data->$identifier);
            motion::make($context, $data, $this->_customdata['groupid'] ?? null);
        }

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
        $mform->setDefault('warning', $this->_ajaxformdata['warning'] ?? 0);

        $params = $this->_ajaxformdata;
        foreach ($params as $field => $value) {
            if ($field !== 'jsondata') {
                $mform->setDefault($field, $this->_ajaxformdata[$field]);
            }
        }

        if ($this->current_offer()) {
            $mform->addElement('checkbox', 'warning', '', get_string('replacemotion', 'mod_plenum'));
        }
    }

    /**
     * Get the motion current user is offering
     *
     * @return motion|null
     */
    public function current_offer(): ?motion {
        global $USER;

        $groupid = $this->_customdata['groupid'] ?? null;

        $context = $this->get_context_for_dynamic_submission();
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        if (!$immediate = motion::immediate_pending($context, $groupid)) {
            return null;
        }
        if ($immediate->get('type') == 'speak') {
            $immediate = new motion($immediate->get('parent'));
        }
        $motions = motion::get_records([
            'plenum' => $cm->instance,
            'usercreated' => $USER->id,
            'status' => motion::STATUS_DRAFT,
            'parent' => $immediate->get('id'),
            'groupid' => groups_get_activity_group($cm),
        ]);

        return empty($motions) ? null : end($motions);
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

    /**
     * Validate form
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->current_offer() && empty($data['warning'])) {
            $errors['warning'] = get_string('replacemotionwarning', 'mod_plenum');
        }
        return $errors;
    }

    /**
     * Get other data for mobile form
     *
     * @return array
     */
    public function other_data() {
        $form = $this->_form;
        $otherdata = [];
        foreach (array_keys($this->_ajaxformdata) as $key) {
            if ($form->elementExists($key)) {
                $otherdata[$key] = $this->_ajaxformdata[$key];
            }
        }
        return $otherdata;
    }
}
