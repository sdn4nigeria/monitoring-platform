User: <?php echo $_SESSION["user"] ?>
<form style="display:inline;float:right" action="javascript:void()" onsubmit="<?php echo $javascript_logout ?>"><input type="hidden" name="logout" value="logout"/><button type="submit">Sign out</button></form>
<script>$('#login-form-container').show()</script>
