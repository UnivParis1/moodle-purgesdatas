<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('../../lib/accesslib.php');
require_login();

ini_set('max_execution_time', 1200);
ini_set('memory_limit', '-1');
$idcategorie=0;
$url = new moodle_url('/local/purgesdatas/index.php');
admin_externalpage_setup('local_purgesdatas');
$renderer = $PAGE->get_renderer('core', 'register');
$PAGE->set_url($url);
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/purgesdatas/css/jquery-ui.css'));
$PAGE->requires->css(new moodle_url('/local/purgesdatas/css/style.css'));
$PAGE->requires->js(new moodle_url('/local/jquery/jquery.js'), true);
/**
 * vérification que l'utilisateur est un administrateur
 */

if (is_siteadmin()) {
	$annee = 0;
	if (isset($_REQUEST['annee'])) $annee = $_REQUEST['annee'];	
	if ($annee==0) {
		$PAGE->set_heading(get_string('heading', 'local_purgesdatas'));
		$PAGE->set_heading(get_string('heading', 'local_purgesdatas'));
		$PAGE->set_title(get_string('title_index', 'local_purgesdatas'));
	} else {
		$select_annee = "SELECT name from {course_categories} where id=? ;";
		$obj = $DB->get_record_sql($select_annee, array($annee));
		$libelle_annee = '';
		if (!empty($obj->name)) $libelle_annee = $obj->name;
		$PAGE->set_heading( str_replace('[annee]', $libelle_annee, get_string('heading_with_year', 'local_purgesdatas')));
		$PAGE->set_heading( str_replace('[annee]', $libelle_annee, get_string('heading_with_year', 'local_purgesdatas')));
		$PAGE->set_title( str_replace('[annee]', $libelle_annee, get_string('title_index_with_year', 'local_purgesdatas')));
	}
	echo $OUTPUT->header();
	echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
	
	if (!empty($annee) && ($USER->username == 'emchemlali@univ-paris1.fr') || ($USER->username == 'prigaux@univ-paris1.fr') ) {
	    $SELECT = "	SELECT C.id
	        	FROM {course} C 
	        	INNER JOIN {course_categories} CC on ( C.category =  CC.id) 
	        	WHERE ( CC.path LIKE ?  OR CC.path LIKE ? )";
		$courses = $DB->get_records_sql($SELECT,array('/'.$annee.'/%','%/'.$annee.'/%'));
		$data = array();
		foreach ($courses as $i=>$course) {
			$data[] = array('id'	=> $course->id);
		}
		
		echo '
			 <script type="text/javascript">
			 $(function() {
				var json_table_course = '.json_encode($data).';
				var img= \'<div style="margin-left: auto;margin-right: auto;width:200px;"><img src="img/loading.gif"></div><div style="margin-left: auto;margin-right: auto;width:200px;padding:5em;text-align:center">Veuillez patienter ...</div>\';
				for(var i=0;i<json_table_course.length;i++){
			        var obj = json_table_course[i];
			        for(var key in obj){
			            var attrName = key;
			            var attrValue = obj[key];
			            $.ajax({
						    type: "POST",
						    url: "delete.php",
						    data: "&course="+obj[key],
						    success:
						    function(retour){
								text=$("#div-retour").html();
								$("#div-retour").html(text+"<p>"+retour+"</p>"+img);
						    }
						});	
					}
			    }
			 });
			 </script>
			<div id="div-retour" style="width:100%; min-height:200px;"><p></p></div>
		';
	}
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer(); 