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
 * The main mod_plenum configuration form.
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_plenum\plugininfo\plenumform;

/**
 * Module instance settings form.
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_plenum_mod_form extends moodleform_mod {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $PAGE;

        $mform = $this->_form;
        $PAGE->requires->js_call_amd('mod_plenum/formchooser', 'init');

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('plenumname', 'mod_plenum'), ['size' => '64']);

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'plenumname', 'mod_plenum');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Settings specific to meeting.
        $mform->addElement('header', 'plenumfieldset', get_string('plenumfieldset', 'mod_plenum'));
        $options = [];
        foreach (plenumform::get_enabled_plugins() as $plugin) {
            $options[$plugin] = get_string('pluginname', "plenumform_$plugin");
        }
        $mform->addElement('select', 'form', get_string('meetingform', 'mod_plenum'), $options, [
            'data-formchooser-field' => 'selector',
        ]);
        $mform->setDefault('form', get_config('mod_plenum', 'defaultform'));
        $mform->addHelpButton('form', 'meetingform', 'mod_plenum');

        $options = [
            get_string('manual', 'mod_plenum'),
            get_string('automaticqueuing', 'mod_plenum'),
        ];
        $mform->addElement('select', 'moderate', get_string('moderate', 'mod_plenum'), $options);
        $mform->addHelpButton('moderate', 'moderate', 'mod_plenum');
        $mform->setDefault('moderate', get_config('mod_plenum', 'moderate'));

        // Add meeting form plugin options.
        // Hidden button to update format-specific options.
        $mform->registerNoSubmitButton('updateform');
        $mform->addElement('submit', 'updateform', get_string('courseformatudpate'), [
            'data-formchooser-field' => 'updateButton',
            'class' => 'd-none',
        ]);

        $mform->addElement('hidden', 'addformoptionshere');
        $mform->setType('addformoptionshere', PARAM_BOOL);

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        $mform->setDefault('grade[modgrade_type]', ['none', 'point', 'scale'][(int)get_config('mod_plenum', 'defaultgradetype')]);
        $mform->setDefault('grade[modgrade_scale]', get_config('mod_plenum', 'defaultgradescale'));

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set
     *
     * @param  array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        // Editing existing instance.
        if ($this->current->instance) {
            $cm = get_coursemodule_from_instance('plenum', $this->current->instance);
            $plenum = \core\di::get(\mod_plenum\manager::class)->get_plenum(\context_module::instance($cm->id), $cm);
            $plenum->data_preprocessing($defaultvalues);
        }

        $suffix = $this->get_suffix();
        $completionmotionsel = 'completionmotions' . $suffix;
        $completionmotionsenabledel = 'completionmotionsenabled' . $suffix;

        // Set up the completion checkboxes which aren't part of standard data.
        // Tick by default if Add mode or if completion motions settings is set to 1 or more.
        if (empty($this->_instance) || !empty($defaultvalues[$completionmotionsel])) {
            $defaultvalues[$completionmotionsenabledel] = 1;
        } else {
            $defaultvalues[$completionmotionsenabledel] = 0;
        }
        if (empty($defaultvalues[$completionmotionsel])) {
            $defaultvalues[$completionmotionsel] = 1;
        }
    }

    /**
     * Add form settings
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform =& $this->_form;

        $form = $mform->getElementValue('form')[0];

        $classname = "\\plenumform_$form\\output\\main";
        $classname::create_settings_elements($mform);
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $group = [];
        $completionmotionsenabledel = 'completionmotionsenabled' . $suffix;
        $group[] =& $mform->createElement(
            'checkbox',
            $completionmotionsenabledel,
            '',
            get_string('completionmotions', 'mod_plenum')
        );
        $completionmotionsel = 'completionmotions' . $suffix;
        $group[] =& $mform->createElement('text', $completionmotionsel, '', ['size' => 3]);
        $mform->setType($completionmotionsel, PARAM_INT);
        $completionmotionsgroupel = 'completionmotionsgroup' . $suffix;
        $mform->addGroup($group, $completionmotionsgroupel, '', ' ', false);
        $mform->hideIf($completionmotionsel, $completionmotionsenabledel, 'notchecked');

        return [$completionmotionsgroupel];
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return (!empty($data['completionmotionsenabled' . $suffix]) && $data['completionmotions' . $suffix] != 0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix};
            $autocompletion = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->{'completionmotionsenabled' . $suffix}) || !$autocompletion) {
                $data->{'completionmotions' . $suffix} = 0;
            }
        }
    }
}
