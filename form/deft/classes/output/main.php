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
 * Class for Plenary meeting media elements
 *
 * @package     plenumform_deft
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plenumform_deft\output;

use cache;
use cm_info;
use context_module;
use moodleform;
use moodle_url;
use MoodleQuickForm;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use mod_plenum\motion;
use plenumform_deft\socket;

/**
 * Class for Plenary meeting media elements
 *
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main extends \mod_plenum\output\main {
    /** @var $motion Motions */
    protected $motions;

    /** @var $socket Deft socket */
    protected $socket;

    /**
     * Constructor.
     *
     * @param context_module $context Module context
     * @param cm_info $cm Course module record
     * @param stdClass $instance Instance record
     */
    public function __construct(
        context_module $context,
        cm_info $cm,
        stdClass $instance
    ) {
        parent::__construct($context, $cm, $instance);

        $this->socket = new socket($context);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        return [
            'autogaincontrol' => !empty(get_config('block_deft', 'autogaincontrol')),
            'chair' => has_capability('mod/plenum:preside', $this->context),
            'contextid' => $this->context->id,
            'echocancellation' => !empty(get_config('block_deft', 'echocancellation')),
            'iceservers' => json_encode($this->socket->ice_servers()),
            'instance' => json_encode($this->instance),
            'name' => $this->instance->name,
            'noisesuppression' => !empty(get_config('block_deft', 'noisesuppression')),
            'peerid' => $USER->id,
            'samplerate' => get_config('block_deft', 'samplerate'),
            'throttle' => get_config('block_deft', 'throttle'),
            'token' => $this->socket->get_token(),
            'slots' => [
                [
                    'slotname' => get_string('chair', 'plenumform_deft'),
                    'slot' => 'chair',
                    'posterurl' => $output->image_url('chair-solid', 'plenumform_deft'),
                ],
                [
                    'slotname' => get_string('floor', 'plenumform_deft'),
                    'slot' => 'floor',
                    'posterurl' => $output->image_url('microphone-solid', 'plenumform_deft'),
                ],
            ],
        ] + parent::export_for_template($output);
    }
}
