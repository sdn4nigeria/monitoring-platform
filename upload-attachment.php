<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

$backend_root = '../_backend/';
include $backend_root.'spill-data.php';

$bin_root     = $backend_root.'bin/';
$data_root    = '/var/www/data/';
$backup_root  = '/var/www/data/backup/';
$attachments_root = '/var/www/data/attachments/';
$attachments_root_url = 'http'.(isset($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT']!='80'?':'.$_SERVER['SERVER_PORT']:'').'/data/attachments/';

header('Content-type: text/html; charset=UTF-8');
$temp_file_name = '';
$new_file_name = '';
$ok = true;
if (array_key_exists('attachmentFile', $_FILES)) {
  $attachmentFile = $_FILES['attachmentFile'];
  //echo ('<pre>'.
  //      $spillDataFile['name']."\n".
  //      $spillDataFile['type']."\n".
  //      $spillDataFile['size']." bytes\n".
  //      '</pre>');
  if ('text/csv' == $attachmentFile['type'] ||
      '.csv' == strtolower(substr($attachmentFile['name'], -4))) {
    $temp_file_name = $attachmentFile['tmp_name'];
  }
  // TODO: use a unique prefix
  $new_file_name = basename($attachmentFile['name']);
  if (!move_uploaded_file($attachmentFile['tmp_name'],
                          $attachments_root.$new_file_name)) {
    $new_file_name = FALSE;
  }
}
?>
<html><body><?php if ($new_file_name) echo $attachments_root_url.$new_file_name ?></body></html>
