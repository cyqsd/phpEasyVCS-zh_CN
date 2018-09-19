<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

require_once(dirname(__FILE__).'/settings.class.php');
require_once(dirname(__FILE__).'/users.class.php');

define('SESSION_USERNAME', 'username');
define('SESSION_USERLEVEL', 'userlevel');
define('SESSION_REPOSITORY', 'repository');
define('SESSION_TIMEZONE', 'timezone');

class Authenticator {

  private $username = null;
  private $userlevel = null;
  private $repository = null;
  private $timezone = null;

  public function authenticate($userlevel = null) {
    $success = false;
    if (defined('WEBUI') && WEBUI) {
      // user already logged in?
      session_start();
      $this->username = @$_SESSION[SESSION_USERNAME];
      $this->userlevel = @$_SESSION[SESSION_USERLEVEL];
      $this->repository = @$_SESSION[SESSION_REPOSITORY];
      $this->timezone = @$_SESSION[SESSION_TIMEZONE];
      $success = $this->username && $this->userlevel;
    }
    if (!$success) {
      $settings = Settings::getSettings();
      $auth = $settings->get('auth', false);
      $realm = $settings->get('realm', 'phpEasyVCS');
      $secret = $settings->get('secret', 'phpeasyvcs');
      if ($auth == 'digest') {
        $success = $this->authenticateDigest($secret);
      } else if ($auth == 'basic') {
        $success = $this->authenticateBasic($realm);
      } else if (function_exists('apache_getenv') && @apache_getenv("REMOTE_USER")) {
        $success = $this->authenticateRemote(apache_getenv("REMOTE_USER"));
      } else if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER']) {
        $success = $this->authenticateRemote($_SERVER['PHP_AUTH_USER']);
      }
      if ($success) {
        if (!$this->timezone) {
          $this->timezone = $settings->get('timezone', 'UTC');
        }
        if (defined('WEBUI') && WEBUI) {
          $_SESSION[SESSION_USERNAME] = $this->username;
          $_SESSION[SESSION_USERLEVEL] = $this->userlevel;
          $_SESSION[SESSION_REPOSITORY] = $this->repository;
          $_SESSION[SESSION_TIMEZONE] = $this->timezone;
        }
      }
    }
    if (!$success || ($userlevel !== null && $userlevel > $this->userlevel)) {
      if ($auth == 'digest') {
        $now = time();
        $nonce = $now."H".md5($now.':'.$secret);
        header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.$nonce.'",opaque="'.md5($realm).'"');
      } else if (!$this->username) {
        header('WWW-Authenticate: Basic realm="'.$realm.'"');
      }
      header('HTTP/1.0 401 Unauthorized');
      echo 'You are not authorized';
      die();
    }
    date_default_timezone_set($this->timezone);
    define('DATAPATH', ROOTPATH.'data'.'/'.($this->repository ? $this->repository : 'default').'/');
  }

  private function authenticateBasic($realm) {
    if (isset($_SERVER['PHP_AUTH_USER'])) {
      $username = $_SERVER['PHP_AUTH_USER'];
      $password = $_SERVER['PHP_AUTH_PW'];
    } else if (isset($_SERVER['HTTP_AUTHORIZATION']) && substr(strtolower($_SERVER['HTTP_AUTHORIZATION']),0,6) == 'basic ') {
      list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'],6)));    
    } else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && substr(strtolower($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),0,6) == 'basic ') {
      # if in .htaccess: RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
      list($username, $password) = explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'],6)));    
    } else if (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (isset($headers['Authorization']) && substr(strtolower($headers['Authorization']),0,6) == 'basic ') {
        list($username, $password) = explode(':', base64_decode(substr($headers['Authorization'],6)));    
      } 
    } 
    $settings = Settings::getSettings();
    $name = $settings->get('admin_name');
    $a1 = $settings->get('admin_a1');
    $success = isset($username) && isset($password) && $this->checkBasic($username, $password, $realm, $name, null, $a1);
    if ($success) {
      $this->username = $name;
      $this->userlevel = USERLEVEL_ADMIN;
      $this->repository = 'default';
      $this->timezone = (string) $settings->timezone;
      return true;
    } else if (isset($username) && isset($password)) {
      $users = Users::getUsers($realm);
      foreach ($users->getAllUsers() as $user) {
        $name = (string) $user->name;
        foreach ($user->repository as $rep) {
          $repname = (string) $rep->name;
          $a1 = (string) $rep->a1;
          $success = $this->checkBasic($username, $password, $realm, $name, $repname, $a1);
          if ($success) {
            $this->username = $name;
            $this->userlevel = (string) $rep->level;
            $this->repository = $repname;
            $this->timezone = (string) $user->timezone;
            return true;
          }
        }
      }
    }
    return false;
  }
  
  private function checkBasic($username, $password, $realm, $name, $rep, $a1) {
    return ($username == $name || $username == $rep.'\\'.$name) && 
           md5($username.':'.$realm.':'.$password) == $a1;
  }
  
  private function authenticateDigest($secret) {
    if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
      $digest = $_SERVER['PHP_AUTH_DIGEST'];
    } else if (isset($_SERVER['HTTP_AUTHORIZATION']) && substr(strtolower($_SERVER['HTTP_AUTHORIZATION']),0,7) == 'digest ') {
      $digest = substr($_SERVER['HTTP_AUTHORIZATION'],7);
    } else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && substr(strtolower($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),0,7) == 'digest ') {
      # if in .htaccess: RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
      $digest = substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'],7);
    } else if (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (isset($headers['Authorization'])) {
        if (substr(strtolower($headers['Authorization']),0,7) == 'digest ') {
          $digest = substr($headers['Authorization'],7);
        }
      } 
    }
    if (!@$digest) return false;
    preg_match_all('@(username|nonce|uri|nc|cnonce|qop|response)=(?:\'([^\']+)\'|"([^"]+)"|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $data[$match[1]] = $match[2] ? $match[2] : ($match[3] ? $match[3] : $match[4]);
    }
    if (count($data) < 7) return false;
    # check uri
    $requestURI = $_SERVER['REQUEST_URI'];
    if (strpos($data['uri'],'?') === false && strpos($requestURI,'?') !== false) {
      $requestURI = substr($requestURI, 0, strpos($requestURI,'?'));
    }
    if ($data['uri'] != $requestURI) return false;
    # check nonce, which is $time."H".md5($time.':'.SECRET)
    if (!preg_match('@^(\d+)H(.*)$@', $data['nonce'], $match)) return false;
    if ((int) $match[1] + 24*3600 < time()) return false;
    if ($match[2] != md5($match[1].':'.$secret)) return false;
    # check response
    $data['username'] = str_replace("\\\\","\\",$data['username']); // workaround?
    $settings = Settings::getSettings();
    $name = $settings->get('admin_name');
    $a1 = $settings->get('admin_a1');
    $a1r = $settings->get('admin_a1r');
    $success = $this->checkDigest($data, $name, null, $a1, $a1r);
    if ($success) {
      $this->username = $name;
      $this->userlevel = USERLEVEL_ADMIN;
      $this->repository = 'default';
      $this->timezone = (string) $settings->timezone;
      return true;
    } else {
      $users = Users::getUsers();
      foreach ($users->getAllUsers() as $user) {
        $name = (string) $user->name;
        foreach ($user->repository as $rep) {
          $repname = (string) $rep->name;
          $a1 = (string) $rep->a1;
          $a1r = (string) $rep->a1r;
          $success = $this->checkDigest($data, $name, $repname, $a1, $a1r);
          if ($success) {
            $this->username = $name;
            $this->userlevel = (string) $rep->level;
            $this->repository = $repname;
            $this->timezone = (string) $user->timezone;
            return true;
          }
        }
      }
    }
    return false;
  }
  
  private function checkDigest($data, $name, $rep, $a1, $a1r) {
    if ($data['username'] == $name) {
      $A1 = $a1;
    } else if ($data['username'] == $rep.'\\'.$name) {
      $A1 = $a1r;
    } else {
      return false;
    }
    $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
    $validResponse = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);
    return ($data['response'] == $validResponse);
  }
  
  private function authenticateRemote($username) {
    if (!$username) return false;
    $settings = Settings::getSettings();
    $name = $settings->get('admin_name');
    if ($username == $name) {
      $this->username = $name;
      $this->userlevel = USERLEVEL_ADMIN;
      $this->repository = 'default';
      $this->timezone = (string) $settings->timezone;
      return true;
    } else {
      $users = Users::getUsers();
      foreach ($users->getAllUsers() as $user) {
        $name = (string) $user->name;
        foreach ($user->repository as $rep) {
          $repname = (string) $rep->name;
          if ($username == $repname.'\\'.$name) {
            $this->username = $name;
            $this->userlevel = (string) $rep->level;
            $this->repository = $repname;
            $this->timezone = (string) $user->timezone;
            return true;
          }
        }
      }
    }
    return false;    
  }
  
  public function switchToRepository($repname) {
    if (!$this->username) return false;
    $users = Users::getUsers();
    $user = $users->getUser($this->username);
    foreach ($user->repository as $rep) {
      if ($repname == (string) $rep->name) {
        $_SESSION[SESSION_REPOSITORY] = $this->repository = $repname;
        $_SESSION[SESSION_USERLEVEL] = $this->userlevel = (string) $rep->level;
        return true;
      }
    } 
    return false;
  }
  
  public function getUserName() {
    return $this->username;
  }
  
  public function getRepository() {
    return $this->repository;
  }
  
  public function getUserLevel() {
    return $this->userlevel;
  }
  
  public function getTimezone() {
    return $this->timezone;
  }

}

