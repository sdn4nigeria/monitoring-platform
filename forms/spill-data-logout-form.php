    <form method="post" action="" class="logout">
      <span class="role <?php echo $_SESSION['user']['role']; ?>"><?php echo $_SESSION['user']['name'] ?></span>
      <button name="logout" type="submit">Close session</button>
    </form>
