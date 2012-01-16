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
  
require_once($CFG->dirroot.'/report/configreports/plugin.class.php');

class plugin_max extends plugin_base{
    
    function init() {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('max','report_configreports');
        $this->reporttypes = array('courses','users','sql','timeline','categories');
    }
    
    function summary($data) {
        global $DB, $CFG;

        if ($this->report->type != 'sql') {
            $components = cr_unserialize($this->report->components);
            if (!is_array($components) || empty($components['columns']['elements']))
                print_error('nocolumns');
            
            $columns = $components['columns']['elements'];
            $i = 0;
            foreach ($columns as $c) {
                if ($i == $data->column)
                    return $c['summary'];
                $i++;
            }
        }
        else{

            require_once($CFG->dirroot.'/report/configreports/report.class.php');
            require_once($CFG->dirroot.'/report/configreports/reports/'.$this->report->type.'/report.class.php');
    
            $reportclassname = 'report_'.$this->report->type;    
            $reportclass = new $reportclassname($this->report);
    
            $components = cr_unserialize($this->report->components);
            $config = (isset($components['customsql']['config']))? $components['customsql']['config'] : new stdclass;    
    
            if (isset($config->querysql)) {
        
                $sql =$config->querysql;
                $sql = $reportclass->prepare_sql($sql);
                if ($rs = $reportclass->execute_query($sql)) {
                    $row = array_shift($rs);
                    $i = 0;
                    foreach ($row as $colname=>$value) {
                        if ($i == $data->column)
                            return str_replace('_', ' ', $colname);
                        $i++;
                    }
                    $rs->close();
                }
            }        
        }

        return '';
    }
    
    function execute($rows) {

        $result = 0;

        foreach ($rows as $r) {
            if (is_numeric($r) && $result < $r)
                $result = $r;
        }

        return $result;
    }
    
}

?>