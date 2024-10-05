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
 * Plenary motion
 *
 * @package    mod_plenum
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_plenum;

use mod_plenum\base_type;
use cache;
use completion_info;
use context;
use context_module;
use core_group\hook\after_group_deleted;
use moodle_exception;
use core\persistent;
use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Plenary motion
 *
 * @package    mod_plenum
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motion extends persistent implements renderable, templatable {
    /** Table name for the persistent. */
    const TABLE = 'plenum_motion';

    /**  Draft status for motions */
    const STATUS_DRAFT = 0;

    /**  Status for pending motions */
    const STATUS_PENDING = 1;

    /**  Status for motion being discussed on floor */
    const STATUS_OPEN = 2;

    /**  Status for questions not to be acted on */
    const STATUS_CLOSED = 3;

    /**  Status for questions approved by body */
    const STATUS_ADOPT = 4;

    /**  Status for question that body rejects*/
    const STATUS_DECLINE = 5;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'plenum' => [
                'type' => PARAM_INT,
            ],
            'plugindata' => [
                'type' => PARAM_TEXT,
            ],
            'parent' => [
                'default' => null,
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
            ],
            'groupid' => [
                'type' => PARAM_INT,
            ],
            'type' => [
                'type' => PARAM_TEXT,
            ],
            'status' => [
                'type' => PARAM_INT,
            ],
            'usercreated' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $type = $this->get('type');
        $context = $this->get_context();
        $classname = "plenumtype_$type\\type";
        $motiontype = new $classname($this, $context, get_coursemodule_from_id('plenum', $context->instanceid));
        $data = [
            'type' => $type,
            'motion' => $motiontype->export_for_template($output),
        ];

        return $data;
    }

    /**
     * Get context of motion
     *
     * return context_module
     */
    public function get_context(): context_module {
        $cm = get_coursemodule_from_instance('plenum', $this->get('plenum'), null, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * Get pending questions
     *
     * @param int $context Course module context of Plenary meeting
     */
    public static function offered_motions($context, $groupid = null) {
        global $USER;

        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        if (is_null($groupid)) {
            $groupid = groups_get_activity_group($cm);
        }

        if (!$previous = self::immediate_pending($context, $groupid)) {
            return [];
        }

        $parent = $previous->get($previous->get('type') == 'speak' ? 'parent' : 'id');

        $params = [
            'plenum' => $cm->instance,
            'status' => self::STATUS_DRAFT,
            'parent' => $parent,
            'groupid' => $groupid,
        ];
        if (!has_capability('mod/plenum:preside', $context)) {
            $cache = cache::make('mod_plenum', 'offeredmotions');
            if (($result = (array)$cache->get($USER->id . ':' . $groupid)) && key_exists($parent, $result)) {
                return $result[$parent];
            }
            $params['usercreated'] = $USER->id;
        }
        $records = self::get_records($params);

        usort($records, function ($a, $b) {
            return $a->get('timecreated') < $b->get('timecreated') ? -1 : 1;
        });
        if (!has_capability('mod/plenum:preside', $context)) {
            $result[$parent] = $records;
            $cache->set($USER->id, $result);
        }

        return $records;
    }

    /**
     * Get pending questions
     *
     * @param context_module $context Course module context of Plenary meeting
     * @return array
     */
    public static function get_pending(context_module $context, $groupid = null): array {
        $cache = cache::make('mod_plenum', 'pendingmotions');

        $cm = get_coursemodule_from_id('plenum', $context->instanceid);

        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            $groupid = 0;
        } else if (is_null($groupid)) {
            $groupid = groups_get_activity_group($cm);
        }

        if ($result = $cache->get($context->id . ':' . $groupid)) {
            return array_map(function ($record) {
                return new self(0, $record);
            }, $result);
        }

        $records = self::get_records([
            'plenum' => $cm->instance,
            'status' => self::STATUS_PENDING,
            'groupid' => $groupid,
        ]);

        $children = [];
        $result = [];
        foreach ($records as $key => $record) {
            if (empty($record->get('parent'))) {
                $next = $record->get('id');
                $result = [$record];
            } else {
                $children[$record->get('parent')] = $record;
            }
        }

        while (!empty($next) && !empty($children) && !empty($children[$next])) {
            $result[] = $children[$next];
            $next = $children[$next]->get('id');
        }

        $cache->set($context->id, array_map(function ($motion) {
            return $motion->to_record();
        }, $result));

        return $result;
    }

    /**
     * Get immediately pending motion
     *
     * @param context_module $context Plenary meeting module context
     * @return motion|null
     */
    public static function immediate_pending(context_module $context, $groupid = null): ?motion {
        $pending = self::get_pending($context, $groupid);

        return empty($pending) ? null : end($pending);
    }

    /**
     * Available motions that are in order
     *
     * @param context_module $context Plenary meeting module context
     * @return array
     */
    public static function available_motions(context_module $context, $groupid = null): array {
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        if ($previous = self::immediate_pending($context, $groupid)) {
            if ($previous->get('type') == 'speak') {
                $previous = new self($previous->get('parent'));
            }
            $type = $previous->get('type');
            $classname = "plenumtype_$type\\type";
            $instance = new $classname($previous, $context, $cm);
        } else {
            $instance = null;
        }

        $motions = [];
        foreach (plugininfo\plenumtype::get_enabled_plugins() as $name) {
            $classname = "plenumtype_$name\\type";
            if ($classname::in_order($context, $instance)) {
                $motions[$name] = get_string('name', "plenumtype_$name");
            }
        }
        return $motions;
    }

    /**
     * Make a new motion
     *
     * @param context_module $context Plenary meeting module context
     * @param stdClass $formdata Data from form
     * @return motion
     */
    public static function make(context_module $context, stdClass $formdata, $groupid = null) {
        global $DB, $USER;

        $cm = get_coursemodule_from_id('plenum', $context->instanceid);

        $previous = self::immediate_pending($context, $groupid);

        unset($formdata->contextid);
        unset($formdata->sesskey);
        unset($formdata->_qf__mod_plenum_form_edit_motion);

        $data = new stdClass();
        $data->contextid = $context->id;
        $data->type = $formdata->type;
        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            $data->groupid = 0;
        } else if (is_null($groupid)) {
            $data->groupid = groups_get_activity_group($cm) ?: 0;
        } else {
            $data->groupid = $groupid;
        }

        unset($formdata->type);
        if (!get_config("plenumtype_$data->type", 'enabled')) {
            return;
        }
        $data->plenum = $cm->instance;
        $data->usercreated = $USER->id;
        $data->plugindata = json_encode($formdata);
        if ($previous) {
            if ($previous->get('type') == 'speak') {
                $data->parent = $previous->get('parent');
            } else {
                $data->parent = $previous->get('id');
            }
        } else {
            $data->parent = null;
        }
        $data->status = self::STATUS_DRAFT;
        $motion = new motion(0, $data);
        $motion->create();

        $completion = new completion_info((object)['id' => $cm->course]);
        if (
            $completion->is_enabled($cm)
            && $DB->get_field('plenum', 'completionmotions', ['id' => $cm->instance])
        ) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        if (
            (has_capability('mod/plenum:preside', $context) && in_array($data->type, ['divide', 'open']))
            || (
                !in_array($data->type, ['nay', 'yea'])
                && $DB->get_field('plenum', 'moderate', ['id' => $cm->instance])
                && !self::get_record([
                    'parent' => $data->parent,
                    'status' => self::STATUS_PENDING,
                ])
            )
        ) {
            $motion->get_instance()->change_status(self::STATUS_PENDING);
        }

        return $motion;
    }

    /**
     * Return motion type instance
     *
     * @return base_type
     */
    public function get_instance(): base_type {
        $type = $this->get('type');
        if (!key_exists($type, plugininfo\plenumtype::get_enabled_plugins())) {
            throw new moodle_exception('typedisabled');
        }
        $classname = "plenumtype_$type\\type";
        return new $classname($this);
    }

    /**
     * Return motion type instances for pending motions
     *
     * @param context_module $context
     * @return array
     */
    public static function instances($context, $groupid = null) {
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $pending = [];
        foreach (self::get_pending($context, $groupid) as $motion) {
            $type = $motion->get('type');
            if (key_exists($type, plugininfo\plenumtype::get_enabled_plugins())) {
                $classname = "plenumtype_$type\\type";
                $pending[] = new $classname($motion, $context, $cm, $groupid);
            }
        }

        $privledged = [];
        $unprivledged = [];
        foreach (self::offered_motions($context, $groupid) as $motion) {
            $type = $motion->get('type');
            if (key_exists($type, plugininfo\plenumtype::get_enabled_plugins())) {
                $classname = "plenumtype_$type\\type";
                $instance = new $classname($motion, $context, $cm, $groupid);
                if ($instance->has_privledge()) {
                    $privledged[] = $instance;
                } else {
                    $unprivledged[] = $instance;
                }
            }
        }

        return count($privledged) ? array_merge($pending, $privledged) : array_merge($pending, $unprivledged);
    }

    /**
     * Change the status of the motion
     *
     * @param int $status
     */
    public function change_status(int $status) {
        $this->set('status', $status);
        $this->update();
    }

    /**
     * Check whether motion has child of a specific type and state
     *
     * @param string $type
     * @param int $state
     * @return bool
     */
    public function has_child(string $type, int $state) {
        return !empty(self::get_records([
            'parent' => $this->get('id'),
            'type' => $type,
            'status' => $state,
        ]));
    }

    /**
     * Get all subsidiary motions descending tree
     *
     * @return array
     */
    public function get_descendants() {
        $descendants = [$this->get('id') => $this];

        foreach (self::get_records(['parent' => $this->get('id') ]) as $child) {
            $descendants += $child->get_descendants();
        };

        return $descendants;
    }


    /**
     * Get stored data for form
     *
     * @return stdClass
     */
    public function get_data() {
        return json_decode($this->get('plugindata') ?: '{}');
    }

    /**
     * Notify of update
     *
     * @param bool $result
     */
    public function after_update($result) {
        $params = [
            'context' => $this->get_context(),
            'objectid' => $this->get('id'),
            'other' => [
                'status' => $this->get('status'),
            ],
        ];
        $event = \mod_plenum\event\motion_updated::create($params);
        $event->trigger();

        $this->clear_cache();
    }

    /**
     * Hook to execute after a create.
     *
     * @return void
     */
    protected function after_create() {
        $params = [
            'context' => $this->get_context(),
            'objectid' => $this->get('id'),
        ];
        $event = \mod_plenum\event\motion_created::create($params);
        $event->trigger();

        $this->clear_cache();
    }

    /**
     * Hook to execute before delete
     *
     * @return void
     */
    protected function before_delete() {
        $cm = get_coursemodule_from_instance('plenum', $this->get('plenum'));
        $hook = new \mod_plenum\hook\before_motion_deleted($this->get_context(), $cm, $this);

        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $params = [
            'context' => $this->get_context(),
            'objectid' => $this->get('id'),
            'other' => [
                'status' => $this->get('status'),
            ],
        ];
        $event = \mod_plenum\event\motion_deleted::create($params);
        $event->trigger();
    }

    /**
     * Clear cache when motion is changed
     */
    protected function clear_cache() {
        $cm = get_coursemodule_from_instance('plenum', $this->get('plenum'));
        $groupid = $this->get('groupid');

        $cache = cache::make('mod_plenum', 'pendingmotions');
        $cache->delete($this->get_context()->id . ':' . $groupid);

        $cache = cache::make('mod_plenum', 'offeredmotions');
        $cache->delete($this->get('usercreated') . ':' . $groupid);

        $hook = new \mod_plenum\hook\after_motion_updated($this->get_context(), $cm);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
    }

    /**
     * Return motion type instaces for pending motions
     *
     * @param context_module $context
     * @return array
     */
    public static function instance_list($context) {
        $cm = get_coursemodule_from_id('plenum', $context->instanceid);
        $groupid = groups_get_activity_group($cm);
        $motions = array_map(
            function ($motion) {
                $type = $motion->get('type');
                if (key_exists($type, plugininfo\plenumtype::get_enabled_plugins())) {
                    $classname = "plenumtype_$type\\type";
                    return new $classname($motion, $context, $cm, $groupid);
                } else {
                    return null;
                }
            },
            self::get_records([
                'plenum' => $cm->instance,
                'groupid' => $groupid,
            ])
        );

        return array_filter($motions);
    }

    /**
     * Delete all motions in a module
     *
     * @param int $plenum Plenum activity instance id
     */
    public static function delete_all(int $plenum) {
        $motions = self::get_records(['plenum' => $plenum]);
        $cm = get_coursemodule_from_instance('plenum', $plenum);
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        foreach ($motions as $motion) {
            $files = $fs->get_area_files($context->id, 'mod_plenum', 'attachments', $motion->get('id'));
            foreach ($files as $file) {
                $file->delete();
            }
            $motion->delete();
        }
    }

    /**
     * Delete motion belonging to a group when group is deleted
     *
     * @param after_group_deleted $hook Group hook for deleted group
     */
    public static function after_group_deleted(after_group_deleted $hook) {
        foreach (self::get_records(['groupid' => $hook->groupinstance->id]) as $motion) {
            $motion->delete();
        }
    }
}
