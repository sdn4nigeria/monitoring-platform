<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

$backend_root = '../_backend/';
require_once $backend_root.'lib/session.php';
//include $backend_root.'spill-data.php';
$data_file_name = $backend_root.'users.csv';

$bin_root     = $backend_root.'bin/';
$data_root    = '/var/www/data/';
$backup_root  = '/var/www/data/backup/';

if (array_key_exists('format', $_GET)) $format = $_GET['format'];
else                                   $format = 'html';

function echo_json_table($data)
{
  $fieldNames = $data->headers();
  $isFirstRow = TRUE;
  echo '[';
  while (($row = $data->next_row()) !== FALSE) {
    if ($isFirstRow) $isFirstRow = FALSE;
    else echo ',';
    echo '{';
    $items = count($row);
    $isFirstColumn = TRUE;
    for ($i = 0; $i < $items; ++$i) {
      $value = $row[$i];
      if (!$value) continue;
      if ($isFirstColumn) $isFirstColumn = FALSE;
      else echo ',';
      echo '"'.$fieldNames[$i].'":'.json_encode($value);
    }
    echo '}';
  }
  echo ']';
}

if ('json' == $format) {
  ob_start('ob_gzhandler');// Ensure gzip compression regardless of php.ini
  
  $user_data = new table_data_source($data_file_name);
  if (array_key_exists('like', $_GET)) {
    $user_data->select(array($_GET['field'] => $_GET['like']));
  }
  if (array_key_exists('callback', $_GET)) {
    // This is a JSONP request, so wrap it accordingly:
    header('Content-type: text/plain; charset=UTF-8');
    echo $_GET['callback'].'(';
    echo_json_table($user_data);
    echo ');';
  }
  else {
    // Plain JSON
    header('Content-type: application/json; charset=UTF-8');
    echo_json_table($user_data);
  }
  $user_data->close();
}
else {
  header('Content-type: text/html; charset=UTF-8');
  
  function print_table($user_data) {
    $count = 0;
    echo '<table>';
    $row = $user_data->headers();
    echo '<tr>';
    $items = count($row);
    for ($i = 0; $i < $items; ++$i) echo '<th>'.$row[$i].'</th>';
    echo '</tr>';
    while (($row = $user_data->next_row()) !== FALSE) {
      echo '<tr>';
      $items = count($row);
      for ($i = 0; $i < $items; ++$i) echo '<td>'.$row[$i].'</td>';
      ++$count;
      echo '</tr>';
    }
    echo '</table>';
    
    return $count;
  }
  
  function param($name) {
    if (array_key_exists($name, $_GET)) return $_GET[$name];
    else                               return '';
  }
  
  function process_request()
  {
    global $bin_root, $data_root, $backup_root;
    global $data_file_name;
    
    $temp_file_name = '';
    $command_output = array();
    $ok = true;
    $user_data = new table_data_source($data_file_name);
    if (array_key_exists('like', $_GET)) {
      $user_data->select(array($_GET['field'] => $_GET['like']));
    }
    echo '<hr/><form action="" method="GET">';
    echo '<button type="submit">SQL query</button> ';
    echo 'SELECT * WHERE <select name="field">';
    foreach ($user_data->headers() as $header) {
      $selected = ($header === param('field'));
      echo '<option value="'.htmlspecialchars($header).'"';
      if ($selected) echo ' selected="selected"';
      echo '>'.htmlspecialchars($header).'</option>';
    }
    echo '</select>';
    echo ' LIKE "<input name="like" value="'.htmlspecialchars(param("like")).'" autofocus="autofocus"/>"';
    echo '</form>';
    echo '<hr/><div id="filters">';
    echo '<button onclick="filter()">All</button>';
    echo '<input id="field-filter-search" type="search" onchange="search(this.value)"/>';
    echo '<button onclick="search($(\'#field-filter-search\').value)">Search</button>';
    echo '<div id="count-display"><span id="shown-row-count-display"></span> rows shown out of <span id="total-row-count-display"></span> total</div>';
    echo '</div>';
    print_table($user_data);
    $user_data->close();
  }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>User account administration</title>
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
    #count-display { text-align:center; }
    @media print {
    body > * { display: none; }
    body > table { display: table; }
    }
    </style>
  </head>
  <body>
    <?php
    if (!login($_POST)) {
      $data = login_request();
      include 'forms/spill-data-login-form.php';
    }
    else {
      $user = $_SESSION['user'];
      include 'forms/spill-data-logout-form.php';
      
      if ($user['role'] == 'administrator') {
        process_request();
      }
      else {
        echo '<div>This page requires an "administrator" account. You are logged in as "'.$user['role'].'"</div>';
      }
    }
    ?>
    <script src="spill-data.js"></script>
  </body>
</html>
<?php
}
?>