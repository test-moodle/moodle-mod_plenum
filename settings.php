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
 * Plugin administration pages are defined here.
 *
 * @package     mod_plenum
 * @category    admin
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_plenum\plugininfo\plenumform;

require_once($CFG->libdir . '/grade/grade_scale.php');

$ADMIN->add('modsettings', new admin_category(
    'modplenum',
    new lang_string('pluginname', 'mod_plenum'),
    $module->is_enabled() === false
));
$settings = new admin_settingpage('plenumdefaults', new lang_string('defaultsettings', 'mod_plenum'));
if ($hassiteconfig) {
    // Add default module settings.
    $options = [
        new lang_string('manual', 'mod_plenum'),
        new lang_string('automaticqueuing', 'mod_plenum'),
    ];
    $name = new lang_string('moderate', 'mod_plenum');
    $description = new lang_string('moderate_help', 'mod_plenum');
    $setting = new admin_setting_configselect(
        'mod_plenum/moderate',
        $name,
        $description,
        0,
        $options
    );
    $settings->add($setting);

    $name = new lang_string('defaultgradetype', 'mod_plenum');
    $description = new lang_string('defaultgradetype_help', 'mod_plenum');
    $setting = new admin_setting_configselect(
        'mod_plenum/defaultgradetype',
        $name,
        $description,
        GRADE_TYPE_VALUE,
        [
                                                        GRADE_TYPE_NONE => new lang_string('modgradetypenone', 'grades'),
                                                        GRADE_TYPE_SCALE => new lang_string('modgradetypescale', 'grades'),
                                                        GRADE_TYPE_VALUE => new lang_string('modgradetypepoint', 'grades'),
        ]
    );
    $settings->add($setting);

    /** @var grade_scale[] $scales */
    $scales = grade_scale::fetch_all_global();
    $choices = ['' => new lang_string('choosedots')];
    foreach ($scales as $scale) {
        $choices[$scale->id] = $scale->get_name();
    }
    $name = new lang_string('defaultgradescale', 'mod_plenum');
    $description = new lang_string('defaultgradescale_help', 'mod_plenum');
    if (count($choices) > 1) {
        $setting = new admin_setting_configselect(
            'mod_plenum/defaultgradescale',
            $name,
            $description,
            '',
            $choices
        );
    } else {
        $setting = new admin_setting_configempty(
            'mod_plenum/defaultgradescale',
            $name,
            $description
        );
    }
    $settings->add($setting);
}
$ADMIN->add('modplenum', $settings);

$options = [];
foreach (plenumform::get_enabled_plugins() as $plugin) {
    $options[$plugin] = new lang_string('pluginname', "plenumform_$plugin");
}

$pluginmanager = core_plugin_manager::instance();
$ADMIN->add('modplenum', new admin_category(
    'plenumformplugins',
    new lang_string('subplugin_plenumform_plural', 'mod_plenum'),
    !$module->is_enabled()
));
$temp = new admin_settingpage('manageplenumformplugins', new lang_string('manageplenumformplugins', 'mod_plenum'));
$temp->add(new \mod_plenum\admin\manage_plenumform_plugins_page());
if ($ADMIN->fulltree) {
    // Add settings here.
    $name = new lang_string('meetingform', 'mod_plenum');
    $description = new lang_string('meetingform_help', 'mod_plenum');
    $temp->add(new admin_setting_configselect(
        'mod_plenum/defaultform',
        $name,
        $description,
        'basic',
        $options,
    ));
}
$ADMIN->add('plenumformplugins', $temp);

foreach ($pluginmanager->get_plugins_of_type('plenumform') as $plugin) {
    $plugin->load_settings($ADMIN, 'plenumformplugins', $hassiteconfig);
}

$ADMIN->add('modplenum', new admin_category(
    'plenumtypeplugins',
    new lang_string('subplugin_plenumtype_plural', 'mod_plenum'),
    !$module->is_enabled()
));
$temp = new admin_settingpage('manageplenumtypeplugins', new lang_string('manageplenumtypeplugins', 'mod_plenum'));
$temp->add(new \mod_plenum\admin\manage_plenumtype_plugins_page());
$ADMIN->add('plenumtypeplugins', $temp);

foreach ($pluginmanager->get_plugins_of_type('plenumtype') as $plugin) {
    $plugin->load_settings($ADMIN, 'plenumtypeplugins', $hassiteconfig);
}

$settings = null;
