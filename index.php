<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('../../lib/accesslib.php');
require_login();

ini_set('max_execution_time', 600);
ini_set('memory_limit', '2048M');
$idcategorie=0;
$url = new moodle_url('/local/purgesdatas/index.php');
admin_externalpage_setup('local_purgesdatas');
$renderer = $PAGE->get_renderer('core', 'register');
$PAGE->set_url($url);
$PAGE->requires->css(new moodle_url('/local/purgesdatas/css/jquery-ui.css'));
$PAGE->requires->css(new moodle_url('/local/purgesdatas/css/style.css'));
$PAGE->requires->js(new moodle_url('/local/jquery/jquery.js'), true);
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
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
	
	$sql= "SELECT id, name from mdl_course_categories where parent=0 and name like'%20%';";
	$cats = $DB->get_records_sql($sql);
	$select = '<select name="annee" id="annee">';
	if ($annee == 0) $select .= '<option value="0" selected>--</option>'; else $select .= '<option value="0">--</option>';
	foreach($cats as $i=>$row) {
		if ($annee == $row->id) $select .= '<option value="'.$row->id.'" selected>'.$row->name.'</option>'; else $select .= '<option value="'.$row->id.'">'.$row->name.'</option>';
	}
	$libelle_choose_cat = get_string('choose_cat', 'local_up1reportepiufr');
	$libelle_valider = get_string('ok', 'local_up1reportepiufr');
	$select .= '</select>';
$form = <<< EOF
<form action="index.php" method="GET" >
	<h3> $libelle_choose_cat $select<input type="submit" value="$libelle_valider"></h3>
</form>
EOF;
	echo $form; // insertion du formulaire dans la page
	
	if (!empty($annee)) {
		$SELECT = "	SELECT C.id, C.fullname, C.timemodified 
        	FROM {course} C 
        	INNER JOIN {course_categories} CC on ( C.category =  CC.id) 
        	WHERE CC.path LIKE ?
        	ORDER BY C.timemodified ";
		$courses = $DB->get_records_sql($SELECT,array('/'.$annee.'%'));
		$data = array();
		$cpt = 0;
		foreach ($courses as $i=>$course) {
			$data[] = array(	
							'<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'" target="_BLANK">'.$course->fullname.'</a>',	
							date('d/m/Y', $course->timemodified).'<br /> à '.date('H:i:s', $course->timemodified)
							);
			$cpt ++;
		}
		$table = new html_table();
		$table->head = array(get_string('EPI', 'local_purgesdatas'), get_string('last_modified', 'local_purgesdatas') );
		$table->data = $data;
		
		echo '<h3>'.str_replace('[annee]', $libelle_annee, get_string('heading_with_year', 'local_purgesdatas')).'</h3>';
		echo html_writer::table($table);
		echo '
			 <script type="text/javascript">
			 function confirmation() {
				if (confirm("'.str_replace('[cpt]', $cpt, get_string('msg_delete_all_courses', 'local_purgesdatas')).$libelle_annee.'?'.'")) {
					$("#formsubmit").submit();
				}
				return false;
			 }
			 </script>
			 
		';
		echo '
			<form id="formsubmit" action="purge.php" >
				<input type="hidden" name="annee" value="'.stripslashes($annee) .'">
				<p style="float:right;padding-right:1em;"><input type="button" class="button-action" name="select_mail" value="'.get_string('purger', 'local_purgesdatas').'" onclick="confirmation();"></p>
				<p style="clear:both"></p>
			</form>	';
		
	}
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer(); 