<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

$backend_root = '../_backend/';
require_once $backend_root.'spill-data.php';

$bin_root     = $backend_root.'bin/';
$data_root    = '/var/www/data/';
$backup_root  = '/var/www/data/backup/';

function pquoted($name)
{
  if (array_key_exists($name, $_GET)) {
    return '\''.preg_replace('/([\'])/', '\'"\'"\'', $_GET[$name]).'\'';
  }
  else {
    return '\'\'';
  }
}

$ok = true;
$exit_code = 0;
file_put_contents('/tmp/coordebug', $bin_root.'coordinates.py 2>&1 --lga='.
                  $bin_root.'nigeria-lga-reduced.geojson'.
                  ' --output= --google= --json='.
                  ' --latitude='.pquoted("latitude").
                  ' --longitude='.pquoted("longitude").
                  ' --northings='.pquoted("northings").
                  ' --eastings='.pquoted("eastings"));
$last_line = exec($bin_root.'coordinates.py 2>&1 --lga='.
                  $bin_root.'nigeria-lga-reduced.geojson'.
                  ' --output= --google= --json='.
                  ' --latitude='.pquoted("latitude").
                  ' --longitude='.pquoted("longitude").
                  ' --northings='.pquoted("northings").
                  ' --eastings='.pquoted("eastings"),
                  $command_output,
                  $exit_code);
if ($exit_code != 0) $ok = false;
$output = join($command_output, " ");
if ($ok) {
  header('Content-type: application/json; charset=UTF-8');
  echo $output;
}
else {
  header('Content-type: application/json; charset=UTF-8');
  echo "{'error':'".htmlspecialchars($output)."'}";
}

?>