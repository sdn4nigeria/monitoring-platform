    <form enctype="multipart/form-data" method="post" action="">
      Select CSV (Comma-Separated Values) or Access Database (MS Access .accdb) file:<br/>
      <input type="file" name="spillData"/>
      <button type="submit" onsubmit="return !!this['spillData'].value">Upload</button><br/>
      Data backup copies are available at <a href="/data/backup/">/data/backup/</a>
    </form>
