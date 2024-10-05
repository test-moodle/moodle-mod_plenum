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
 * Mobile output class for Plenary meeting
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum\output;

use context;
use context_module;
use mod_plenum\motion;
use moodle_exception;
use mod_plenum\plugininfo\plenumtype;
use mod_plenum\plugininfo\plenumform;
use stdClass;

/**
 * Mobile output class for Plenary meeting
 *
 * @package     mod_plenum
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the Plenary meeting course view for the mobile app.
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     * @throws \required_capability_exception
     * @throws \coding_exception
     * @throws \require_login_exception
     * @throws \moodle_exception
     */
    public static function mobile_mod_view($args) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $args = (object) $args;

        $context = context_module::instance($args->cmid);
        $cm = get_coursemodule_from_id('plenum', $args->cmid);

        // Capabilities check.
        require_login($args->courseid, false, $cm, true, true);

        require_capability('mod/plenum:view', $context);

        $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);

        if ($groupmode = groups_get_activity_groupmode($cm)) {
            $groups = groups_get_activity_allowed_groups($cm);
            if (isset($args->group)) {
                $selectedgroup = $groups[(string)$args->group];
            } else {
                $selectedgroup = reset($groups);
            }
        } else {
            $groups = [];
            $selectedgroup = null;
        }

        $output = $PAGE->get_renderer('mod_plenum');

        $motions = new \mod_plenum\output\motions($context, $selectedgroup->id ?? 0);

        $data = $motions->export_for_template($output) + [
            'chair' => has_capability('mod/plenum:preside', $context),
            'cmid' => $cm->id,
            'courseid' => $moduleinstance->course,
            'groupmode' => $groupmode,
            'groups' => array_values($groups),
            'selectedgroup' => $selectedgroup,
            'instance' => $moduleinstance,
            'media' => '',
            'viewed' => !empty($args->viewed) || !empty($args->group),
        ];

        $js = '';
        $otherdata = [];
        if ($name = $moduleinstance->form) {
             $classname = "plenumform_$name\\output\\mobile";
             $result = $classname::mobile_mod_view((array)$args);
             $js .= $result['javascript'] ?? '';

             $data['media'] .= $result['templates'][0]['html'];
             $otherdata += $result['otherdata'] ?? [];
        }

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_plenum/mobile_main', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => $otherdata,
        ];
    }

    /**
     * Returns the form for making a motion
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array HTML, javascript and otherdata
     * @throws \required_capability_exception
     * @throws \coding_exception
     * @throws \require_login_exception
     * @throws \moodle_exception
     */
    public static function mobile_move($args) {
        global $DB, $OUTPUT, $PAGE;

        $args = (object)$args;
        $context = context::instance_by_id($args->contextid);
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);

        // Capabilities check.
        require_login($moduleinstance->course, false, $cm, true, true);

        require_capability('mod/plenum:meet', $context);

        $output = $PAGE->get_renderer('mod_plenum');

        $error = [];
        $otherdata = [];
        $formclass = "plenumtype_$args->type\\form\\edit_motion";
        $args->attachments = 0;
        $form = new $formclass(null, ['groupid' => $args->groupid ?? null], 'post', '', null, true, (array)$args);
        if (!empty($args->submit)) {
            if (!$error = $form->validation((array)$args, [])) {
                self::process_submission($context, $args);
                return [
                    'templates' => [
                        [
                            'id' => 'main',
                            'html' => get_string('motioncreated', 'mod_plenum'),
                        ],
                    ],
                    'javascript' => '',
                    'otherdata' => [
                    ],
                ];
            } else {
                $otherdata = $form->other_data();
            }
        }
        $data = (array)$args + [
            'error' => $error,
            'warning' => !empty(self::current_offer($context, $args->groupid ?? null)),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template("plenumtype_$args->type/mobile_edit_form", $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => $otherdata,
        ];
    }

    /**
     * Initialize javascript
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     */
    public static function init($args): array {
        global $CFG, $OUTPUT;

        $js = "var Plenum = {},
            result = {Plenum: Plenum};
              ";
        $js .= file_get_contents("$CFG->dirroot/mod/plenum/mobile/view.js");

        $templates = [];
        foreach (plenumform::get_enabled_plugins() as $name) {
            $classname = "\\plenumform_$name\\output\\mobile";
            if (class_exists($classname)) {
                $result = $classname::init($args);
                $templates = array_merge($templates, $result['templates']);
                $js .= $result['javascript'];
            }
        }

        $js .= "
        result.Janus = Janus;

        result;";

        return [
            'templates' => [
                [
                    'id' => 'confirm',
                    'html' => $OUTPUT->render_from_template('mod_plenum/mobile_confirm', []),
                ],
            ],
            'javascript' => $js,
        ];
    }

    /**
     * List motions
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array HTML, javascript and otherdata
     */
    public static function mobile_motion_list($args) {
        global $DB, $OUTPUT, $PAGE;

        $args = (object)$args;
        $cm = get_coursemodule_from_id('plenum', $args->cmid);
        $context = context_module::instance($cm->id);
        $moduleinstance = $DB->get_record('plenum', ['id' => $cm->instance], '*', MUST_EXIST);

        // Capabilities check.
        require_login($moduleinstance->course, false, $cm, true, true);

        require_capability('mod/plenum:meet', $context);

        $output = $PAGE->get_renderer('mod_plenum');

        $motions = [];
        foreach (motion::instance_list($context) as $motion) {
            if (
                in_array($motion->component, [
                'plenumtype_amend',
                'plenumtype_open',
                'plenumtype_resolve',
                ])
            ) {
                $motions[] = [
                'motion' => $motion->export_for_template($output),
                'type' => $motion->component,
                ];
            }
        }

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_plenum/mobile_motion_list', [
                        'contextid' => $context->id,
                        'motions' => $motions,
                    ]),
                ],
            ],
            'javascript' => '',
        ];
    }

    /**
     * Get the motion current user is offering
     *
     * @return motion|null
     */
    protected static function current_offer($context, $groupid = null): ?motion {
        global $USER;

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
        ]);

        return empty($motions) ? null : end($motions);
    }

    /**
     * Process the form submission
     *
     * @param module_context $context Module content
     * @param stdClass Form data
     */
    protected static function process_submission($context, $data) {
        if (!empty($data->warning)) {
            $motion = self::current_offer($context, $data->groupid ?? null);
            $data->plugindata = json_encode(array_diff_key((array)$data, [
                'contextid' => null,
                'warning' => null,
            ]));
            unset($data->groupid);
            $motion->from_record($data);
            $motion->update();
        } else {
            motion::make($context, $data, $data->groupid ?? null);
        }
    }
}
