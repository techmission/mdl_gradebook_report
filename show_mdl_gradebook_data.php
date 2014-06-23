<?php

/* Drupal bootstrap - full for use of theme_table. */
chdir('..');
require_once('./includes/bootstrap.inc');
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Run the script to update the Moodle gradebook table.
// Backticks are execution operation in PHP.
$execute = `/usr/bin/php /home/nettm5/public_html/drupal/courseinfo_ac3s/mdl_gradebook_data.php`;

// Switch to the Moodle database.
db_set_active('nettm5_moodle');

$sql = 'select g.studentname, g.course_id, c.shortname, g.pretest,' .
  'g.posttest, g.eval, from_unixtime(g.completiondate) as completiondate,' .
  'from_unixtime(g.cert_completiondate) as cert_completiondate' .
  ' from mdl_grades_denormalized g ' .
  'join mdl_course c on g.course_id = c.idnumber where ' .
  'g.course_id != ""';
$result = db_query($sql);

$output = '<h1>' . t('Moodle grades') . '</h1>';

$header = array(t('Student name'), t('Course id'), t('Course name'),
  t('Pretest grade'), t('Posttest grade'), t('Evaluation'), 
  t('Evaluation completion date'), t('Certificate completion date'), t('Certificate issued'));
$rows = array();
while($course_info = db_fetch_object($result)) {
  $eval_taken = ($course_info->eval == TRUE) ? t('Yes') : t('No');
  $cert_issued = (strpos($course_info->cert_completiondate, '1969') === FALSE) ? t('Yes') : t('No');
  $completiondate = (strpos($course_info->completiondate, '1969') === FALSE) ? $course_info->completiondate : t('Not complete');
  $cert_completiondate = (strpos($course_info->cert_completiondate, '1969') === FALSE) ? $course_info->cert_completiondate : t('Not complete');
  $rows[] = array(
    $course_info->studentname,
    $course_info->course_id,
    $course_info->shortname,
    $course_info->pretest,
    $course_info->posttest,
    $eval_taken,
    $completiondate,
    $cert_completiondate,
    $cert_issued
  );
}
$output .= theme('table', $header, $rows);

echo $output;


