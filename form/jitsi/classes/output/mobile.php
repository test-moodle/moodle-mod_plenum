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
 * @package     plenumform_jitsi
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plenumform_jitsi\output;

use stdClass;
use context_module;

/**
 * Mobile output class for Plenary meeting Deft integration
 *
 * @package     plenumform_jitsi
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
        $delay = get_config('plenumform_jitsi', 'delay');

        $js = "setTimeout(this.refreshContent.bind(this, false), $delay * 1000);";
        $data = [
            'instance' => $moduleinstance,
            'contextid' => $context->id,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('plenumform_jitsi/mobile_main', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'contextid' => $context->id,
                'throttle' => get_config('block_deft', 'throttle'),
            ],
        ];
    }

    /**
     * Returns content to int js
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function init($args) {
        $js = '';

        return [
            'templates' => [
            ],
            'javascript' => $js,
        ];
    }
}
