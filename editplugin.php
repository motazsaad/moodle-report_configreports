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
 * A report plugin for creating Configurable Reports
 * @package report
 * @subpackage configreports
 * @copyright Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    
    require_once($CFG->dirroot."/report/configreports/locallib.php");
    

    $id = required_param('id', PARAM_INT);
    $comp = required_param('comp', PARAM_ALPHA);
    $cid = optional_param('cid', '', PARAM_ALPHANUM);
    $pname = optional_param('pname', '', PARAM_ALPHA);
    
    $moveup = optional_param('moveup', 0, PARAM_INT);
    $movedown = optional_param('movedown', 0, PARAM_INT);
    $delete = optional_param('delete', 0, PARAM_INT);
    
    if (!$pname) {
        redirect(new moodle_url('/report/configreports/editcomp.php', array('id' => $id, 'comp' => $comp)));
        exit;
    }
    
    if (! $report = $DB->get_record('report_configreports',array('id' => $id)))
        print_error('reportdoesnotexists');

    if (! $course = $DB->get_record("course",array( "id" =>  $report->courseid)) ) {
        print_error("No such course id");
    }    
    
    // Force user login in course (SITE or Course)
    if ($course->id == SITEID) {
        require_login();
        $context = get_context_instance(CONTEXT_SYSTEM);
    }    
    else{
        require_login($course->id);
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
    }

    if (! has_capability('report/configreports:managereports', $context) && ! has_capability('report/configreports:manageownreports', $context))
        print_error('badpermissions');
        
    if (! has_capability('report/configreports:managereports', $context) && $report->ownerid != $USER->id)
        print_error('badpermissions');

    require_once($CFG->dirroot.'/report/configreports/report.class.php');
    require_once($CFG->dirroot.'/report/configreports/reports/'.$report->type.'/report.class.php');
    
    $reportclassname = 'report_'.$report->type;    
    $reportclass = new $reportclassname($report->id);
    
    if (!in_array($comp,$reportclass->components))
        print_error('badcomponent');
    
    $PAGE->set_context($context);    
    $PAGE->set_pagelayout('incourse');    
    $PAGE->set_url('/report/configreports/editplugin.php', array('id'=>$id,'comp'=>$comp,'cid'=>$cid,'pname'=>$pname));
    
    $cdata = null;
    $plugin = '';
    if (!$cid) {
        if (filetype($CFG->dirroot.'/report/configreports/components/'.$comp.'/'.$pname) == 'dir') {
            $plugin = $pname;
        }
    }
    else {
        $components = cr_unserialize($report->components);
        $elements = isset($components[$comp]['elements'])? $components[$comp]['elements'] : array();

        if ($elements)
            foreach ($elements as $e) {
                if ($e['id'] == $cid) {
                    $cdata = $e;
                    $plugin = $e['pluginname'];
                    break;
                }
            }
    
        if (($moveup || $movedown || $delete) && confirm_sesskey()) {
            foreach ($elements as $index=>$e) {
                if ($e['id'] == $cid) {
                    if ($delete) {
                        unset($elements[$index]);
                        break;
                    }
                    $newindex = ($moveup)? $index - 1 : $index +1;
                    $tmp = $elements[$newindex];
                    $elements[$newindex] = $e;
                    $elements[$index] = $tmp;
                    break;
                }
            }
            $components[$comp]['elements'] = $elements;
            $report->components = cr_serialize($components);
            $DB->update_record('report_configreports',$report);
            redirect(new moodle_url('/report/configreports/editcomp.php', array('id' => $id, 'comp' => $comp)));
            exit;
        }
    }

    if (!$plugin || $plugin != $pname)
        print_error('nosuchplugin');
    
    require_once($CFG->dirroot.'/report/configreports/plugin.class.php');
    require_once($CFG->dirroot.'/report/configreports/components/'.$comp.'/'.$pname.'/plugin.class.php');
    $pluginclassname = 'plugin_'.$pname;
    $pluginclass = new $pluginclassname($report);    
    
    if (isset($pluginclass->form) && $pluginclass->form) {
        require_once($CFG->dirroot.'/report/configreports/component.class.php');    
        require_once($CFG->dirroot.'/report/configreports/components/'.$comp.'/component.class.php');    
        $componentclassname = 'component_'.$comp;
        $compclass = new $componentclassname($report->id);

        require_once($CFG->dirroot.'/report/configreports/components/'.$comp.'/'.$pname.'/form.php');
        $classname = $pname.'_form';

        $formurlparams = array('id' => $id, 'comp' => $comp, 'pname' => $pname);
        if ($cid) {
            $formurlparams['cid'] = $cid;
        }
        $formurl = new moodle_url('/report/configreports/editplugin.php', $formurlparams);
        $editform = new $classname($formurl,compact('comp','cid','id','pluginclass','compclass','report','reportclass'));
    
        if (!empty($cdata)) {
            $editform->set_data($cdata['formdata']);
        }
    
        if ($editform->is_cancelled()) {
            if (!empty($report))
                redirect($CFG->wwwroot.'/report/configreports/editreport.php?id='.$report->id);
            else
                redirect($CFG->wwwroot.'/report/configreports/editreport.php');
        }
        else if ($data = $editform->get_data()) {    
            add_to_log($report->courseid, 'configurable_reports', 'edit', '', $report->name);
            if (!empty($cdata)) {
                // cr_serialize() will add slashes
        
                $cdata['formdata'] = $data;
                $cdata['summary'] = $pluginclass->summary($data);
                $elements = cr_unserialize($report->components);
                $elements = isset($elements[$comp]['elements'])? $elements[$comp]['elements'] : array();

        
                if ($elements)
                    foreach ($elements as $key=>$e) {
                        if ($e['id'] == $cid) {
                            $elements[$key] = $cdata;
                            break;
                        }
                    }
        
                $allelements = cr_unserialize($report->components);
                $allelements[$comp]['elements'] = $elements;
        
                $report->components = cr_serialize($allelements);
                if (!$DB->update_record('report_configreports',$report)) {
                    print_error('errorsaving');
                }
                else{
                    redirect(new moodle_url('/report/configreports/editcomp.php', array('id' => $id, 'comp' => $comp)));
                    exit;
                }
            
            }
            else{
        
                $allelements = cr_unserialize($report->components);
        
                $uniqueid = random_string(15);
                while(strpos($report->components,$uniqueid) !== false) {
                    $uniqueid = random_string(15);
                }
        
                $cdata = array('id' => $uniqueid, 'formdata' => $data, 'pluginname' => $pname, 'pluginfullname' => $pluginclass->fullname, 'summary' => $pluginclass->summary($data));
        
                $allelements[$comp]['elements'][] = $cdata;
                $report->components = cr_serialize($allelements, false);
                if (!$DB->update_record('report_configreports',$report)) {
                    print_error('errorsaving');
                }
                else{
                    redirect(new moodle_url('/report/configreports/editcomp.php', array('id' => $id, 'comp' => $comp)));
                    exit;
                }
            }
        }
    }
    else{
        $allelements = cr_unserialize($report->components);
        
        $uniqueid = random_string(15);
        while(strpos($report->components,$uniqueid) !== false) {
            $uniqueid = random_string(15);
        }

        $cdata = array('id' => $uniqueid, 'formdata' => new stdclass, 'pluginname' => $pname, 'pluginfullname' => $pluginclass->fullname, 'summary' => $pluginclass->summary(new stdclass));

        $allelements[$comp]['elements'][] = $cdata;
        $report->components = cr_serialize($allelements);
        if (!$DB->update_record('report_configreports',$report)) {
            print_error('errorsaving');
        }
        else{
            redirect(new moodle_url('/report/configreports/editcomp.php', array('id' => $id, 'comp' => $comp)));
            exit;
        }
    }
    
    $title = format_string($report->name).' '.get_string($comp,'report_configreports');    

    $navlinks = array();
    $navlinks[] = array('name' => get_string('managereports','report_configreports'), 'link' => $CFG->wwwroot.'/report/configreports/managereport.php?courseid='.$report->courseid, 'type' => 'title');
    $navlinks[] = array('name' => $title, 'link' => $CFG->wwwroot.'/report/configreports/editcomp.php?id='.$id.'&amp;comp='.$comp, 'type' => 'title');
    $navlinks[] = array('name' => get_string($pname,'report_configreports'), 'link' => null, 'type' => 'title');
    $navigation = build_navigation($navlinks);
    
    $PAGE->set_title($title);    
    $PAGE->set_heading( $title);    
    $PAGE->set_cacheable( true);
    
    echo $OUTPUT->header();

    $currenttab = $comp;
    include('tabs.php');

    if ($pluginclass->form)
        $editform->display();
    
    echo $OUTPUT->footer();

?>