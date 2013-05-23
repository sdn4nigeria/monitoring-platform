<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

require_once "conf.php";
require_once "lib/session.php";
require_once "lib/data.php";

$bin_root     = __DIR__."/bin/";
$data_root    = "/var/www/data/";
$backup_root  = "/var/www/data/backup/";

function process_data($input_file_name, &$command_output) {
  global $bin_root, $data_root;
  $ok = true;
  $exit_code = 0;
  $last_line = exec('cp '.$input_file_name.
                    ' '.$data_root.'nosdra-legacy-db.csv && '.
                    $bin_root.'coordinates.py 2>&1 --lga='.
                    $bin_root.'nigeria-lga-reduced.geojson --output='.
                    $data_root.'nosdra.csv --json= '.
                    //$data_root.'nosdra.jsonz --gzip '.
                    $input_file_name,
                    $command_output,
                    $exit_code);
  if ($exit_code != 0 || $last_line != 'OK') $ok = false;
  if ($ok) {
    $spill_data = new table_data_source($data_root.'nosdra.csv');
    $spill_data->check_format();
    if ($spill_data->error()) {
      echo '<div class="error">'.$spill_data->error().'</div>';
      $ok = false;
    }
    $spill_data->close();
    if (!$ok) array_push($command_output,
                         'Wrong format in output CSV');
  }
  
  return $ok;
}

function make_backup(&$command_output) {
  global $data_root, $backup_root;
  $ok = true;
  $exit_code = 0;
  $last_line = exec('cd '.$data_root.'; t=`date --utc +"%Y%m%d-%H%M%S"`'.
                    '; for f in nosdra-legacy-db.csv nosdra.csv; do cp $f '.
                    $backup_root.
                    '${t}_${f}; done && cd '.
                    $backup_root.' && md5sum ${t}_${f} >${t}_${f}.md5 && echo "OK"',
                    $command_output,
                    $exit_code);
  if ($exit_code != 0 || $last_line != 'OK') $ok = false;
  
  return $ok;
}
