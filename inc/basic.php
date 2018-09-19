<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

#@ini_set("display_errors", 1);
#error_reporting(E_ALL); 
#define("DEBUG_SEVERITY",E_USER_NOTICE); 

require_once(dirname(__FILE__).'/filevcs.class.php');
require_once(dirname(__FILE__).'/settings.class.php');
require_once(dirname(__FILE__).'/authenticator.class.php');

define('USERLEVEL_VIEW', 1);
define('USERLEVEL_EDIT', 2);
define('USERLEVEL_ADMIN', 3);
define('ROOTPATH', BasicHelper::getRootPath());

// Use workaround for clients uploading files in two or three steps
// (delete file, add empty or one-byte file, upload file):
// remove deleted versions or zero/one-byte file versions not older than x seconds
define('WORKAROUND_SECONDS', 120);
define('WORKAROUND_MAXSIZE', 1);

if (!defined('WEBUI')) define('WEBUI', true);

// workaround to remove slashes from all parameters, if magic_quotes is on
if (get_magic_quotes_gpc()) {
  foreach ($_GET as $key => $value) $_GET[$key] = BasicHelper::stripSlashesFromParam($value);
  foreach ($_POST as $key => $value) $_POST[$key] = BasicHelper::stripSlashesFromParam($value);
  foreach ($_REQUEST as $key => $value) $_REQUEST[$key] = BasicHelper::stripSlashesFromParam($value);
}

if (Settings::existSettings()) {
  $settings = Settings::getSettings();
  $authenticator = new Authenticator();
  // if authentication fails, this will show an error page, authentication request:
  $authenticator->authenticate();

  # time zone of all dates/times without zone and the timezone displayed on the web pages
  define('TIMEZONE', $authenticator->getTimezone()); 
  
  # display format for dates on the web pages              
  define('DATE_FORMAT', $settings->get('dateformat', '%Y-%m-%d %H:%M')); 
  
  # change the temporary directory if open_basedir restriction in effect
  define('TMP_DIR', $settings->get('tmpdir', '/tmp')); 
  
  # define a pattern for files that should not be created or uploaded, e.g. '/^~.*/'
  if (@$settings->forbidpattern) define('FORBID_PATTERN', (string) $settings->forbidpattern);
  
  # define a pattern and flags for files that should be physically deleted on request instead of versioned,
  # e.g. '/^~.*|^[A-Z]{8}$/'
  if (@$settings->deletepattern) define('DELETE_PATTERN', (string) $settings->deletepattern);
  if (@$settings->deleteemptyfiles) define('DELETE_EMPTY_FILES', true);
  
  # settings for error reporting and debugging
  if (@$settings->debugging) { 
    @ini_set("display_errors", 1);
    error_reporting(E_ALL); 
    define("DEBUG_SEVERITY",E_USER_NOTICE); 
  }

} else if (!WEBUI) {
  header("HTTP/1.0 500 Server Error");
  exit();
} else if (basename($_SERVER['PHP_SELF']) != 'settings.php') {
  header("Location: settings.php");
  exit();
} else {
  $authenticator = new Authenticator();
}


# Helper class with "private" methods - not for general use
class BasicHelper {

  static function getRootPath() {
    $pos = strrpos(dirname(__FILE__), DIRECTORY_SEPARATOR.'inc');
    return str_replace(DIRECTORY_SEPARATOR, '/', substr(dirname(__FILE__), 0, $pos+1));
  }
  
  static function stripSlashesFromParam($value) {
    if (is_array($value)) {
      $result = array();
      foreach ($value as $v) $result[] = stripslashes($v);
      return $result;
    } else {
      return stripslashes($value);
    }
  }
  
}


function sanitizeDir($dir) {
  return FileVCS::sanitizeDir($dir);
}

function sanitizeName($name) {
  return FileVCS::sanitizeName($name);
}

function getUserName() {
  global $authenticator;
  return @$authenticator->getUserName();
}

function getUserRepository() {
  global $authenticator;
  return @$authenticator->getRepository();
}

function changeUserRepository($repname) {
  global $authenticator;
  return @$authenticator->switchToRepository($repname);
}

function checkUserLevel($level) {
  global $authenticator;
  $userlevel = @$authenticator->getUserLevel();
  if ($userlevel && $userlevel < $level) {
    header('HTTP/1.0 401 Unauthorized');
    echo 'You are not authorized';
    die();
  }
}

function isUserLevel($level) {
  global $authenticator;
  $userlevel = @$authenticator->getUserLevel();
  return $userlevel && $userlevel >= $level;
}

function isReadOnly() {
  global $authenticator;
  $userlevel = @$authenticator->getUserLevel();
  return $userlevel <= USERLEVEL_VIEW;
}

if (!function_exists("timezone_identifiers_list")) { 
  include_once(dirname(__FILE__).'/timezone.inc.php');
}
