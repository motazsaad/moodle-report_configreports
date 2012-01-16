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

/** Configurable Reports
 * A report plugin for creating customizable reports
 * @package report
 * @subpackage configreports
 * @copyright Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class component_columns extends component_base{
    
    function init() {
        $this->plugins = true;
        $this->ordering = true;
        $this->form = true;
        $this->help = true;
    }
    
    function process_form() {
        if ($this->form) {
            return true;
        }
    }
    
    function add_form_elements(&$mform,$fullform) {
        global $DB, $CFG;

        $mform->addElement('header', '', get_string('columnandcellproperties','report_configreports'), '');
        
        $mform->addElement('text', 'columname', get_string('name'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('columname', PARAM_TEXT);
        } else {
            $mform->setType('columname', PARAM_CLEAN);
        }

        $mform->addElement('select', 'align', get_string('cellalign','report_configreports'),array('center'=>'center','left'=>'left','right'=>'right'));
        $mform->setAdvanced('align');

        $mform->addElement('text', 'size', get_string('cellsize','report_configreports'));
        $mform->setType('size', PARAM_CLEAN);
        $mform->setAdvanced('size');

        $mform->addElement('select', 'wrap', get_string('cellwrap','report_configreports'),array(''=>'Wrap','nowrap'=>'No Wrap'));
        $mform->setAdvanced('wrap');

        $mform->addRule('columname',get_string('required'),'required');
    }
    
    function validate_form_elements($data,$errors) {
        if (!empty($data['size']) && !preg_match("/^\d+(%|px)$/i",trim($data['size'])))
            $errors['size'] = get_string('badsize','report_configreports');
        return $errors;
    }

    function form_process_data(&$cform) {
        if ($this->form) {
            $data = $cform->get_data();
            // cr_serialize() will add slashes
    
            $components = cr_unserialize($this->config->components);
            $components['columns']['config'] = $data;    
            $this->config->components = cr_serialize($components);
            $DB->update_record('report_configreports',$this->config);
        }
    }
    
    function form_set_data(&$cform) {
        if ($this->form) {
            $fdata = new stdclass;
            $components = cr_unserialize($this->config->components);
                
            $fdata = (isset($components['columns']['config']))? $components['columns']['config']: $fdata;
                            
            $cform->set_data($fdata);    
        }
    }
    
}

?>