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
 * Plenary meeting motion type definition
 *
 * @package   mod_plenum
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum;

use context_module;
use stdClass;
use core_user;
use mod_plenum\motion;
use renderer_base;
use renderable;
use templatable;
use user_picture;

/**
 * Plenary meeting motion type definition
 *
 * @package   mod_plenum
 * @copyright 2023 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base_type implements renderable, templatable {
    /** Whether information should be shown on motion */
    const DETAIL = false;

    /** @var $component */
    public string $component = 'plenumtype_open';

    /** @var $debatable */
    public bool $debatable = false;

    /**
     * Constructor.
     *
     * @param motion $motion Motion
     * @param null|context_module $context The content record for the binder
     * @param null|stdClass $cm Course module
     */
    public function __construct(
        /** @var $motion Motion */
        protected readonly motion $motion,
        /** @var $context Module context */
        protected ?context_module $context = null,
        /** @var $cm Course module record */
        protected ?stdClass $cm = null,
        /** @var $groupid Group id */
        protected $groupid = null
    ) {
    }

    /**
     * Get plugin identifier
     *
     * @return string
     */
    public function get_name() {
        return get_string('name', static::COMPONENT);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $options = [];
        $user = core_user::get_user($this->motion->get('usercreated'));
        $userpicture = new user_picture($user);
        $user->pictureurl = $userpicture->get_url($PAGE, $output);
        $user->fullname = fullname($user);
        $immediate = motion::immediate_pending($this->get_context(), $this->groupid);
        $data = [
            'id' => $this->motion->get('id'),
            'close' => $this->can_close(),
            'type' => $this->motion->get('type'),
            'typename' => get_string('pluginname', 'plenumtype_' . $this->motion->get('type')),
            'data' => $this->motion->get_data(),
            'start' => userdate($this->motion->get('timecreated'), get_string('strftimetime', 'langconfig')),
            'user' => $user,
            'chair' => has_capability('mod/plenum:preside', $this->context),
            'decide' => $this->can_decide(),
            'immediate' => !empty($immediate) && ($this->motion->get('id') == $immediate->get('id')),
            'needssecond' => $this->needs_second(),
            'pending' => $this->motion->get('status') == motion::STATUS_PENDING,
            'preview' => $this->show_detail(),
        ];

        return $data;
    }

    /**
     * Whether user can withdraw
     *
     * @return bool
     */
    public function can_close(): bool {
        return false;
    }

    /**
     * Whether to show a detail pop up
     *
     * @return bool
     */
    public function show_detail() {
        return static::DETAIL;
    }

    /**
     * Whether motion is currently in order
     *
     * @param context_module $context The context for plenary meeting
     * @param base_type|null $immediate The immediately pending question
     * @return bool
     */
    public static function in_order($context, $immediate) {
        return true;
    }

    /**
     * Find whether motion is debatable
     *
     * @return bool
     */
    public function is_debatable() {
        return $this->debatable && !$this->motion->has_child('call', motion::STATUS_ADOPT);
    }

    /**
     * Find whether user is allow to record result
     *
     * @return bool
     */
    public function can_decide() {
        $immediate = motion::immediate_pending($this->get_context(), $this->groupid);
        return has_capability('mod/plenum:preside', $this->context)
            && in_array($this->motion->get('type'), ['adjorn', 'amend', 'call', 'divide', 'order', 'resolve', 'second'])
            && !$this->needs_second()
            && (!get_config('plenumtype_call', 'enabled') || !$this->is_debatable())
            && !empty($immediate)
            && ($this->motion->get('id') == $immediate->get('id'));
    }

    /**
     * Change the status of the motion
     *
     * @param int $state
     */
    public function change_status(int $state) {
        global $DB;

        $pending = motion::immediate_pending($this->motion->get_context(), $this->motion->get('groupid'));

        if (
            $pending
            && ($this->motion->get('status') == motion::STATUS_PENDING)
            && ($state != motion::STATUS_PENDING)
            && ($pending->get('id') == $this->motion->get('id'))
            && ($plenum = $DB->get_record('plenum', ['id' => $this->motion->get('plenum')]))
            && $plenum->moderate
            && $queue = motion::get_records([
                'plenum' => $this->motion->get('plenum'),
                'groupid' => $this->motion->get('groupid'),
                'parent' => $this->motion->get('parent'),
                'status' => motion::STATUS_DRAFT,
            ], "CASE WHEN type = 'order' THEN 0 ELSE 1 END, timecreated")
        ) {
            $next = array_shift($queue);
            $this->motion->set('status', $state);
            $this->motion->update();
            $next->change_status(motion::STATUS_PENDING);
        } else {
            $this->motion->set('status', $state);
            $this->motion->update();
        }
    }

    /**
     * Whether motion is privledged
     *
     * @return bool
     */
    public function has_privledge() {
        return false;
    }

    /**
     * Get course module info
     *
     * @return cm_info|stdClass
     */
    public function get_course_module(): stdClass|cm_info {
        if (empty($this->cm)) {
            return $this->motion->get_course_module();
        }
        return $this->cm;
    }

    /**
     * Get context
     *
     * @return context_module
     */
    public function get_context(): context_module {
        if (empty($this->context)) {
            return $this->motion->get_context();
        }
        return $this->context;
    }

    /**
     * Whether second is required
     *
     * @return bool
     */
    public function needs_second() {
        return get_config(static::COMPONENT, 'requiresecond')
            && get_config('plenumtype_second', 'enabled')
            && !$this->motion->has_child('second', motion::STATUS_ADOPT);
    }
}
