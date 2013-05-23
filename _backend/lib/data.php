<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

/**
 * Data source in table form.
 * The first row contains the column headers.
 */
class table_data_source 
{
  var $type;
  var $uri;
  var $handle;
  var $error;
  var $headers;
  var $filter;
  
  function error() {
    return $this->error;
  }
  
  function __construct($uri, $headers = FALSE) {
    $this->uri     = $uri;
    $this->handle  = FALSE;
    $this->error   = FALSE;
    $this->headers = $headers;
    $this->filter  = FALSE;
    if (!file_exists($this->uri)) {
      if (!$headers) return;// Only create file if given schema
      $this->handle = fopen($this->uri, "c+");
      if ($this->handle == FALSE) {
        $this->error = "Can not create source '".$uri."'";
      }
      else {
        $this->write(FALSE);
      }
    }
    $this->reopen();
  }
  
  function open($uri) {
    $this->uri = $uri;
    $this->handle = fopen($this->uri, "r");
    if ($this->handle == FALSE) {
      $this->error = "Can not open source '".$uri."'";
    }
    else {
      $this->headers = $this->next_row();
    }
  }
  
  function close() {
    if ($this->handle !== FALSE) fclose($this->handle);
    $this->handle = FALSE;
  }
  
  function reopen() {
    $this->close();
    $this->open($this->uri);
  }
  
  function check_format() {
    $row_index = 0;
    while (($row = $this->next_row()) !== FALSE) {
      if (0 == $row_index) $this->headers = $row;
      $items = count($row);
      if ($items != count($this->headers)) {
        $this->error = "Malformed row #".($row_index+1);
        break;
      }
      ++$row_index;
    }
    if ($row_index < 2 && !$this->error) $this->error = "Empty data table";
  }
  
  function select($pattern)
  {
    if (!$pattern) {
      $this->filter = FALSE;
      return;
    }
    
    $this->filter = array();
    foreach ($pattern as $field_name => $p) {
      if (!$p) $regexp = "^$";
      else {
        $regexp = preg_replace("/([.+*?()\/[\]])/", "\\\\$1", $p);
        $regexp = preg_replace("/^([^%])/", "^$1", $regexp);
        $regexp = preg_replace("/([^%])$/", "$1$", $regexp);
        $regexp = preg_replace("/%$/", "", $regexp);
        $regexp = preg_replace("/%/", ".*", $regexp);
        $regexp = preg_replace("/_/", ".", $regexp);
      }
      $this->filter[$field_name] = "/".$regexp."/i";
    }
  }
  
  function headers()
  {
    return $this->headers;
  }
  
  function get_field($header, $row)
  {
    $fields = count($this->headers);
    for ($i = 0; $i < $fields; ++$i) {
      $field_name = $this->headers[$i];
      if ($field_name == $header) return $row[$i];
    }
    
    return FALSE;
  }
  
  /**
   * Get a key-value map of the fields in the row.
   */
  function get_fields_as_map($row)
  {
    $map = array();
    $fields = count($this->headers);
    for ($i = 0; $i < $fields; ++$i) $map[$this->headers[$i]] = $row[$i];
    
    return $map;
  }
  
  function next_row($handle = FALSE)
  {
    do {
      if ($handle)            $row = fgetcsv(      $handle, 0, ",");
      else if ($this->handle) $row = fgetcsv($this->handle, 0, ",");
      else                    $row = FALSE;
      if ($this->filter && $row) {
        $items = count($this->headers);
        for ($i = 0; $i < $items; ++$i) {
          $field_name = $this->headers[$i];
          if (array_key_exists($field_name, $this->filter) &&
              !preg_match($this->filter[$field_name], $row[$i])) {
            $row = FALSE;
            break;
          }
        }
      }
      else break;
    } while ($row === FALSE);
    
    return $row;
  }
  
  function add_row($new_row, $autoincrement_key = FALSE)
  {
    $write_handle = fopen($this->uri, "r+");
    if ($write_handle == FALSE) {
      $this->error = "Can not write to source '".$this->uri."'";
      return;
    }
    if (flock($write_handle, LOCK_EX)) {
      $autoincrement_key_index = -1;
      if ($autoincrement_key) {
        $this->headers = $this->next_row($write_handle);
        $items = count($this->headers);
        for ($i = 0; $i < $items && $autoincrement_key_index < 0; ++$i) {
          if ($autoincrement_key == $this->headers[$i]) $autoincrement_key_index = $i;
        }
        if ($autoincrement_key_index < 0) {
          $this->error = "add_row(): autoincrement key '".$autoincrement_key."' not found in ".join($this->headers, ",");
          fclose($write_handle);
          return;
        }
        $autoincrement_key_value = 0;
        while (($row = $this->next_row($write_handle)) != FALSE) {
          $value = $row[$autoincrement_key_index];
          if (is_string($value)) {
            preg_match('/^[0-9]+/', $value, $matches);
            if (count($matches) != 1) {
              $this->error = "add_row(): key value does not start with integer '".$value."' in field ".$autoincrement_key;
              fclose($write_handle);
              return;
            }
            $value = intval($matches[0]);
            if ($value > $autoincrement_key_value) $autoincrement_key_value = $value;
          }
        }
        $new_row[$autoincrement_key_index] = $autoincrement_key_value + 1;
      }
      
      fputcsv($write_handle, $new_row);
      // This is automatically done by fclose() below:
      //flock($write_handle, LOCK_UN);
    }
    else {
      $this->error = "Can not lock the file '".$this->uri."'";
    }
    fclose($write_handle);
  }
  
  /**
   * Merge another data source into this one
   *
   * @param source the table_data_source to merge into this one
   * @param primary_key the field that is the primary key
   */
  function merge($source, $primary_key) {
    $this->reopen();
    $source->reopen();
    $this->headers = $this->next_row();
    $source->next_row();// Just consume it. Should be the same as $headers.
    $primary_key_index = -1;
    $items = count($this->headers);
    for ($i = 0; $i < $items && $primary_key_index < 0; ++$i) {
      if ($primary_key == $this->headers[$i]) $primary_key_index = $i;
    }
    if ($primary_key_index < 0) {
      $this->error = "merge(): primary key '".$primary_key."' not found in ".join($this->headers, ",");
      return;
    }
    $rows = array();
    while (($row = $this->next_row()) != FALSE) {
      if (! $this->merge_row($row, $rows, $primary_key)) return;
    }
    while (($row = $source->next_row()) != FALSE) {
      if (! $this->merge_row($row, $rows, $primary_key)) return;
    }
    $this->write($rows);
  }
  
  function merge_row($row, $rows, $primary_key_index) {
    $items = count($row);
    if ($primary_key_index >= $item) {
      $this->error = "merge(): primary key index too large, ".$primary_key_index.">".$items;
      return FALSE;
    }
    $key = $row[$primary_key_index];
    if (!array_key_exists($key, $rows)) {
      $rows[$key] = $row;
    }
    else {
      $other = $rows[$key];
      $are_the_same_so_far = ($items == count($other));
      if ($are_the_same_so_far) {
        for ($i = 0; $i < $items; ++$i) {
          // Here we could do some normalization like collapsing spaces.
          $are_the_same_so_far &= $items[$i] == $other[$i];
        }
      }
      if (!$are_the_same_so_far) {
        $this->error = "merge(): conflicting data at ".$primary_key_index.">".$items."\n".(join($other, ","))."\n".(join($row, ","));
        return FALSE;
      }
    }
    
    return TRUE;
  }
  
  function write($rows) {
    if ($this->headers === FALSE) {
      $this->error = "write(): no headers defined for '".$this->uri."'";
      return;
    }
    $write_handle = fopen($this->uri, "w");
    if ($write_handle == FALSE) {
      $this->error = "Can not write to source '".$this->uri."'";
      return;
    }
    if (flock($write_handle, LOCK_EX)) {
      fputcsv($write_handle, $this->headers);
      if ($rows !== FALSE) foreach ($rows as $key => $row) {
        fputcsv($write_handle, $row);
      }
      flock($write_handle, LOCK_UN);
    }
    else {
      $this->error = "Can not lock the file '".$this->uri."'";
    }
    fclose($write_handle);
  }
  
  function append($rows) {
    $write_handle = fopen($this->uri, "a");
    if ($write_handle == FALSE) {
      $this->error = "Can not write to source '".$this->uri."'";
      return;
    }
    if (flock($write_handle, LOCK_EX)) {
      fputcsv($write_handle, $this->headers);
      foreach ($rows as $key => $row) {
        fputcsv($write_handle, $row);
      }
      flock($write_handle, LOCK_UN);
    }
    else {
      $this->error = "Can not lock the file '".$this->uri."'";
    }
    fclose($write_handle);
  }
}

?>
