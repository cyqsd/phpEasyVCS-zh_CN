<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

define('LOCK_MAX_TIMEOUT_SECONDS', 24*3600); # one day

class Lock {
  
  # all locks are "exclusive" "write" locks
  private $token;
  private $path;
  private $recursive; # for directories: inclusive content or just the directory properties
  private $timeout;   # UNIX timestamp
  private $owner;
  
  public function __construct($token, $path, $recursive, $timeout, $owner) {
    $this->token = $token;
    $this->path = $path;
    $this->recursive = $recursive;
    $this->timeout = $timeout;
    $this->owner = $owner;
  }
  
  public function __get($name) {
    switch ($name) {
      case 'token': return $this->token;
      case 'path': return $this->path;
      case 'recursive': return (bool) $this->recursive;
      case 'timeout': return $this->timeout;
      case 'owner': return $this->owner; 
    }
  }
  
}


class LockSupport {
  
  private $fp;
  private $locks;
  private $changed = false;
  
  public function __construct($lockfile) {
    if (!file_exists($lockfile)) {
      $fp = fopen($lockfile, "w");
      fclose($fp);
    }
    $now = time();
    $this->fp = fopen($lockfile, "r+");
    if (!flock($this->fp, LOCK_EX)) throw new Exception(); # should never happen, but rather block
    while (($line = fgets($this->fp)) !== false) {
      if (preg_match('/^([^ ]+) "([^"]*)" (0|1) (\d+) (.*)$/', $line, $match)) {
        if ($now <= (int) $match[4]) {
          $this->locks[$match[1]] = new Lock($match[1], $match[2], $match[3]=='1', (int) $match[4], trim($match[5]));
        } else {
          $this->changed = true;
        }
      }
    }
  }
  
  public function __destruct() {
    if ($this->changed) {
      fseek($this->fp, 0);
      ftruncate($this->fp, 0);
      if ($this->locks) foreach ($this->locks as $lock) {
        $recursive = $lock->recursive ? '1' : '0';
        fputs($this->fp, "$lock->token \"$lock->path\" $recursive $lock->timeout $lock->owner\n");
      }
    }
    flock($this->fp, LOCK_UN);
    fclose($this->fp);
  }

  public function getLock($path) {
    if ($this->locks) foreach ($this->locks as $lock) {
      if (($lock->recursive && substr($path,0,strlen($lock->path)+1) == $lock->path.'/') || (!$lock->recursive && $lock->path == $path)) {
        return $lock;
      }
    }
    return null;
  }
  
  public function addLock($token, $path, $recursive, $timeout, $owner) {
    $lock = $this->getLock($path);
    if (!$lock) {
      $now = time();
      if ($timeout < $now) return false;
      if (!$token) $token = $this->newToken;
      if ($timeout - $now > LOCK_MAX_TIMEOUT_SECONDS) $timeout = $now + LOCK_MAX_TIMEOUT_SECONDS;
      $this->locks[$token] = new Lock($token, $path, $recursive, $timeout, $owner);
      $this->changed = true;
      return true;
    } else if ($lock->token == $token) {
      return $this->updateLock($token, $lock->path, $timeout);
    } else {
      return false;
    }
  }
  
  public function updateLock($token, $path, $timeout) {
    $lock = $this->locks[$token];
    if (!$lock) return false;
    if ($path != $lock->path && (!$lock->recursive || substr($path,0,strlen($lock->path)+1) != $lock->path.'/')) return false;
    $now = time();
    if ($timeout < $now) return false;
    if ($timeout - $now > LOCK_MAX_TIMEOUT_SECONDS) $timeout = $now + LOCK_MAX_TIMEOUT_SECONDS;
    $this->locks[$token] = new Lock($lock->token, $lock->path, $lock->recursive, $timeout, $lock->owner);
    $this->changed = true;
    return true;
  }
  
  public function removeLock($token, $path) {
    if (!isset($this->locks[$token])) return false;
    $lock = $this->locks[$token];
    if (!$lock || $lock->path != $path) return false;
    unset($this->locks[$token]);
    $this->changed = true;
    return true;
  }
  
  private function newToken() {
    // use uuid extension from PECL if available
    if (function_exists("uuid_create")) {
        return uuid_create();
    }

    // fallback
    $uuid = md5(microtime().getmypid());    // this should be random enough for now

    // set variant and version fields for 'true' random uuid
    $uuid{12} = "4";
    $n = 8 + (ord($uuid{16}) & 3);
    $hex = "0123456789abcdef";
    $uuid{16} = $hex{$n};

    // return formated uuid
    return "opaquelocktoken:".substr($uuid,0,8)."-". substr($uuid,8,4)."-".
           substr($uuid,12,4)."-".substr($uuid,16,4)."-".substr($uuid,20);
  }
  
}
