<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

require_once 'data.php';

$users = array();
$users_data = new table_data_source(__DIR__."/../users.csv",
                                    array('name', 'password', 'role'));
while (($row = $users_data->next_row()) !== FALSE) {
  $fields = $users_data->get_fields_as_map($row);
  if ($fields['name']) $users[$fields['name']] = $fields;
}
$users_data->close();

function login($post)
{
  global $users;
  if (!session_start()) error_log('Error: session can not be started');
  if (array_key_exists('logout', $post)) logout($post);
  else if (!array_key_exists('user', $_SESSION)) {
    if (array_key_exists('user', $post)) {
      $user_name = $post['user'];
      if (array_key_exists($user_name, $users)) {
        $user = $users[$user_name];
        if ($post['password'] === $user['password']) {
          $_SESSION['user'] = $user;
        }
      }
    }
  }
  
  return array_key_exists('user', $_SESSION);
}

function logout($post)
{
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', 0, '/');
    session_regenerate_id(true);
    if (array_key_exists('url', $post)) header('Location: '.$post['url']);
}

function login_request()
{
    return array('login'=>
                 array('loginFieldName'   =>'user',
                       'passwordFieldName'=>'password',
                       'loginFieldLabel'   =>'User <em>name</em>',
                       'passwordFieldLabel'=>'Your <em>password</em>',
                       'message'=>'<h2>Log-in</h2>'
                       )
                 );
}

?>
