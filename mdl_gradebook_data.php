<?php

/* Drupal bootstrap - full so use of watchdog. */
chdir('..');
require_once './includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Switch to the Moodle database.
db_set_active('nettm5_moodle');
db_query('DELETE FROM mdl_grades_denormalized');
// Query for courses.
$sql = 'SELECT mdl_course.idnumber AS course_id,
mdl_course.id AS course_idnum,
mdl_user.id as student_id,
mdl_user.firstname,
mdl_user.lastname,
mdl_user.username,
mdl_grade_items.itemname AS gradebookitem,
mdl_grade_grades.rawgrade AS raw_grade,
mdl_grade_grades.finalgrade AS final_grade,
mdl_grade_grades.timecreated AS date_created,
mdl_grade_grades.timemodified AS date_modified,
SUBSTRING(mdl_grade_grades.feedback, 1, 15)
FROM mdl_grade_grades
JOIN mdl_user ON mdl_grade_grades.userid = mdl_user.id
JOIN mdl_grade_items ON mdl_grade_grades.itemid = mdl_grade_items.id
JOIN mdl_course ON mdl_grade_items.courseid = mdl_course.id
ORDER BY mdl_course.idnumber,
 mdl_user.username,
 mdl_grade_items.itemname, mdl_grade_grades.timemodified';
$results = db_query($sql);
// Build the gradebook information array.
$grades = array();
while($row = db_fetch_array($results)) {
  $studentname = $row['firstname'] . ' ' . $row['lastname'];
  $student_id = $row['student_id'];
  $course_id = $row['course_id'];
  if(!isset($grades[$studentname][$course_id])) {
    $grades[$studentname][$course_id]['firstname'] = $row['firstname'];
    $grades[$studentname][$course_id]['lastname'] = $row['lastname'];
    $grades[$studentname][$course_id]['studentname'] = $studentname;
    // TODO: Transform course_id here rather than in spreadsheet?
    $grades[$studentname][$course_id]['course_id'] = $course_id;
    // Default eval to FALSE until it is found for a student
    $grades[$studentname][$course_id]['eval'] = FALSE;
  }
  if(strpos($row['gradebookitem'], 'Course Evaluation') !== FALSE) {
    if($row['final_grade'] != 0) {
      $grades[$studentname][$course_id]['eval'] = TRUE;
      $grades[$studentname][$course_id]['completiondate'] = $row['date_modified'];
    }
    $sql = 'select mdl_certificate_issues.timecreated from mdl_certificate_issues 
    join mdl_certificate on mdl_certificate.id = mdl_certificate_issues.certificateid 
    where mdl_certificate_issues.userid = %d and mdl_certificate.course = %d';
    $grades[$studentname][$course_id]['cert_completiondate'] = db_result(db_query($sql, $student_id, $row['course_idnum']));
  }
  /* if($row['eval_complete'] == 'y') {
    $grades[$studentname][$course_id]['eval'] = TRUE;
  } */
  if(strpos($row['gradebookitem'], 'Pre Test') !== FALSE) {
    $grades[$studentname][$course_id]['pretest'] = $row['final_grade'];
  }  
  if(strpos($row['gradebookitem'], 'Post Test') !== FALSE) {
    $grades[$studentname][$course_id]['posttest'] = $row['final_grade'];
  }
}
// Write the gradebook information array to the database.
if(count($grades) > 0) {
  foreach($grades as $studentname => $courses) {
    foreach($courses as $course_id => $record) {
      db_query('INSERT INTO mdl_grades_denormalized (studentname, firstname, lastname, course_id, pretest, posttest, eval, completiondate, cert_completiondate) VALUES ("%s", "%s", "%s", "%s", %d, %d, %b, %d, %d)', 
        array($record['studentname'], $record['firstname'], $record['lastname'], $record['course_id'], $record['pretest'], $record['posttest'], $record['eval'], $record['completiondate'], $record['cert_completiondate']));
    }
  }
}
// Log a successful send to watchdog.
if(isset($count) && $count > 0) {
  watchdog('mdl_grades_denormalized', 'Inserted %count grades.', array('%count' => $count), WATCHDOG_NOTICE);
}
