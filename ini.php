<?php
  /* ini.php
   * a set of functions to read ini files
   *
   *
   * $ini = new ini('filename');
   * - opens a new ini file
   *
   * $ini->list(section);
   * - returns all items in a section
   * 
   * $ini->read(section, item);
   * - reads the selected item
   */
  
  function ini2array($FILE) {
    // check if the file exists
    if (!is_file($FILE)) {
      return Array();
    }
    
    // read the file into memory
    $DATA = file_get_contents($FILE);
    
    // read the data into an array
    foreach (explode("\n", $DATA) as $LINE) {
      // remove any whitespace / return carriage
      $LINE = trim($LINE);

      if ((substr($LINE, 0, 1) == "[") && (substr($LINE, strlen($LINE) - 1) == "]")) {
        // assume this is a [section]
        $SECTION = substr($LINE, 1, -1);
      } elseif (strpos($LINE, '=') != "") {
        // assume this is a item=value
        $INI[$SECTION][substr($LINE, 0, strpos($LINE, '='))] = substr($LINE, strpos($LINE, '=') + 1);
      }
    }
    
    return $INI;
  }
?>