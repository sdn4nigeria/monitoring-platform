<form method="POST" action="javascript:void()" onsubmit="return loadIncidentReportForm(this)" class="login">
          Please sign-in to report or update the information about a spill.<br/>
  <input type="text" placeholder="user" name="<?php echo $data["login"]["loginFieldName"] ?>"/><br/>
  <input type="password" placeholder="password" name="<?php echo $data["login"]["passwordFieldName"] ?>"/><br/>
  <button type="submit">Sign in</button>
  <button style="float:right" onclick="toggleIncidentReportForm(); return false">Cancel</button>
  <br style="clear:both"/>
</form>
