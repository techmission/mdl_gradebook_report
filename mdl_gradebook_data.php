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
mdl_user.firstname,
mdl_user.lastname,
mdl_user.username,
mdl_grade_items.itemname AS gradebookitem,
mdl_grade_grades.rawgrade AS raw_grade,
mdl_grade_grades.finalgrade AS final_grade,
mdl_grade_grades.timecreated AS date_created,
mdl_grade_grades.timemodified AS date_modified,
SUBSTRING(mdl_grade_grades.feedback, 1, 15),
mdl_questionnaire_response.complete as eval_complete
FROM mdl_grade_grades
JOIN mdl_user ON mdl_grade_grades.userid = mdl_user.id
JOIN mdl_grade_items ON mdl_grade_grades.itemid = mdl_grade_items.id
JOIN mdl_course ON mdl_grade_items.courseid = mdl_course.id
JOIN mdl_questionnaire ON mdl_questionnaire.course = mdl_course.id AND mdl_questionnaire.name = "Course Evaluation"
JOIN mdl_questionnaire_attempts ON mdl_questionnaire_attempts.qid = mdl_questionnaire.id AND mdl_questionnaire_attempts.userid = mdl_user.id
JOIN mdl_questionnaire_response ON mdl_questionnaire_response.id = mdl_questionnaire_attempts.rid
ORDER BY mdl_course.idnumber,
 mdl_user.username,
 mdl_grade_items.itemname, mdl_grade_grades.timemodified';
$results = db_query($sql);
// Build the gradebook information array.
$grades = array();
while($row = db_fetch_array($results)) {
  $studentname = $row['firstname'] . ' ' . $row['lastname'];
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
      // $grades[$studentname][$course_id]['eval'] = TRUE;
      $grades[$studentname][$course_id]['completiondate'] = $row['date_modified'];
    }
  }
  if($row['eval_complete'] == 'y') {
    $grades[$studentname][$course_id]['eval'] = TRUE;
  }
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
      db_query('INSERT INTO mdl_grades_denormalized (studentname, firstname, lastname, course_id, pretest, posttest, eval, completiondate) VALUES ("%s", "%s", "%s", "%s", %d, %d, %b, %d)', array($record['studentname'], $record['firstname'], $record['lastname'], $record['course_id'], $record['pretest'], $record['posttest'], $record['eval'], $record['completiondate']));
    }
  }
}
// Log a successful send to watchdog.
if(isset($count) && $count > 0) {
  watchdog('mdl_grades_denormalized', 'Inserted %count grades.', array('%count' => $count), WATCHDOG_NOTICE);
}
