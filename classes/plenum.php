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

namespace mod_plenum;

defined('MOODLE_INTERNAL') || die();

use cache;
use cached_cm_info;
use context;
use cm_info;
use context_module;
use core_group\hook\after_group_deleted;
use moodle_exception;
use moodle_url;
use MoodleQuickForm;
use core\persistent;
use renderable;
use renderer_base;
use templatable;
use stdClass;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Plenary meeting instance class
 *
 * @package    mod_plenum
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plenum {
    /**
     * Constructor
     */
    public function __construct(
        /** @var context_module Module context */
        protected readonly context_module $context,
        /** @var null|cm_info $cm Course module */
        protected null|stdClass|cm_info $cm,
        /** @var stdClass|null $course */
        protected ?stdClass $course,
        /** @var stdClass|null $instance Module instance */
        protected ?stdClass $instance,
        /** @var \core\clock $clock System clock */
        protected readonly \core\clock $clock,
        /** @var \moodle_database $db Database manager */
        protected readonly \moodle_database $db
    ) {
    }

    /**
     * Saves a new instance of the mod_plenum into the database.
     *
     * Given an object containing all the necessary data, (defined by the form
     * in mod_form.php) this function will create a new instance and return the id
     * number of the instance.
     *
     * @param object $moduleinstance An object from the form.
     * @param mod_plenum_mod_form $mform The form.
     * @return int The id of the newly inserted record.
     */
    public function add_instance($moduleinstance, $mform = null) {
        $moduleinstance->timecreated = $this->clock->time();
        $moduleinstance->timemodified = $moduleinstance->timecreated;

        $moduleinstance->id = $this->db->insert_record('plenum', $moduleinstance);

        $result = false;

        foreach (\mod_plenum\plugininfo\plenumform::get_enabled_plugins() as $name) {
            $classname = "plenumform_$name\options";
            $result = $result && $classname::update_instance($moduleinstance, $mform);
        }

        // Update grade item definition.
        plenum_grade_item_update($moduleinstance);

        $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $moduleinstance->coursemodule,
            'plenum',
            $moduleinstance->id,
            $completiontimeexpected
        );

        return $moduleinstance->id;
    }

    /**
     * Updates an instance of the mod_plenum in the database.
     *
     * Given an object containing all the necessary data (defined in mod_form.php),
     * this function will update an existing instance with new data.
     *
     * @param object $moduleinstance An object from the form in mod_form.php.
     * @param mod_plenum_mod_form $mform The form.
     * @return bool True if successful, false otherwise.
     */
    public function update_instance($moduleinstance, $mform = null) {
        $moduleinstance->timemodified = $this->clock->time();
        $moduleinstance->id = $moduleinstance->instance;

        $result = $this->db->update_record('plenum', (array)$moduleinstance + ['moderate' => 0]);

        foreach (plugininfo\plenumform::get_enabled_plugins() as $name) {
            $classname = "plenumform_$name\options";
            $result = $result && $classname::update_instance($moduleinstance, $mform);
        }

        // Update grade item definition.
        plenum_grade_item_update($moduleinstance);

        $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
        \core_completion\api::update_completion_date_event(
            $moduleinstance->coursemodule,
            'plenum',
            $moduleinstance->id,
            $completiontimeexpected
        );

        return $result;
    }

    /**
     * Removes an instance of the mod_plenum from the database.
     *
     * @return bool True if successful, false on failure.
     */
    public function delete_instance() {
        $id = $this->get_course_module()->instance;

        $exists = $this->db->get_record('plenum', ['id' => $id]);
        if (!$exists) {
            return false;
        }

        // Get all available plugins.
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('plenumform');
        $result = true;
        foreach (array_keys($plugins) as $name) {
            $classname = "plenumform_$name\options";
            $result = $result && $classname::delete_instance($id);
        }

        motion::delete_all($id);

        plenum_grade_item_delete($this->get_instance());

        return $result && $this->db->delete_records('plenum', ['id' => $id]);
    }

    /**
     * Return main view page
     *
     * @return stdClass
     */
    public function get_mainpage() {
        $form = $this->get_instance()->form ?: get_config('mod_plenum', 'defaultform');
        $classname = "plenumform_$form\\output\\main";
        $main = new $classname($this->context, $this->cm, $this->instance);
        return $main;
    }

    /**
     * Get module id
     *
     * @return int
     */
    public function get_id(): int {
        return $this->get_course_module()->instance;
    }

    /**
     * Get module instance
     *
     * @return stdClass
     */
    public function get_instance(): stdClass {
        if (empty($this->instance)) {
            $this->instance = $this->db->get_record('plenum', [
                'id' => $this->get_course_module()->instance,
            ]);
        }

        return $this->instance;
    }

    /**
     * Get context
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Get course module info
     *
     * @return cm_info|stdClass
     */
    public function get_course_module(): cm_info {
        if (empty($this->cm)) {
            $this->cm = get_coursemodule_from_id('plenum', $this->get_context()->instanceid);
        }

        if (!$this->cm instanceof cm_info) {
            $this->cm = cm_info::create($this->cm);
        }

        return $this->cm;
    }

    /**
     * Get course
     *
     * @return stdClass
     */
    public function get_course(): stdClass {
        if (empty($this->course)) {
            $this->course = get_course($this->get_context()->get_course_context()->instance);
        }

        return $this->course;
    }

    /**
     * This function returns any "extra" information that may be
     * needed when printing this activity in a course listing.
     *
     * @param stdClass $coursemodule The coursemodule object (record).
     * @return cached_cm_info An object on information that the courses
     * will know about (most noticeably, an icon).
     */
    public static function get_coursemodule_info($coursemodule) {
        $dbparams = ['id' => $coursemodule->instance];
        $fields = 'id, name, intro, introformat, form, completionmotions';
        $db = \core\di::get(\moodle_database::class);
        if (!$plenum = $db->get_record('plenum', $dbparams, $fields)) {
            return false;
        }

        $result = new cached_cm_info();
        $result->name = $plenum->name;
        $result->customdata['form'] = $plenum->form;

        if ($coursemodule->showdescription) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('plenum', $plenum, $coursemodule->id, false);
        }

        // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
        if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $result->customdata['customcompletionrules']['completionmotions'] = $plenum->completionmotions;
        }

        return $result;
    }

    /**
     * Prepares the form before data are set
     *
     * @param  array $defaultvalues
     * @param  int $instance
     */
    public function data_preprocessing(array &$defaultvalues) {
        $form = $this->get_course_module()->customdata['form'];
        $form = $this->get_instance()->form;
        $optionsclass = "\\plenumform_$form\\options";
        $optionsclass::data_preprocessing($defaultvalues, $this->get_course_module()->instance);
    }

    /**
     * Whether grading is enabled for this item.
     *
     * @return bool
     */
    public function is_grading_enabled(): bool {
        return !empty($this->get_instance()->grade);
    }

    /**
     * Get an instance of a grading form if advanced grading is enabled.
     * This is specific to the plenum, marker and student.
     *
     * @param int $userid - The student userid
     * @param stdClass|false $grade - The grade record
     * @param bool $gradingdisabled
     * @return mixed gradingform_instance|null $gradinginstance
     */
    protected function get_grading_instance($userid, $grade, $gradingdisabled) {
        global $CFG, $USER;

        $grademenu = make_grades_menu($this->get_instance()->grade);
        $allowgradedecimals = $this->get_instance()->grade > 0;

        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_plenum', 'plenum');
        $gradinginstance = null;
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if ($grade) {
                    $itemid = $grade->id;
                }
                if ($gradingdisabled && $itemid) {
                    $gradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $gradinginstance = $controller->get_or_create_instance(
                        $instanceid,
                        $USER->id,
                        $itemid
                    );
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }
        if ($gradinginstance) {
            $gradinginstance->get_controller()->set_grade_range($grademenu, $allowgradedecimals);
        }
        return $gradinginstance;
    }

    /**
     * Add elements to grade form.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param array $params
     * @return void
     */
    public function add_grade_form_elements(MoodleQuickForm $mform, stdClass $data, $params) {
        global $USER, $CFG, $SESSION;

        $grade = $this->db->get_record('plenum_grades', $params);
        $userid = $data->userid ?? 0;

        // Add advanced grading.
        $gradingdisabled = !$this->is_grading_enabled();
        $gradinginstance = $this->get_grading_instance($userid, $grade, $gradingdisabled);

        $mform->addElement('header', 'gradeheader', get_string('gradenoun'));
        if ($gradinginstance) {
            $gradingelement = $mform->addElement(
                'grading',
                'advancedgrading',
                get_string('gradenoun') . ':',
                ['gradinginstance' => $gradinginstance]
            );
            if ($gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
                $mform->setType('advancedgradinginstanceid', PARAM_INT);
            }
        } else if ($this->get_instance()->grade > 0) {
            $mform->addElement('text', 'grade', get_string('gradenoun'));
            $mform->setType('grade', PARAM_FLOAT);
        } else if ($this->get_instance()->grade < 0) {
            $scaleoptions = null;
            if ($scale = $this->db->get_record('scale', ['id' => -($this->get_instance()->grade)])) {
                $scaleoptions = make_menu_from_list($scale->scale);
                $mform->addElement('select', 'grade', get_string('gradenoun'), $scaleoptions);
            } else {
                throw new \moodle_exception('invalidescale');
            }
        }

        $mform->addElement('editor', 'feedback', get_string('feedback', 'mod_plenum'), ['rows' => 10]);
        if (!empty($grade)) {
            $mform->setDefault('grade', (float)$grade->grade);
            $mform->setDefault('feedback', [
                'text' => $grade->feedback,
                'format' => $grade->feedbackformat,
            ]);
        }
        $mform->setType('feedback', PARAM_RAW);
    }

    /**
     * This will retrieve a grade object from the db, optionally creating it if required.
     *
     * @param int $userid The user we are grading
     * @param bool $create If true the grade will be created if it does not exist
     * @return ?stdClass The grade record
     */
    public function get_user_grade($userid, $create): ?stdClass {
        global $USER;

        // If the userid is not null then use userid.
        if (!$userid) {
            $userid = $USER->id;
        }

        $params = [
            'plenum' => $this->get_instance()->id,
            'userid' => $userid,
            'itemnumber' => 0,
        ];
        if ($grade = $this->db->get_record('plenum_grades', $params)) {
            return $grade;
        }
        if (!$create) {
            return null;
        }

        $params['timecreated'] = $this->clock->time();
        $params['timemodified'] = $params['timecreated'];
        $params['grader'] = $USER->id;
        $params['id'] = $this->db->insert_record('plenum_grades', $params);

        return (object)$params;
    }

    /**
     * Apply a grade from a grading form to a user (may be called multiple times for a group submission).
     *
     * @param stdClass $formdata - the data from the form
     * @param int $userid - the user to apply the grade to
     * @param int $attemptnumber - The attempt number to apply the grade to.
     * @return void
     */
    protected function apply_grade_to_user($formdata, $userid) {
        global  $CFG, $USER;

        $grade = $this->get_user_grade($userid, true);
        $gradingdisabled = !$this->is_grading_enabled();
        $gradinginstance = $this->get_grading_instance($userid, $grade, $gradingdisabled);
        if (!$gradingdisabled) {
            if ($gradinginstance) {
                $grade->grade = $gradinginstance->submit_and_get_grade(
                    $formdata->advancedgrading,
                    $grade->id
                );
            } else {
                if (isset($formdata->grade)) {
                    $grade->grade = grade_floatval(unformat_float($formdata->grade));
                    ['text' => $grade->feedback, 'format' => $grade->feedbackformat] = $formdata->feedback;
                }
            }
            $grade->grader = $USER->id;
            $grade->timemodified = $this->clock->time();
            $this->db->update_record('plenum_grades', $grade);
            \mod_plenum\event\plenum_graded::create_from_grade($this, $grade)->trigger();
        }
        plenum_update_grades($this->get_instance(), $userid);
    }

    /**
     * Save grade update.
     *
     * @param int $userid
     * @param  stdClass $data
     * @return bool - was the grade saved
     */
    public function save_grade($userid, $data) {

        // Need grade permission.
        require_capability('mod/plenum:grade', $this->context);

        $instance = $this->get_instance();
        $this->apply_grade_to_user($data, $userid);

        return true;
    }

    /**
     * Render output for student to see grade
     *
     * @return string
     */
    public function view_status(): string {
        global $OUTPUT, $PAGE, $USER;

        // Grading criteria preview.
        $gradingmanager = get_grading_manager($this->context, 'mod_plenum', 'plenum');
        $gradingcontrollerpreview = '';
        $grade = $this->db->get_record('plenum_grades', [
            'userid' => $USER->id,
            'plenum' => $this->get_id(),
        ]);
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_defined()) {
                if ($grade) {
                    $gradingcontrollerpreview = $controller->render_grade(
                        $PAGE,
                        $grade->id,
                        0,
                        '',
                        false
                    );
                } else {
                    $gradingcontrollerpreview = $controller->render_preview($PAGE);
                }
            }
        } else if ($this->get_instance()->grade > 0) {
            $numericalgrade = $grade->grade;
        } else {
            if ($scale = $this->db->get_record('scale', ['id' => -($this->get_instance()->grade)])) {
                $scaleoptions = make_menu_from_list($scale->scale);
                $scalegrade = $scaleoptions[(int)$grade->grade] ?? '';
            } else {
                throw new \moodle_exception('invalidescale');
            }
        }

        return $OUTPUT->render_from_template(
            'mod_plenum/view_grade',
            [
                'gradingpreview' => $gradingcontrollerpreview ?? '',
                'numericalgrade' => $numericalgrade ?? '',
                'scalegrade' => $scalegrade ?? '',
                'feedback' => format_text($grade->feedback, $grade->feedbackformat, ['context' => $this->get_context()]),
            ]
        );
    }

    /**
     * Get the grade for the Plenary meeting when grading holistically.
     *
     * @return int
     */
    public function get_grade_for_plenum(): int {
        return $this->get_instance()->grade;
    }
}
