<?php
// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

class table_data_source 
{
  var $type;
  var $uri;
  var $handle;
  var $error;
  
  function error() {
    return $this->error;
  }
  
  function __construct($uri) {
    $this->open($uri);
  }
  
  function open($uri) {
    $this->uri = $uri;
    $this->handle = fopen($this->uri, "r");
    if ($this->handle == FALSE) {
      $this->error = "Can not open source '".$uri."'";
    }
  }
  
  function reopen() {
    $this->fclose();
    $this->fopen($this->uri, "r");
  }
  
  function check_format() {
    $row_index = 0;
    while (($row = $this->next_row()) !== FALSE) {
      if (0 == $row_index) $header = $row;
      $items = count($row);
      if ($items != count($header)) {
        $this->error = "Malformed row #".($row_index+1);
        break;
      }
      ++$row_index;
    }
    if ($row_index < 2 && !$this->error) $this->error = "Empty data table";
  }
  
  function next_row() {
    return fgetcsv($this->handle, 0, ",");
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
    $header = $this->next_row();
    $source->next_row();// Just consume it. Should be the same as $header.
    $primary_key_index = -1;
    $items = count($header);
    for ($i = 0; $i < $items && $primary_key_index < 0; ++$i) {
      if ($primary_key == $header[$i]) $primary_key_index = $i;
    }
    if (primary_key_index < 0) {
      $this->error = "merge(): primary key '".$primary_key."' not found in ".join($header, ",");
      return;
    }
    $rows = array();
    while (($row = $this->next_row()) != FALSE) {
      if (! $this->merge_row($row, $rows)) return;
    }
    while (($row = $source->next_row()) != FALSE) {
      if (! $this->merge_row($row, $rows)) return;
    }
    
    $write_handle = fopen($this->uri, "w");
    if ($write_handle == FALSE) {
      $this->error = "Can not write to source '".$this->uri."'";
      return;
    }
    fputcsv($write_handle, $header);
    foreach ($rows as $key => $row) {
      fputcsv($write_handle, $row);
    }
    fclose($write_handle);
  }
  
  function merge_row($row, $rows) {
    $items = count($row);
    if (primary_key_index >= $item) {
      $this->error = "merge(): primary key index too large, ".$primary_key_index.">".$items;
      return FALSE;
    }
    $key = $row[primary_key_index];
    if (!array_key_exists($key, $rows)) {
      $rows[$key] = $row;
    }
    else {
      $other = $rows[$key];
      $are_the_same_so_far = ($items == count($other));
      if ($are_the_same_so_far) {
        for ($i = 0; $i < $items; ++$i) {
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
  
  function close() {
    if ($this->handle !== FALSE) fclose($this->handle);
    $this->handle = FALSE;
  }
}

?>
