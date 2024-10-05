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
 * Form to close user's own motion
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\form;

use context;
use context_user;
use core_form\dynamic_form;
use core_user;
use mod_plenum\motion;
use moodle_exception;
use moodle_url;

/**
 * Form to close user's own motion
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_motion extends dynamic_form {
    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'message', get_string('confirm'));
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
        global $USER;

        $data = (object)$this->_ajaxformdata;
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/plenum:meet', $context);
        if (
            !has_capability('mod/plenum:preside', $context)
            && ($USER->id != (new motion($data->id))->get('usercreated'))
        ) {
            require_capability('mod/plenum:preside', $context);
        }
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $USER;

        $data = (object)$this->_ajaxformdata;
        $context = $this->get_context_for_dynamic_submission();
        $motion = new motion($data->id);
        $type = $motion->get('type');
        $classname = "plenumtype_$type\\type";
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $instance = new $classname($motion, $context, $cm);
        $instance->change_status(motion::STATUS_CLOSED);

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
        $id = $this->_ajaxformdata['id'];
        $mform->setDefault('id', $id);
        $state = $this->_ajaxformdata['state'];
        $mform->setDefault('state', $state);

        $motion = new motion($id);
        $mform->addElement('static', 'action', get_string('action'), get_string('closemotion', 'mod_plenum'));
        $mform->addElement(
            'static',
            'motiontype',
            get_string('motiontype', 'mod_plenum'),
            get_string('pluginname', 'plenumtype_' . $motion->get('type'))
        );
        $mform->addElement('static', 'user', get_string('user'), fullname(core_user::get_user($motion->get('usercreated'))));
        $mform->addElement(
            'static',
            'timecreated',
            get_string('timecreated'),
            userdate(
                $motion->get('timecreated'),
                get_string('strftimedaytime', 'langconfig')
            )
        );
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
