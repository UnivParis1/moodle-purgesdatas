<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');


class purgesdata {
	private $_typePurges = array( 	'becrazy',
									'init',
									'cohort',
									'log',
									'assign' );
	
	private function verifUSer(){
		global $USER;
		if (($USER->username == 'emchemlali@univ-paris1.fr') || ($USER->username == 'prigaux@univ-paris1.fr')) return true;
		return false;
	}
	
	public function purge($annee,$type,$cli = false) {
		global $DB,$CFG,$USER;
		if (empty($annee)) return false;
		if (! $this->verifUSer()) return false;
		if (!in_array($type,$this->_typePurges) ) return false;
		switch ($type) {
			case 'becrazy' : {
				$this->purgeCourses($annee,$cli);
				break;
			}
			case 'init' : {
				$this->purgeCourses($annee,$cli);
				break;
			}
			case 'log' : {
				$this->purgeLogs($annee,$cli);
				break;
			}
			case 'cohort' : {
				$this->purgeCohorts($annee,$cli);
				break;
			}
			case 'assign' : {
				$this->purgeAssignements($annee,$cli);
				break;
			}
			default : break;
			
		}
		return true;
	}	
	
	public function purgeAll($annee, $cli =false) {
		global $DB,$CFG,$USER;
		if (empty($annee)) return false;
		if (! $this->verifUSer()) return false;
		$this->deleteCourses($annee,$cli);
		
	}
	
	private function purgeCourses($annee,$cli=false) {
		global $DB;
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
			if ($cli)	
				echo '====================== '.$strdeletingcourse.' ======================'; 
			else 	
				echo '<h3>'.$strdeletingcourse. '</h3>';
			// We do this here because it spits out feedback as it goes.
			delete_course($course);
			if ($cli)	
				echo '-- '.$courseshortname. 'deleted
';			
			else 
				echo '<h4>'.get_string("deletedcourse", "", $courseshortname). '</h4>';
			$cpt_course++;
		}
		// Update course count in categories.
		fix_course_sortorder();
	if ($cli)	
		echo '
-- '.$cpt_course. ' courses deleted.';
	else 
		echo '<p><strong>'.$cpt_course. ' courses deleted.</strong></p>';
	}
	
	private function purgeCohorts($cli=false) {
		global $DB;
		if ($cli)	
			echo '====================== Suppression des cohortes non utilisées ======================';
		else 
			echo '<h3>Suppression des cohortes non utilisées</h3>';
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
		if ($cli)	
			echo '
-- '.$nb_supressions. ' rows deleted from the table cohort.
====================== Suppression des lignes de la table cohort_members non utilisées ======================';
		else 
			echo '<p><strong>'.$nb_supressions. ' rows deleted from the table cohort..</strong></p>
				<h4>Suppression des lignes de la table cohort_members non utilisées</h4>';
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
		if ($cli)	
			echo '
	-- '.$nb_supressions. ' rows deleted from the table cohort_members.'; 
		else 
			echo '<p><strong>'.$nb_supressions. ' rows deleted from the table cohort_members.</strong></p>';
	}
	
	private function purgeLogs($cli=false) {
		global $DB;
		if ($cli)	
			echo '====================== Suppression des lignes de log non utiles ======================';
		else 
			echo '<h3>Suppression des lignes de log non utiles</h3>';
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
		if ($cli)	
			echo '
	-- '.$nb_supressions. ' rows deleted from the table log.'; 
		else 
			echo '<p><strong>'.$nb_supressions. ' rows deleted from the table log.</strong></p>';
		
	}
}