<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

class Settings {

  private static $settings = null;

  private $root;
  
  static function getSettings() {
    if (!self::$settings) {
      self::$settings = new Settings();
    }
    return self::$settings;
  }
  
  static function existSettings() {
    return file_exists(ROOTPATH.'data/settings.xml');
  }
  
  private function __construct() {
    if (file_exists(ROOTPATH.'data/settings.xml')) {
      $this->root = simplexml_load_file(ROOTPATH.'data/settings.xml', 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
      if (isset($this->root->admin)) {
        # convert file from old format
        $this->__set('admin_name', (string) $this->root->admin->name);
        $this->__set('admin_a1', (string) $this->root->admin->a1);
        $this->__set('admin_a1r', (string) $this->root->admin->a1r);
        unset($this->root->admin);
      }
    } else {
      $this->root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><settings></settings>');
    }
  }
  
  function __get($name) {
    if (isset($this->root->$name)) return (string) $this->root->$name; else return null;
  }
  
  function __set($name, $value=null) {
    if (isset($this->root->$name)) unset($this->root->$name);
    if ($value !== null) $this->root->addChild($name, htmlspecialchars($value));
  }
  
  function get($name, $default=null) {
    $value = $this->__get($name);
    return $value === null ? $default : $value;
  }
  
  function set($name, $value) {
    $this->__set($name, $value);
  }
  
  function save() {
    return $this->root->asXML(ROOTPATH.'data/settings.xml');
  }
  
}
