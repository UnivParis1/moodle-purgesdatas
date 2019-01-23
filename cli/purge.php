<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions

// now get cli options
list($options, $unrecognized) = cli_get_params(
									array(
        								'help'		=>	false,
        								'init'		=>	false,
        								'becrazy'	=>	false,
        								'cohort'	=>	false,
        								'log'		=>	false, 
										'assign' 	=> 	false ),
    									array('h'=>'help', 'i'=>'init', 'c'=>'cohort', 'l'=>'log', 'a'=>'assign')
    									);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Purge all courses for a categorie 
Options:
 --becrazy=n		Apply All purges for the category n
-i=n, --init=n		Apply to all child course of this category n
-h, --help		Print out this help
-c, --cohort		Purge table cohort and cohort_members 
-l, --log		Purge table log where userid not in table user or cohortid not in table cohort
-a, --assign		Purge table assignment_user_mapping where userid not in table user or assignment not in table assignment
Example:
/usr/bin/php local/purgesdatas/cli/purge.php --init=10
For the university categories you can specify :
";
	
	$sql= "SELECT id, name from mdl_course_categories where parent=0 and name like'%20%';";
	$cats = $DB->get_records_sql($sql);
	foreach($cats as $i=>$row) {
		$help .= ' *** n='.$row->id . ' for '. $row->name.' 
'; 
	}

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}
$CFG->debug = DEBUG_NORMAL;

if ( $options['init'] ) {
    $annee =  $options['init'];
    $SELECT = "	SELECT C.*
        	FROM {course} C 
        	INNER JOIN {course_categories} CC on ( C.category =  CC.id) 
        	WHERE ( CC.path LIKE ?  OR CC.path LIKE ? )";
	$courses = $DB->get_records_sql($SELECT,array('/'.$annee.'/%','%/'.$annee.'/%'));
	$data = array();
	$cpt_course = 0;
	foreach ($courses as $i=>$course) {
		$coursecontext = context_course::instance($course->id);
		$categorycontext = context_coursecat::instance($course->category);
		$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
		$coursefullname = format_string($course->fullname, true, array('context' => $coursecontext));
		$strdeletingcourse = get_string("deletingcourse", "", $courseshortname);
		echo '====================== '.$strdeletingcourse.' ======================';
		// We do this here because it spits out feedback as it goes.
		delete_course($course);
	    $DB->delete_records("log", array("course" => $course->id));
	    echo '
	     ++ log supprimés ++';
		echo ' --- '.$courseshortname. 'deleted';	
		$cpt_course++;
	}
		// Update course count in categories.
		fix_course_sortorder();
	echo '
-- '.$cpt_course. ' courses deleted.
';
		
    return 0;
}


if ( $options['cohort'] ) {
		echo '====================== Suppression des cohortes non utilisées ======================';
	$nbCohortBeforePurge = 0;
	$selectCohortBeforePurge = 'select count(id) as nb from {cohort}';
	$cohortsBeforePurge = $DB->get_record_sql($selectCohortBeforePurge);
	if (!empty($cohortsBeforePurge->nb)) $nbCohortBeforePurge = $cohortsBeforePurge->nb;
	// On selectionne la periode de cohortes que l'on ne veut surtout pas supprimé (la dernière)
	$select = 	"	SELECT value from {config_plugins} where plugin = ? and name = ?";
	$obj = $DB->get_record_sql($select,array('local_cohortsyncup1','cohort_period'));
	if (!empty($obj->value)) {
		$DB->execute("delete from {cohort} where id not in( select me.customint1 from {enrol} as me inner join {user_enrolments} as mue on (mue.enrolid = me.id)  where me.enrol=?) and up1period != ? and up1period!= ?",array('cohort',$obj->value,''));
	}
	$nbCohortAfterPurge = 0;
	$selectCohortAfterPurge = 'select count(id) as nb from {cohort_members}';
	$cohortAfterPurge = $DB->get_record_sql($selectCohortAfterPurge);
	if (!empty($cohortAfterPurge->nb)) $nbCohortMembersAfterPurge = $cohortAfterPurge->nb;
	$nb_supressions = $nbCohortBeforePurge - $nbCohortAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table cohort.
====================== Suppression des lignes de la table cohort_members non utilisées ======================';
	$nbCohortMembersBeforePurge = 0;
	$selectCohortMembersBeforePurge = 'select count(id) as nb from {cohort_members}';
	$cohortMembersBeforePurge = $DB->get_record_sql($selectCohortMembersBeforePurge);
	if (!empty($cohortMembersBeforePurge->nb)) $nbCohortMembersBeforePurge = $cohortMembersBeforePurge->nb;
	$DB->execute("delete from {cohort_members} where userid not in (select id from {user})");
	$DB->execute("delete from {cohort_members} where cohortid not in (select id from {cohort})");
	$nbCohortMembersAfterPurge = 0;
	$selectCohortMembersAfterPurge = 'select count(id) as nb from {cohort_members}';
	$cohortMembersAfterPurge = $DB->get_record_sql($selectCohortMembersAfterPurge);
	if (!empty($cohortMembersAfterPurge->nb)) $nbCohortMembersAfterPurge = $cohortMembersAfterPurge->nb;
	$nb_supressions = $nbCohortMembersBeforePurge - $nbCohortMembersAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table cohort_members.'; 
    return 0;
}

if ( $options['log'] ) {
		echo '====================== Suppression des lignes de log non utiles ======================';
	$nbLogBeforePurge = 0;
	$selectLogBeforePurge = 'select count(id) as nb from {log}';
	$logBeforePurge = $DB->get_record_sql($selectLogBeforePurge);
	if (!empty($logBeforePurge->nb)) $nbLogBeforePurge = $logBeforePurge->nb;
	$DB->execute("delete from {log} where userid not in (select id from {user})");
	$DB->execute("delete from {log} where course not in (select id from {course})");
	$nbLogAfterPurge = 0;
	$selectLogAfterPurge = 'select count(id) as nb from {log}';
	$logAfterPurge = $DB->get_record_sql($selectLogAfterPurge);
	if (!empty($logAfterPurge->nb)) $nbLogAfterPurge = $logAfterPurge->nb;
	$nb_supressions = $nbLogBeforePurge - $nbLogAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table log.'; 
    return 0;
}

if ( $options['assign'] ) {
		echo '
====================== Suppression des lignes de assignment_user_mapping non utilisées ======================';
	$nbAssignmentUserMappingBeforePurge = 0;
	$selectAssignmentUserMappingBeforePurge = 'select count(id) as nb from {assignment_user_mapping}';
	$assignmentUserMappingBeforePurge = $DB->get_record_sql($selectAssignmentUserMappingBeforePurge);
	if (!empty($assignmentUserMappingBeforePurge->nb)) $nbAssignmentUserMappingBeforePurge = $assignmentUserMappingBeforePurge->nb;
	$DB->execute("delete from {assignment_user_mapping} where userid not in (select id from {user})");
	$DB->execute("delete from {assignment_user_mapping} where assignment not in (select id from {assignment})");
	$nbAssignmentUserMappingAfterPurge = 0;
	$selectAssignmentUserMappingAfterPurge = 'select count(id) as nb from {assignment_user_mapping}';
	$assignmentUserMappingAfterPurge = $DB->get_record_sql($selectAssignmentUserMappingAfterPurge);
	if (!empty($assignmentUserMappingAfterPurge->nb)) $nbAssignmentUserMappingAfterPurge = $assignmentUserMappingAfterPurge->nb;
	$nb_supressions = $nbAssignmentUserMappingBeforePurge - $nbAssignmentUserMappingAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table assignment_user_mapping.
'; 
    return 0;
}

if ($options['becrazy']) {
    $annee =  $options['becrazy'];
    $SELECT = "	SELECT C.*
        	FROM {course} C 
        	INNER JOIN {course_categories} CC on ( C.category =  CC.id) 
        	WHERE ( CC.path LIKE ?  OR CC.path LIKE ? )";
	$courses = $DB->get_records_sql($SELECT,array('/'.$annee.'/%','%/'.$annee.'/%'));
	$data = array();
	$cpt_course = 0;
	foreach ($courses as $i=>$course) {
		$coursecontext = context_course::instance($course->id);
		$categorycontext = context_coursecat::instance($course->category);
		$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
		$coursefullname = format_string($course->fullname, true, array('context' => $coursecontext));
		$strdeletingcourse = get_string("deletingcourse", "", $courseshortname);
		echo '
====================== '.$strdeletingcourse.' ======================';
		// We do this here because it spits out feedback as it goes.
		delete_course($course);
	    $DB->delete_records("log", array("course" => $course->id));
	    echo '
	     ++ log supprimés ++';
		echo ' --- '.$courseshortname. 'deleted';	
		$cpt_course++;
	}
		// Update course count in categories.
		fix_course_sortorder();
	echo '
-- '.$cpt_course. ' courses deleted.

====================== Suppression des cohortes non utilisées ======================';
	$nbCohortBeforePurge = 0;
	$selectCohortBeforePurge = 'select count(id) as nb from {cohort}';
	$cohortsBeforePurge = $DB->get_record_sql($selectCohortBeforePurge);
	if (!empty($cohortsBeforePurge->nb)) $nbCohortBeforePurge = $cohortsBeforePurge->nb;
	// On selectionne la periode de cohortes que l'on ne veut surtout pas supprimé (la dernière)
	$select = 	"	SELECT value from {config_plugins} where plugin = ? and name = ?";
	$obj = $DB->get_record_sql($select,array('local_cohortsyncup1','cohort_period'));
	if (!empty($obj->value)) {
		$DB->execute("delete from {cohort} where id not in( select me.customint1 from {enrol} as me inner join {user_enrolments} as mue on (mue.enrolid = me.id)  where me.enrol=?) and up1period != ? and up1period!= ?",array('cohort',$obj->value,''));
	}
	$nbCohortAfterPurge = 0;
	$selectCohortAfterPurge = 'select count(id) as nb from {cohort_members}';
	$cohortAfterPurge = $DB->get_record_sql($selectCohortAfterPurge);
	if (!empty($cohortAfterPurge->nb)) $nbCohortMembersAfterPurge = $cohortAfterPurge->nb;
	$nb_supressions = $nbCohortBeforePurge - $nbCohortAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table cohort.

====================== Suppression des lignes de la table cohort_members non utilisées ======================';
	$nbCohortMembersBeforePurge = 0;
	$selectCohortMembersBeforePurge = 'select count(id) as nb from {cohort_members}';
	$cohortMembersBeforePurge = $DB->get_record_sql($selectCohortMembersBeforePurge);
	if (!empty($cohortMembersBeforePurge->nb)) $nbCohortMembersBeforePurge = $cohortMembersBeforePurge->nb;
	$DB->execute("delete from {cohort_members} where userid not in (select id from {user})");
	$DB->execute("delete from {cohort_members} where cohortid not in (select id from {cohort})");
	$nbCohortMembersAfterPurge = 0;
	$selectCohortMembersAfterPurge = 'select count(id) as nb from {cohort_members}';
	$cohortMembersAfterPurge = $DB->get_record_sql($selectCohortMembersAfterPurge);
	if (!empty($cohortMembersAfterPurge->nb)) $nbCohortMembersAfterPurge = $cohortMembersAfterPurge->nb;
	$nb_supressions = $nbCohortMembersBeforePurge - $nbCohortMembersAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table cohort_members.
====================== Suppression des lignes de log non utiles ======================';
	$nbLogBeforePurge = 0;
	$selectLogBeforePurge = 'select count(id) as nb from {log}';
	$logBeforePurge = $DB->get_record_sql($selectLogBeforePurge);
	if (!empty($logBeforePurge->nb)) $nbLogBeforePurge = $logBeforePurge->nb;
	$DB->execute("delete from {log} where userid not in (select id from {user})");
	$DB->execute("delete from {log} where course not in (select id from {course})");
	$nbLogAfterPurge = 0;
	$selectLogAfterPurge = 'select count(id) as nb from {log}';
	$logAfterPurge = $DB->get_record_sql($selectLogAfterPurge);
	if (!empty($logAfterPurge->nb)) $nbLogAfterPurge = $logAfterPurge->nb;
	$nb_supressions = $nbLogBeforePurge - $nbLogAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table log.
====================== Suppression des lignes de assignment_user_mapping non utilisées ======================';
	$nbAssignmentUserMappingBeforePurge = 0;
	$selectAssignmentUserMappingBeforePurge = 'select count(id) as nb from {assignment_user_mapping}';
	$assignmentUserMappingBeforePurge = $DB->get_record_sql($selectAssignmentUserMappingBeforePurge);
	if (!empty($assignmentUserMappingBeforePurge->nb)) $nbAssignmentUserMappingBeforePurge = $assignmentUserMappingBeforePurge->nb;
	$DB->execute("delete from {assignment_user_mapping} where userid not in (select id from {user})");
	$DB->execute("delete from {assignment_user_mapping} where assignment not in (select id from {assignment})");
	$nbAssignmentUserMappingAfterPurge = 0;
	$selectAssignmentUserMappingAfterPurge = 'select count(id) as nb from {assignment_user_mapping}';
	$assignmentUserMappingAfterPurge = $DB->get_record_sql($selectAssignmentUserMappingAfterPurge);
	if (!empty($assignmentUserMappingAfterPurge->nb)) $nbAssignmentUserMappingAfterPurge = $assignmentUserMappingAfterPurge->nb;
	$nb_supressions = $nbAssignmentUserMappingBeforePurge - $nbAssignmentUserMappingAfterPurge;
	echo '
-- '.$nb_supressions. ' rows deleted from the table assignment_user_mapping.'; 
    return 0;
}
