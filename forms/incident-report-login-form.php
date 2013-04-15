<form method="POST" action="javascript:void()" onsubmit="<?php echo $javascript_logout ?>" class="login">
  <input type="text" placeholder="user" name="<?php echo $data["login"]["loginFieldName"] ?>"/><br/>
  <input type="password" placeholder="password" name="<?php echo $data["login"]["passwordFieldName"] ?>"/>
  <button type="submit">Sign in</button><br/>
</form>
