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
 * @package     plenumtype_adjorn
 * @category    admin
 * @copyright   2023 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings = new admin_settingpage('plenumtype_adjorn_settings', new lang_string('pluginname', 'plenumtype_adjorn'));
if ($hassiteconfig) {
    // Add plugin settings.
    $name = new lang_string('requiresecond', 'plenumtype_adjorn');
    $description = new lang_string('requiresecond_help', 'plenumtype_adjorn');
    $setting = new admin_setting_configcheckbox(
        'plenumtype_adjorn/requiresecond',
        $name,
        $description,
        1
    );
    $settings->add($setting);
}
