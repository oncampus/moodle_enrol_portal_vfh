<?php


require_once($CFG->dirroot.'/enrol/portal/lib.php');

$courseidnumber='B';
$username='student';
$role=5;

require(dirname(dirname(dirname(__FILE__))).'/config.php'); // global moodle config file.
$enrol_portal = new enrol_portal_plugin();
$enrol_portal->enrol_to_course($courseidnumber,$username,$role);     