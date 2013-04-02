<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

// This makes any errors be displayed on the web page:
error_reporting(E_ALL);
ini_set('display_errors', '1');

include "../_backend/lib/session.php";
include "../_backend/lib/data.php";

$bin_root    = "../../bin/";
$data_root   = "/var/www/data/";
$backup_root = "/var/www/data/backup/";

function process_data($input_file_name, &$command_output) {
  global $bin_root, $data_root;
  $ok = true;
  $exit_code = 0;
  $last_line = exec($bin_root."coordinates.py 2>&1 --lga=".
                    $bin_root."nigeria-lga-reduced.geojson --output=".
                    $data_root."nosdra-full.csv --google= --json=".
                    $data_root."nosdra.jsonz --gzip ".
                    $input_file_name,
                    $command_output,
                    $exit_code);
  if ($exit_code != 0 || $last_line != "OK") $ok = false;
  if ($ok) {
    $spill_data = new table_data_source($data_root."nosdra-full.csv");
    $spill_data->check_format();
    if ($spill_data->error()) {
      echo "<div class='error'>".$spill_data->error()."</div>";
      $ok = false;
    }
    $spill_data->close();
    if (!$ok) array_push($command_output,
                         "Wrong format in output CSV");
  }
  
  return $ok;
}

function make_backup(&$command_output) {
  global $data_root, $backup_root;
  $ok = true;
  $exit_code = 0;
  $last_line = exec('cd '.$data_root.'; t=`date +"%Y%m%dT%H%M%S"`'.
                    '; for f in nosdra.jsonz nosdra-full.csv nosdra.csv; do cp $f '.
                    $backup_root.
                    '${t}_${f}; done; cd '.
                    $backup_root.'; md5sum ${t}_${f} >${t}_${f}.md5; echo "OK"',
                    $command_output,
                    $exit_code);
  if ($exit_code != 0 || $last_line != "OK") $ok = false;
  
  return $ok;
}

function print_table($spill_data) {
  echo "<table>";
  $row_index = 0;
  while (($row = $spill_data->next_row()) !== FALSE) {
    echo "<tr>\n";
    $items = count($row);
    if (0 == $row_index) {
      for ($i = 0; $i < $items; ++$i) echo "<th>".$row[$i]."</th>\n";
    }
    else {
      for ($i = 0; $i < $items; ++$i) echo "<td>".$row[$i]."</td>\n";
    }
    echo "</tr>";
    ++$row_index;
  }
  echo "</table>";
}

?>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Data upload back-end</title>
    <style type="text/css">
    html { background:#EEE; }
    body { font-family:sans-serif; }
    table { border-collapse:collapse; }
    th { font-family:sans-serif; background:#777; color:#FFF; }
    td { background:#FFF; }
    th, td { padding:0.1em 0.2em 0.1em 0.2em; border:thin solid #AAA; }
    .error { padding:1em; border:thick solid red; }
    .login { max-width:20em; margin:25% auto; padding:3em; border:thin solid #888; background:#FFF; }
    .logout { float:right; }
    #rows { width:100%; min-height:20em; }
    </style>
  </head>
  <body>
    <?php
    if (!login($_POST)) {
      $data = login_request();
      include "spill-data-login-form.php";
    }
    else {
      include "spill-data-logout-form.php";
      
      $temp_file_name = "";
      $dataset        = "";
      $command_output = array();
      $ok = true;
      if (array_key_exists("spillData", $_FILES)) {
        $spillDataFile = $_FILES["spillData"];
        echo ("<pre>".
              $spillDataFile["name"]."\n".
              $spillDataFile["type"]."\n".
              $spillDataFile["size"]." bytes\n".
              "</pre>");
        flush();
        $temp_file_name = $spillDataFile["tmp_name"];
      }
      else if (array_key_exists("dataset", $_REQUEST)) {
        $dataset = preg_replace("/[^a-zA-Z0-9_\/-]/", "_",
                                $_REQUEST["dataset"]);
        if (array_key_exists("rows", $_REQUEST)) {
          $temp_file_name = tempnam(sys_get_temp_dir(), "upload");
          file_put_contents($temp_file_name, $_REQUEST["rows"]);
        }
        else if (array_key_exists("edit", $_REQUEST)) {
          echo "<h2>Current data</h2>";
          echo '<form method="post" action="">';
          echo "<textarea id='rows' name='rows'>";
          echo htmlspecialchars(file_get_contents($data_root.$dataset.".csv"));
          echo "</textarea>";
          echo '<button type="submit">Update</button>';
          echo "</form>";
        }
        else {
          $spill_data = new table_data_source($data_root.$dataset.".csv");
          print_table($spill_data);
          $spill_data->close();
        }
      }
      
      if ($temp_file_name) {
        $spill_data = new table_data_source($temp_file_name);
        $spill_data->check_format();
        if ($spill_data->error()) {
          echo "<div class='error'>".$spill_data->error()."</div>";
          $ok = false;
        }
        $spill_data->close();
        if ($ok) {
          array_splice($command_output, 0);// Empty the array
          $exit_code = 0;
          $last_line = exec('cp '.$temp_file_name.
                            ' '.$data_root.'nosdra.csv; echo "OK"',
                            $command_output,
                            $exit_code);
          if ($exit_code != 0 || $last_line != "OK") $ok = false;
          if ($ok) {
            echo "<pre>Data read</pre>";
            unlink($temp_file_name);
          }
          else {
            echo "<pre class='error'>".join($command_output, "\n")."</pre>";
          }
        }
        
        if ($ok) {
          array_splice($command_output, 0);// Empty the array
          $ok = process_data($data_root."nosdra.csv", $command_output);
          if ($ok) {
            echo "<pre>".join($command_output, "\n")."</pre>";
          }
          else {
            echo "<pre class='error'>".join($command_output, "\n")."</pre>";
          }
          flush();
        }
        
        if ($ok) {
          array_splice($command_output, 0);// Empty the array
          $ok = make_backup($command_output);
          if ($ok) {
            echo "<pre>Backups done</pre>";
          }
          else {
            echo "<pre class='error'>".join($command_output, "\n")."</pre>";
          }
        }
        
        if ($ok) {
          echo "<h2>Finished</h2>";
          echo "<p>The data you submitted has been <strong>successfully processed</strong>, and is available in the map view.</p>";
        }
        else {
          echo "<p>Apparently there was an error. Please report the information above to the site administrator.</p>";
        }
      }
      else if (!$dataset) {
        include "spill-data-upload-form.php";
      }
      
      echo "<h2>Data backup copies</h2>";
      echo "Available at <a href='/data/backup/'>/data/backup/</a>";
      echo "<pre>";
      system('ls -t '.$backup_root,
             $exit_code);
      echo "</pre>";
    }
    ?>
  </body>
</html>
