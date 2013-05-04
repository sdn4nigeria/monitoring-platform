<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software.

$backend_root = '../_backend/';
require_once $backend_root.'conf.php';
require_once $backend_root.'lib/session.php';
require_once $backend_root.'lib/data.php';
require_once $backend_root.'schemas.php';

if (!login($_POST)) {
  $data = login_request();
  header('Content-type: text/html; charset=UTF-8');
  include 'forms/incident-report-login-form.php';
}
else {
  $user = $_SESSION['user'];
  header('Content-type: text/html; charset=UTF-8');
  include 'forms/incident-report-logout-form.php';
  
  function p($name, $array = FALSE, $default = '')
  {
    if (!$array) $array = $_POST;
    if (array_key_exists($name, $array)) return $array[$name];
    else                                 return $default;
  }
  
  if (array_key_exists('incidentdate', $_POST)) {
    header('Content-type: text/html; charset=UTF-8');
    switch ($user['role']) {
      case 'administrator':
      $dataset = "nosdra";
      $report_status  = p("status", $_POST, "verified");
      break;
      default:
      $dataset = "nosdra-unverified";
      $report_status  = "new";
    }
    $attachments = array();
    $attachmentLinks        = p('attachmentLink',        $_POST, array());
    $attachmentLinkCaptions = p('attachmentLinkCaption', $_POST, array());
    $count = count($attachmentLinks);
    if ($count > count($attachmentLinkCaptions)) $count = count($attachmentLinkCaptions);
    for ($i = 0; $i < $count; ++$i) {
      $label = $attachmentLinkCaptions[$i];
      if (!$label) $label = $attachmentLinks[$i];
      if (!$label) continue;
      echo '<a href="'.htmlspecialchars($attachmentLinks[$i]).'" target="_blank">'.htmlspecialchars($label).'</a><br/>';
      array_push($attachments,
                 array("href"    => $attachmentLinks[$i],
                       "caption" => $attachmentLinkCaptions[$i]));
    }
    $row = array('',
                 p('updatefor'),
                 $report_status,
                 p('incidentdate'),
                 p('company'),
                 p('initialcontainmentmeasures'),
                 p('estimatedquantity'),
                 p('contaminant'),
                 p('cause'),
                 p('latitude'), p('longitude'), p('lga'),
                 p('sitelocationname'), p('estimatedspillarea'),
                 p('spillareahabitat'),
                 json_encode($attachments),
                 p('impact'), p('descriptionofimpact'),
                 p('datejiv'),
                 p('datespillstopped'),
                 p('datecleanup'),
                 p('datecleanupcompleted'),
                 p('methodsofcleanup'),
                 p('dateofpostcleanupinspection'),
                 p('dateofpostimpactassessment'),
                 p('furtherremediation'),
                 p('datecertificate'));
    $spill_data = new table_data_source($data_root.$dataset.".csv",
                                        $schemas['report']['field_names']);
    $spill_data->add_row($row, "spillid");
    if ($spill_data->error()) {
      echo '<div class="error">'.$spill_data->error().'</div>';
    }
    $spill_data->close();
    if (p('updatefor')) echo '<p>Spill report updated</p>';
    else                echo '<p>Spill report added</p>';
  }
  else {
    header('Content-type: text/html; charset=UTF-8');
    include 'forms/incident-report-form.php';
  }
}
?>
