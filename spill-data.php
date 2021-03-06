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

if (array_key_exists('dataset', $_REQUEST)) {
  $dataset = preg_replace('/[^a-zA-Z0-9_\/-]/', '_',
                          $_REQUEST['dataset']);
}
else {
  $dataset = FALSE;
}
if (array_key_exists('format', $_GET)) $format = $_GET['format'];
else                                   $format = 'html';

function echo_json_table($data)
{
  $fieldNames = $data->headers();
  $entries = array();
  while (($row = $data->next_row()) !== FALSE) {
    $entry = array();
    $items = count($row);
    for ($i = 0; $i < $items; ++$i) {
      $value = $row[$i];
      if (!$value) continue;// Omit empty values
      $entry[$fieldNames[$i]] = $value;
    }
    if ($entry) {
      if (array_key_exists('updatefor', $entry)) {
        // Deliver only the latest version
        $entry['spillid'] = $entry['updatefor'];
        unset($entry['updatefor']);
      }
      $entries[$entry['spillid']]   = $entry;
    }
  }
  
  $isFirstRow = TRUE;
  echo '[';
  foreach ($entries as $entry) {
    if ($isFirstRow) $isFirstRow = FALSE;
    else echo ',';
    echo json_encode($entry);
  }
  echo ']';
}

if ('json' == $format) {
  ob_start('ob_gzhandler');// Ensure gzip compression regardless of php.ini
  
  $spill_data = new table_data_source($data_root.$dataset.'.csv');
  if (array_key_exists('like', $_GET)) {
    $spill_data->select(array($_GET['field'] => $_GET['like']));
  }
  if (array_key_exists('callback', $_GET)) {
    // This is a JSONP request, so wrap it accordingly:
    header('Content-type: text/plain; charset=UTF-8');
    echo $_GET['callback'].'(';
    echo_json_table($spill_data);
    echo ');';
  }
  else {
    // Plain JSON
    header('Content-type: application/json; charset=UTF-8');
    echo_json_table($spill_data);
  }
  $spill_data->close();
}
else {
  header('Content-type: text/html; charset=UTF-8');
  
  function print_table($spill_data) {
    global $dataset;
    
    $count = 0;
    echo '<table>';
    $field_names = $spill_data->headers();
    echo '<tr>';
    $items = count($field_names);
    for ($i = 0; $i < $items; ++$i) {
      echo '<th onclick="sort('.$i.')">'.$field_names[$i].'</th>';
    }
    echo '</tr>';
    while (($row = $spill_data->next_row()) !== FALSE) {
      echo '<tr>';
      $items = count($row);
      for ($i = 0; $i < $items; ++$i) {
        echo '<td><a href="spill-data.php?field='.htmlspecialchars($field_names[$i]).'&like='.htmlspecialchars($row[$i]).'&dataset='.htmlspecialchars($dataset).'">'.$row[$i].'</a></td>';
      }
      ++$count;
      echo '</tr>';
    }
    echo '</table>';
    
    return $count;
  }
  
  function param($name)
  {
    if (array_key_exists($name, $_GET)) return $_GET[$name];
    else                               return '';
  }
  
  function data_explorer()
  {
    global $data_root, $dataset, $filters;
    
    $spill_data = new table_data_source($data_root.$dataset.'.csv');
    if (array_key_exists('like', $_GET)) {
      $spill_data->select(array($_GET['field'] => $_GET['like']));
    }
    echo '<hr/><form action="" method="GET">';
    echo '<button type="submit">SQL query</button> ';
    echo 'SELECT * FROM '.htmlspecialchars($dataset).' WHERE <select name="field">';
    foreach ($spill_data->headers() as $header) {
      $selected = ($header === param('field'));
      echo '<option value="'.htmlspecialchars($header).'"';
      if ($selected) echo ' selected="selected"';
      echo '>'.htmlspecialchars($header).'</option>';
    }
    echo '</select>';
    echo ' LIKE "<input name="like" value="'.htmlspecialchars(param("like")).'" autofocus="autofocus"/>"';
    echo '<br/><input type="hidden" name="dataset" value="'
    .htmlspecialchars($dataset)
    .'"/></form>';
    echo '<div style="text-align:right"><a href="javascript:void(0)" onclick="this.style.display=\'none\';$(\'#sql-syntax-help\').style.display=\'\'">Show brief syntax help</a></div>';
    echo '<p style="font-family:serif;background:#fff;padding:0.5em;border:thin solid #ccc;display:none" id="sql-syntax-help">The pattern "<span style="font-family:sans-serif">%</span>" matches any characters, any length included zero. For instance, <span style="font-family:sans-serif">SELECT * FROM '.htmlspecialchars($dataset).' WHERE LOCATION LIKE "%barge%"</span> selects all barge incidents. To see all incidents in 2007, select the field <span style="font-family:sans-serif">DATE OF INCIDENT</span> with the pattern "<span style="font-family:sans-serif">2007-%</span>".<br/>The pattern "<span style="font-family:sans-serif">_</span>" matches any character, but only exactly one. For example, <span style="font-family:sans-serif">SELECT * FROM '.htmlspecialchars($dataset).' WHERE LOCATION LIKE "%oso r_%"</span> selects all incidents that mention "<span style="font-family:sans-serif">Oso RK</span>", "<span style="font-family:sans-serif">Oso RG</span>", "<span style="font-family:sans-serif">Oso RP</span>", etc.</p>';
    echo '<hr/><div id="filters">Filter:';
    echo '<button onclick="filter()">All</button>';
    if ('nosdra-legacy-db' == $dataset) {
      echo '<button id="button-filter-no-incident-number" onclick="filter(filters.no_incident_number)">Entries without incident number</button>';
      echo '<button id="button-filter-no-location" onclick="filter(filters.no_location)">Entries with neither lat/lon nor northings/eastings information</button>';
      echo '<button id="button-filter-misplaced-location" onclick="filter(filters.misplaced_location)">Entries with misplaced lat/lon information</button>';
    }
    echo '<input id="field-filter-search" type="search" onchange="search(this.value)"/>';
    echo '<button onclick="search($(\'#field-filter-search\').value)">Search</button>';
    echo '<div id="count-display"><span id="shown-row-count-display"></span> rows shown out of <span id="total-row-count-display"></span> total</div>';
    echo '</div>';
    print_table($spill_data);
    $spill_data->close();
  }
  
  function process_request()
  {
    global $bin_root, $data_root, $backup_root, $dataset;
    
    $temp_file_name = '';
    $command_output = array();
    $ok = true;
    if (array_key_exists('spillData', $_FILES)) {
      $spillDataFile = $_FILES['spillData'];
      echo ('<pre>'.
            $spillDataFile['name']."\n".
            $spillDataFile['type']."\n".
            $spillDataFile['size']." bytes\n".
            '</pre>');
      flush();
      if ('text/csv' == $spillDataFile['type'] ||
          '.csv' == strtolower(substr($spillDataFile['name'], -4))) {
        $temp_file_name = $spillDataFile['tmp_name'];
      }
      else {
        $temp_file_name = tempnam(sys_get_temp_dir(), 'upload');
        $exit_code = 0;
        $last_line = exec('echo "Importing MS Access data..."; java -jar '.$bin_root.'AccessToCSV.jar '.
                          $spillDataFile['tmp_name'].' '.
                          $temp_file_name.
                          ' 2>&1 && cp '.$temp_file_name.' /tmp/imported-access-data.csv && echo "OK"',
                          $command_output,
                          $exit_code);
        if ($exit_code != 0 || $last_line != 'OK') $ok = false;
        if ($ok) {
          echo '<pre>Microsoft Access data file imported</pre>';
        }
        else {
          echo '<pre class="error">'.join($command_output, "\n").'</pre>';
        }
      }
    }
    else if ($dataset) {
      include 'forms/spill-data-upload-form.php';
      if (array_key_exists('rows', $_REQUEST)) {
        $temp_file_name = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($temp_file_name, $_REQUEST['rows']);
      }
      else if (array_key_exists('edit', $_REQUEST)) {
        echo '<h2>Current data</h2>';
        echo '<form method="post" action="">';
        echo '<textarea id="rows" name="rows">';
        echo htmlspecialchars(file_get_contents($data_root.$dataset.'.csv'));
        echo '</textarea>';
        echo '<button type="submit">Update</button>';
        echo '</form>';
      }
      else {
        data_explorer();
      }
    }
    
    if ($temp_file_name) {
      if ($ok) {
        $spill_data = new table_data_source($temp_file_name);
        $spill_data->check_format();
        if ($spill_data->error()) {
          echo '<div class="error">'.$spill_data->error().'</div>';
          $ok = false;
        }
        $spill_data->close();
      }
      
      if ($ok) {
        array_splice($command_output, 0);// Empty the array
        $ok = process_data($temp_file_name, $command_output);
        if ($ok) {
          echo '<pre>'.join($command_output, "\n").'</pre>';
        }
        else {
          echo '<pre class="error">'.join($command_output, "\n").'</pre>';
        }
        flush();
      }
      
      if ($ok) {
        array_splice($command_output, 0);// Empty the array
        $ok = make_backup($command_output);
        if ($ok) {
          echo '<pre>Backups done</pre>';
        }
        else {
          echo '<pre class="error">'.join($command_output, "\n").'</pre>';
        }
      }
      
      if ($ok) {
        echo '<h2>Finished</h2>';
        echo '<p>The data you submitted has been <strong>successfully processed</strong>, and is available in the map view.</p>';
        echo '<p>Click to <a href="?dataset=nosdra">view the current data</a\
>.</p>';
      }
      else {
        echo '<p>Apparently there was an error. Please report the information above to the site administrator.</p>';
      }
      
      // Either $temp_file_name is the one created by PHP to put the
      // uploaded file, or is the one we created to put the uploaded CSV
      // data or to convert ACCDB into CSV data.
      // In any case we don't need it any more, and there is no harm
      // in unlinking it twice if it was created by PHP.
      unlink($temp_file_name);
    }
    else if (!$dataset) {
      include 'forms/spill-data-upload-form.php';
    }
  }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta charset="utf-8" />
    <title>Data Explorer - Nigeria Oil Spill Monitor</title>
    <meta name='viewport' content='width=980'>
    <link rel="stylesheet" href="/monitoring-platform-test/all.css" type="text/css" />
    <link rel='stylesheet' href='/monitoring-platform-test/ipad.css' type='text/css' media='only screen and (device-width: 768px)' />
    <link rel='shortcut icon' href='/monitoring-platform-test/img/favicon.ico' type='image/x-icon' />
    <style type="text/css">
    table { border-collapse:collapse; }
    th, td { padding:0.1em 0.2em 0.1em 0.2em; border:thin solid #CCC; }
    th { cursor:pointer; }
    .error { padding:1em; border:thick solid red; }
    .login { max-width:20em; margin:25% auto; padding:3em; border:thin solid #888; background:#FFF; }
    .logout { display:block; text-align:right; }
    input { max-width:none !important; }
    #rows { width:100%; min-height:20em; }
    #count-display { text-align:center; }
    @media print {
    body > * { display: none; }
    body > table { display: table; }
    }
    </style>
  </head>
  <body>
    <div id="masthead">
      <div class="limiter-wide">
        <div class="title">
          <h2><a href="/monitoring-platform-test/">Data Explorer - Nigeria Oil Spill Monitor</a></h2>
        </div>
        <div id="nav">
          <a class='main-nav active' href="spill-data.php?dataset=nosdra">Spill data</a>
          <a class='main-nav active' href="spill-data.php?dataset=nosdra-unverified">Unverified reports</a>
          <a class='main-nav active' href="spill-data.php?dataset=nosdra-legacy-db">Legacy database</a>
          <a class='main-nav active' href="users.php">User accounts</a>
        </div>
      </div>
    </div>
    <?php
    if (!login($_POST)) {
      $data = login_request();
      include 'forms/spill-data-login-form.php';
    }
    else {
      $user = $_SESSION['user'];
      include 'forms/spill-data-logout-form.php';
      
      switch ($user['role']) {
        case 'administrator':
        process_request();
        break;
        case 'officer':
        case 'company':
        data_explorer();
        break;
        default:
        echo '<div>This page requires an "administrator", "officer", or "company" account. You are logged in as "'.$user['role'].'"</div>';
      }
    }
    ?>
    <script src="spill-data.js"></script>
  </body>
</html>
<?php
}
?>