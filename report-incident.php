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
$javascript_logout = 'return loadIncidentReportForm(this)';
if (!login($_POST)) {
  $data = login_request();
  include "incident-report-login-form.html";
}
else {
  include "incident-report-logout-form.html";
}
?>
        <hr/>
        <div style="text-align:right"><input id="copy-data-from-map" name="copy-data-from-map" type="checkbox"/><label for="copy-data-from-map">Copy data from map</label></div>
<?php
include "incident-report-form.html";
?>
