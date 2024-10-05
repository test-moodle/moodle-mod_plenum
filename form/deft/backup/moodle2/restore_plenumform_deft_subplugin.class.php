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
 * Define restore step for plenumform_deft subplugin
 *
 * restore subplugin class that provides the data
 * needed to restore one plenumform_deft subplugin.
 *
 * @package     plenumform_deft
 * @copyright   2024 Daniel Thies
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_plenumform_deft_subplugin extends restore_subplugin {
    /**
     * Define subplugin structure
     *
     */
    protected function define_plenum_subplugin_structure() {

        $paths = [];

        $elename = $this->get_namefor('');

        $elename = $this->get_namefor('speaker');
        $elepath = $this->get_pathfor('/deft_speakers/speaker');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes the plenumform_deft element, if it is in the file.
     * @param array $data the data read from the XML file.
     */
    public function process_plenumform_deft($data) {
        global $DB;

        $data = (array) $data + (array) get_config('plenumform_deft');
        $data = (object)$data;
        $data->plenum = $this->get_new_parentid('plenum');
        $DB->insert_record('plenumform_deft', $data);
    }

    /**
     * Processes the speaker elements, if it is in the file.
     * @param array $data the data read from the XML file.
     */
    public function process_plenumform_deft_speaker($data) {
        global $DB;

        $data = (array) $data + (array) get_config('plenumform_deft');
        $data = (object)$data;
        $data->plenum = $this->get_new_parentid('plenum');
        $data->sessionid = 0;
        $oldid = $data->id;
        if (!empty($data->motion)) {
            $data->motion = $this->get_mappingid('plenum_motion', $data->motion);
        }
        $newitemid = $DB->insert_record('plenumform_deft_peer', $data);
        $this->set_mapping('plenumform_deft', $oldid, $newitemid, true);
    }
}
