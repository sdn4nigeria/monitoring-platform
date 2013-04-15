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

header("Content-type: text/html; charset=UTF-8");
$javascript_logout = 'return loadLoginForm(this)';
if (!login($_POST)) {
  $data = login_request();
  include "forms/incident-report-login-form.php";
?>
          Please sign-in if you have a NOSDRA account.<br/>
          Otherwise your report will be filed as <b>user-submitted</b>
          until it can be verified.
<?php
}
else {
  include "forms/incident-report-logout-form.php";
}
?>
