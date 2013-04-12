<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software.

$backend_root = "../_backend/";
include $backend_root."conf.php";
include $backend_root."lib/session.php";
include $backend_root."lib/data.php";

header("Content-type: text/html; charset=UTF-8");
if (!login($_POST)) {
  $data = login_request();
  // TODO: store as unverified
}
else {
  // TODO: store as verified by registered user
}
include "forms/incident-report-form.php";
?>
