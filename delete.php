<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

	$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
	$coursecontext = context_course::instance($course->id);
	require_login();
	$categorycontext = context_coursecat::instance($course->category);
	$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
	$coursefullname = format_string($course->fullname, true, array('context' => $coursecontext));
	$strdeletingcourse = get_string("deletingcourse", "", $courseshortname);
	echo '<h3>'.$strdeletingcourse. '</h3>';
	// We do this here because it spits out feedback as it goes.
	delete_course($course);
    $DB->delete_records("log", array("course" => $id));
	echo '<h4>'.get_string("deletedcourse", "", $courseshortname). '</h4>';
	// Update course count in categories.
	fix_course_sortorder();