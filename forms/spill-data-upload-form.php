    <form enctype="multipart/form-data" method="post" action="">
      Upload .csv (Comma-Separated Values) or .accdb (MS Access Database) file:<br/>
      <input type="file" name="spillData"/>
      <button type="submit" onsubmit="return !!this['spillData'].value">Upload</button><br/>
      Note: uploading a database file will overwrite the whole database.
      Data backup copies are available at <a href="/data/backup/">/data/backup/</a>
    </form>
