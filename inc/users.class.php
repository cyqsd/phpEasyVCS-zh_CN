<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

class Users {

  private static $users = null;

  private $root;
  private $realm;
  
  public static function getUsers($realm=null) {
    if (!self::$users) {
      self::$users = new Users($realm);
    }
    return self::$users;
  }
  
  private function __construct($realm=null) {
    if (file_exists(ROOTPATH.'data/users.xml')) {
      $this->root = simplexml_load_file(ROOTPATH.'data/users.xml', 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
    } else {
      $this->root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><users></users>');
    }
    $this->realm = $realm;
  }
  
  public function getUser($name) {
    $matches = $this->root->xpath('user[name=\''.$name.'\']');
    return $matches && count($matches) > 0 ? $matches[0] : null;
  }
  
  public function getAllUsers() {
    $users = array();
    if ($this->root->user) {
      foreach ($this->root->user as $user) $users[] = $user;
    }
    return $users;
  }
  
  public function setUser($name, $timezone, $password=null, $repositoryLevels=null) {
    $matches = $this->root->xpath('user[name=\''.$name.'\']');
    if ($matches && count($matches) > 0) {
      $user = $matches[0];
    } else {
      $user = $this->root->addChild('user');
      $user->addChild('name', htmlspecialchars($name));
    }
    $user->timezone = $timezone;
    if ($repositoryLevels) {
      // add, remove, modify repositories
      foreach ($user->repository as $rep) {
        $repname = (string) $rep->name;
        if (!isset($repositoryLevels[$repname]) || !$repositoryLevels[$repname]) {
          unset($rep);
        } else {
          $rep->level = htmlspecialchars($repositoryLevels[$repname]);
        }
        unset($repositoryLevels[$repname]);
      }
      foreach ($repositoryLevels as $repname => $replevel) {
        $rep = $user->addChild('repository');
        $rep->addChild('name', htmlspecialchars($repname));
        $rep->addChild('level', htmlspecialchars($replevel));
      }
    }
    if ($password && $this->realm) {
      foreach ($user->repository as $rep) {
        $repname = (string) $rep->name;
        $rep->a1 = md5($name.':'.$this->realm.':'.$password);  
        $rep->a1r = md5($repname.'\\'.$name.':'.$this->realm.':'.$password);  
      }
    }
  } 
  
  public function checkUserPassword($name, $password) {
    if (!$this->realm) return false;
    $matches = $this->root->xpath('user[name=\''.$name.'\']');
    if ($matches && count($matches) > 0) {
      $user = $matches[0];
      $rep = $user->repository[0];
      return $rep->a1 == md5($name.':'.$this->realm.':'.$password);
    }
  }
  
  public function setUserPassword($name, $password) {
    if (!$this->realm) return;
    $matches = $this->root->xpath('user[name=\''.$name.'\']');
    if ($matches && count($matches) > 0) {
      $user = $matches[0];
      foreach ($user->repository as $rep) {
        $repname = (string) $rep->name;
        $rep->a1 = md5($name.':'.$this->realm.':'.$password);  
        $rep->a1r = md5($repname.'\\'.$name.':'.$this->realm.':'.$password);  
      }
    }
  }
  
  public function setUserTimezone($name, $timezone) {
    $matches = $this->root->xpath('user[name=\''.$name.'\']');
    if ($matches && count($matches) > 0) {
      $user = $matches[0];
      $user->timezone = htmlspecialchars($timezone);
    }
  }
  
  public function deleteUser($name) {
    $users = $this->root->user;
    for ($i=count($users)-1; $i>=0; $i--) {
      if ($name == (string) $users[$i]->name) unset($users[$i]);
    }
  }
  
  public function save() {
    return $this->root->asXML(ROOTPATH.'data/users.xml') === true;
  }
  
}
