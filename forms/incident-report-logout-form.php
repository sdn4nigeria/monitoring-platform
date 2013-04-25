<span class="role <?php echo $_SESSION['user']['role']; ?>"><?php echo $_SESSION['user']['name'] ?></span>
<form style="display:inline;float:right" action="javascript:void()" onsubmit="return loadLoginForm(this)"><input type="hidden" name="logout" value="logout"/><button type="submit">Sign out</button></form>
<br style="clear:both"/>
<script>$('#login-form-container').show()</script>
