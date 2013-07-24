<?php

 
error_reporting(0);
ini_set("display_errors",0);
ob_start();

 

class enrol_portal_plugin extends enrol_plugin {

	public function enrol_to_course($courseidnumber,$username,$roleid=5) {
		global $DB;

		$return = true;
		$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
		$user = $DB->get_record('user', array('username'=>$username));
		libsynclog("user->id = ".$user->id." ");
	libsyncLog("course = $courseidnumber :: username = $username :: role = $roleid ");
		$sql = "SELECT c.id, c.visible, e.id as enrolid
                          FROM {course} c
                          JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'portal')
                         WHERE c.id = :courseid";
		$params = array('courseid'=>$course->id);
		if (!($course_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
			$course_instance = new stdClass();
			$course_instance->id = $course->id;
			$course_instance->visible = $course->visible;
			$course_instance->enrolid = $this->add_instance($course_instance);
		}

		if (!$instance = $DB->get_record('enrol', array('id'=>$course_instance->enrolid))) {
			libsynclog("error on fetching instance!");
		 
		}

		$result = $this->enrol_user($instance, $user->id, $roleid);
		if (!$result) { 
			libsynclog("error on native enrol_user(instance, user, roleid) ");
			$return =  false; 
			}
		$DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$user->id));

	return $return;

	}

	public function unenrol_from_course($courseidnumber,$username) {
		global $DB;

		$return = true;
		
		libSyncLog("unenroling '$username' from course '$courseidnumber' ...");
		
		##
		###
		
		 
			$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
			$user = $DB->get_record('user', array('username'=>$username));
			$context = context_course::instance($course->id);
			
			# Hack: manuell enrollte User müssen via portaldienst enrolled sein
			$DB->set_field('role_assignments', 'component', 'enrol_portal', array ('contextid'=>$context->id,'userid'=>$user->id ));
			 
			
		###
		##
		$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
		$user = $DB->get_record('user', array('username'=>$username));

		$sql = "SELECT c.id, c.visible, e.id as enrolid
                          FROM {course} c
                          JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'portal')
                         WHERE c.id = :courseid";
		$params = array('courseid'=>$course->id);
		if (!($course_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
			$course_instance = new stdClass();
			$course_instance->id = $course->id;
			$course_instance->visible = $course->visible;
			$course_instance->enrolid = $this->add_instance($course_instance);
		}

		if (!$instance = $DB->get_record('enrol', array('id'=>$course_instance->enrolid))) {
			$return = false;
		}

		$this->unenrol_user($instance,$user->id);
    $this->force_unenrol_from_course($courseidnumber,$username);
 		$return = true;
 		
 	return $return;
	}



 public function force_unenrol_from_course($courseidnumber,$username) {
		global $DB;

		$return = true;
		
		libSyncLog("[f] unenroling '$username' from course '$courseidnumber' ...");
		
		##
		###
		
		 
			$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
			$user = $DB->get_record('user', array('username'=>$username));
			$context = context_course::instance($course->id);
			
			libSyncLog("[f] userID = ".$user->id);
			libSyncLog("[f] ContextID = ".$context->id);
			
			
			# Hack: manuell enrollte User müssen via portaldienst enrolled sein
			$DB->set_field('role_assignments', 'component', 'enrol_portal', array ('contextid'=>$context->id,'userid'=>$user->id ));
			
			
		 
		$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
		$user = $DB->get_record('user', array('username'=>$username));

		// $sql = "SELECT c.id, c.visible, e.id as enrolid
                          // FROM {course} c
                          // JOIN {enrol} e ON (e.courseid = c.id AND  e.enrol in ('portal','manual') )
                         // WHERE c.id = :courseid";
						 
		$sql = "SELECT c.id, c.visible, e.id as enrolid
                          FROM {course} c
                          JOIN {enrol} e ON (e.courseid = c.id AND (e.enrol = 'portal' or e.enrol = 'manual') )
                         WHERE c.id = :courseid
                         order by enrol desc";
						 
		$sql = "SELECT c.id, c.visible, e.id as enrolid
                          FROM {course} c
                          JOIN {enrol} e ON (e.courseid = c.id AND (e.enrol = 'manual') )
                         WHERE c.id = :courseid
                         order by enrol desc";
						 
		$params = array('courseid'=>$course->id);
		if (!($course_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
			$course_instance = new stdClass();
			$course_instance->id = $course->id;
			$course_instance->visible = $course->visible;
			$course_instance->enrolid = $this->add_instance($course_instance);
			libSyncLog('[f] no courseinstance found!');
		} else {
			$this->allow_unenrol($course_instance);
			libSyncLog('[f] courseinstance found: '.$course_instance->enrolid);
		}
		$DB->set_field('enrol', 'enrol', 'portal', array ('id'=>$course_instance->enrolid ));
		
		$DB->set_field('enrol','status', '0' , array('enrol'=>'portal','courseid'=>$course_instance->id));
		libSyncLog("[f] set_field() done...!");
		
		libSyncLog("[f] course_instance_id = ".$course_instance->enrolid);
		
		if (!$instance = $DB->get_record('enrol', array('id'=>$course_instance->enrolid))) {
			$return = false;
			libSyncLog('[f] wrong instance!');
		}
		
		try {
			$this->unenrol_user($instance,$user->id);
			libSyncLog("[f] ok!");
		} catch (Exception $e) {
			libSyncLog("[f] Exeception: ".$e->getMessage() );
		}
		libSyncLog("[f] ablauf");
		$DB->set_field('enrol', 'enrol', 'manual', array ('id'=>$course_instance->enrolid ));
			
 	return $return;
	}
	



public function enrol_update_role($courseidnumber,$username,$roleid=5, $status = 1) {
		global $DB;
	 

		$enrolStatus = 0;
		if ($status == 0) {				// courseuser inaktiv?
			$enrolStatus = 1;
		}
		libSyncLog("updating '$username' in '$courseidnumber' withe role '$roleid' and status = $status ");
		
		$result = false;
		try {
			$course = $DB->get_record('course', array('idnumber'=>$courseidnumber));
			$user = $DB->get_record('user', array('username'=>$username));
	
			$context = context_course::instance($course->id);
			
			libSyncLog("context-id : ".$context->id);
			
			# Hack: manuell enrollte User müssen via portaldienst enrolled sein
			$DB->set_field('role_assignments', 'component', 'enrol_portal', array ('contextid'=>$context->id,'userid'=>$user->id ));
			
			$DB->set_field('role_assignments', 'roleid', $roleid, array('contextid'=>$context->id,'userid'=>$user->id,'component'=>'enrol_portal')); 
			$itemid = $DB->get_field('role_assignments' , 'itemid' , array('contextid'=>$context->id,'userid'=>$user->id,'component'=>'enrol_portal') );
			$itemid2 = $DB->get_field('role_assignments' , 'itemid' , array('contextid'=>$context->id,'userid'=>$user->id,'component'=>'') );
			
		 libSyncLog("itemid1 = $itemid , itemid2 = $itemid2 ");
			
		 	$eid=$DB->get_records_sql("SELECT e.id FROM mdl_user_enrolments as ue left join mdl_enrol as e on ue.enrolid=e.id WHERE e.courseid=".$course->id." and ue.userid=".$user->id);

		 	$eo=array_pop($eid);
		 	$eoid=$eo->id;
		// libSyncLog("eid = $eoid");		 	
		 
			$DB->set_field('user_enrolments' , 'status' , $enrolStatus, array('enrolid' => $itemid , 'userid' => $user->id)  );
			$DB->set_field('user_enrolments' , 'status' , $enrolStatus, array('enrolid' => $itemid2,  'userid' => $user->id )  );
			
						
			$DB->set_field('user_enrolments' , 'status' , $enrolStatus, array('enrolid' => $eoid,  'userid' => $user->id )  );
			
			$result = true; 
	} catch (Exception $e) {
		$result = false;
	}
	
	return $result;
	
	}
	
	
	/*
	 public function edit_enrolment($userenrolment, $data) {
        //Only allow editing if the user has the appropriate capability
        //Already checked in /enrol/users.php but checking again in case this function is called from elsewhere
        list($instance, $plugin) = $this->get_user_enrolment_components($userenrolment);
        if ($instance && $plugin && $plugin->allow_manage($instance) && has_capability("enrol/$instance->enrol:manage", $this->context)) {
            if (!isset($data->status)) {
                $data->status = $userenrolment->status;
            }
            $plugin->update_user_enrol($instance, $userenrolment->userid, $data->status, $data->timestart, $data->timeend);
            return true;
        }
        return false;
    }
  */  
    
	
 
 
 
}

 
 
 function libSyncLog($mess) {
 	
 	# define("SYNCLOGFILE" , "/opt/www/moodle.oncampus.de/moodle/_syncLog/synclog".date("Y-m-d",date("U")).".txt" );
 	#mail("illi@fh-luebeck.de","log",$mess);
 	
 	$mess = utf8_decode($mess);
 		$handle = fopen( "/opt/www/moodledata/_syncLog/synclog-".date("Y-m-d",date("U")).".txt" ,"a+");
 		$prefix = date("H:i:s",date("U"))."[enrol-lib:]  ";
 		fwrite ($handle, $prefix.$mess."\r\n");
 		fclose($handle);
 }
 
 