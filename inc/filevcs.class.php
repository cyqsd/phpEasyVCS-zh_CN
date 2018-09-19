<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

define('VCS_SUCCESS', 0);
define('VCS_NOACTION', -1);
define('VCS_READONLY', -2);
define('VCS_NOTFOUND', -3);
define('VCS_LOCKED', -4);
define('VCS_FORBIDDEN', -5);
define('VCS_EXISTS', -6);
define('VCS_CONFLICT', -7);
define('VCS_PARTIAL', -8);
define('VCS_ERROR', -9);

define('FILEVCS_CONTENT', 'C');
define('FILEVCS_DIR', 'D');
define('FILEVCS_FILE', 'F');
define('FILEVCS_NORMAL', 'N');
define('FILEVCS_DELETED', 'X');

# FileVCS only processes one add/update/delete request at a time:
define('FILEVCS_LOCKFILE', '.lock');

# ADDITIONAL SETTINGS to overcome WebDAV client problems:
# -------------------------------------------------------
# The following constants may be defined to tweek the behaviour of FileVCS:
#   define a pattern for files that should not be created or uploaded:
#     FORBID_PATTERN: regex string, e.g. '/^~.*/'
#   define a pattern for files that should be physically deleted on request instead of versioned,
#     DELETE_PATTERN: regex string, e.g. '/^~.*|^[A-Z]{8}$/'
#   physically delete empty files with only 1 version, if set to true:
#     DELETE_EMPTY_FILES: boolean, e.g. true
#   physically delete files not older than WORKAROUND_SECONDS seconds and not bigger 
#   than WORKAROUND_MAXSIZE bytes, when a delete is requested:
#   (for clients first creating an empty or one-byte file before rewriting it with content
#   to avoid an empty file as first version, even if the file was only uploaded once)
#     WORKAROUND_SECONDS: int, e.g. 120
#     WORKAROUND_MAXSIZE: int, e.g. 1


require_once(dirname(__FILE__).'/filetype.class.php');

class FileVCS {
  
  private $lock = null;
  private $tags;
  private $base;
  private $tag;
  private $user;
  private $readonly;
  
  public function __construct($base, $tagOrDate=null, $user=null, $readonly = false) {
    if (substr($base,-1) != '/') $base .= '/';
    $this->base = $base;
    $this->loadTags();
    $this->setTag(@trim($tagOrDate));
    $this->user = $user;
    $this->readonly = $readonly;
  }
  
  public function __destruct() {
    if ($this->lock) {
      flock($this->lock, LOCK_UN);
      fclose($this->lock);
    }
  }

  private function lock() {
    $this->lock = fopen($this->base.FILEVCS_LOCKFILE,'w');
    if (!flock($this->lock, LOCK_EX)) throw new Exception();  # should never happen
  }
  
  private function forbidden($name) {
  	if (defined('FORBID_PATTERN') && preg_match(FORBID_PATTERN, $name)) return true;
  	return false;
  }
  
  private function canPhysicallyDelete($entry) {
    if ($entry->type != FILEVCS_FILE) return false;
    if (defined('DELETE_PATTERN') && preg_match(DELETE_PATTERN, $entry->name)) return true;
    if (defined('DELETE_EMPTY_FILES') && DELETE_EMPTY_FILES && $entry->size <= 0 && $entry->version == 1) return true;
    return false;
  }
  
  public function isTag() {
    return (bool) $this->tag;
  }
  
  public function isReadOnly() {
    return (bool) $this->readonly;
  }
  
  public function setTag($tagOrDate) {
    if ($tagOrDate && $tagOrDate != 'current') {
      $this->tag = @$this->tags[$tagOrDate] ? $this->tags[$tagOrDate] : new VCSTag($tagOrDate);
    } else {
      $this->tag = null;
    }
  }
  
  public function getTag($name=null) {
    return $name ? @$this->tags[$name] : $this->tag;
  }
  
  public function getTags() {
    return $this->tags;
  }
  
  public function getRoot() {
    return new FileVCSRoot($this->base);
  }
  
  public function getEntry($dir, $name, $version=null, $includeDeleted=false) {
    $dir = self::sanitizeDir($dir);
    $name = self::sanitizeName($name);
    if (!$version) {
      $entry = $this->entry($dir, $name);
      return $entry && ($includeDeleted || !$entry->deleted) ? $entry : null;
    } else if (is_dir($this->base.$dir.$name)) {
      $directory = new FileVCSDirectory($this->base, $dir, self::filename($name, $version, FILEVCS_DIR, FILEVCS_NORMAL));
      return $directory->exists() && ($includeDeleted || !$directory->deleted) ? $directory : null;
    } else {
      $file = new FileVCSFile($this->base, $dir, self::filename($name, $version, FILEVCS_FILE, FILEVCS_NORMAL));
      return $file->exists() && ($includeDeleted || !$file->deleted) ? $file : null;
    }
  }
  
  public function getListing($dir, $includeDeleted=false, $withHistory=false) {
    $dir = self::sanitizeDir($dir);
    # get files and directories (up to tag date, if tag is given)
    return new FileVCSListing($this->base, $dir, $this->listing($dir, $includeDeleted, $withHistory));
  } 
  
  public function getHistory($dir, $name) {
    $dir = self::sanitizeDir($dir);
    $name = self::sanitizeName($name);
    $entries = $this->entries($dir, $name);
    return $entries;
  }
  
  private function checkDir($dir, $create=false, $comment='') {
    if (substr($dir,-1) == '/') $dir = substr($dir,0,strlen($dir)-1);
    if (!$dir) return true;
    if (($pos = strrpos($dir,'/')) !== false) {
      $parent = substr($dir,0,$pos+1);
      $name = substr($dir,$pos+1);
    } else {
      $parent = '';
      $name = $dir;
    }
    $entry = $this->entry($parent, $name, true);
    if ($entry && $entry->type != FILEVCS_DIR) return false;
    if (!$create && (!$entry || $entry->deleted)) return false;
    // create directory if necessary:
    if (!$entry) {
      if (!$this->checkDir($parent, $create, $comment)) return false;
      if (!@mkdir($this->base.$parent.$name)) return false;
    }
    if (!$entry || $entry->deleted) {
      $newversion = $entry ? $entry->version + 1 : 1;
      $f = fopen($this->base.$parent.$this->filename($name,$newversion,FILEVCS_DIR,FILEVCS_NORMAL), "w");
      if ($this->user) fputs($f, "user ".$this->user."\n");
      if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
      fclose($f);
    }
    return true;
  }
    
  public function addDirectory($dir, $name, $comment='', $autoCreateDir=true) {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = self::sanitizeDir($dir);
    $name = self::sanitizeName($name);
    if ($this->forbidden($name)) return VCS_FORBIDDEN;
    if (!$this->checkDir($dir, $autoCreateDir, $comment)) return VCS_CONFLICT;
    $entry = $this->entry($dir, $name);
    if ($entry && $entry->type != FILEVCS_DIR) return VCS_FORBIDDEN;
    if ($entry && !$entry->deleted) return VCS_NOACTION;
    # create directory if necessary
    if (!$entry && !@mkdir($this->base.$dir.$name)) return VCS_ERROR;
    # create property file
    $newversion = $entry ? $entry->version + 1 : 1;
    $f = @fopen($this->base.$dir.$this->filename($name,$newversion,FILEVCS_DIR,FILEVCS_NORMAL), "w");
    if ($f) {
      if ($this->user) fputs($f, "user ".$this->user."\n");
      if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
      fclose($f);
      return $newversion;
    } else {
      return VCS_ERROR;
    }
  }
  
  public function addFile($dir, $name, $tmpfile, $comment='', $autoCreateDir=true) {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = $this->sanitizeDir($dir);
    $name = $this->sanitizeName($name);
    if ($this->forbidden($name)) return VCS_FORBIDDEN;
    if (!$this->checkDir($dir, $autoCreateDir, $comment)) return VCS_CONFLICT; 
    $entries = $this->entries($dir, $name);
    if (!$entries) $entry = null; else foreach ($entries as $entry) break;
    if ($entry && $entry->type != FILEVCS_FILE) return VCS_FORBIDDEN;
    if ($entry && defined('WORKAROUND_SECONDS') && defined('WORKAROUND_MAXSIZE')) {
      if (($entry->deleted || $entry->size <= WORKAROUND_MAXSIZE) && time() <= $entry->date + WORKAROUND_SECONDS) {
        if ($entry->deleted) {
          // delete property file
          unlink($this->base.$dir.$this->filename($name,$entry->version,FILEVCS_FILE,FILEVCS_DELETED));
        } else {
          if ($entry->_content == $dir.$this->filename($name,$entry->version,FILEVCS_CONTENT)) {
            // delete content file, if not a lower version file is used
            unlink($this->base.$entry->_content);
          }
          // delete property file
          unlink($this->base.$dir.$this->filename($name,$entry->version,FILEVCS_FILE,FILEVCS_NORMAL));
        }
        // update entries
        array_shift($entries);
        if (!$entries) $entry = null; else foreach ($entries as $entry) break;
      }
    }
    $md5 = md5_file($tmpfile);
    $sha1 = sha1_file($tmpfile);
    if ($entry && $entry->size == filesize($tmpfile) && $entry->md5 == $md5 && $entry->sha1 == $sha1) {
      # no changes, ignore upload
      unlink($tmpfile);
      return VCS_NOACTION;
    } else {
      $newversion = $entry ? $entry->version + 1 : 1;
      # is the content the same as an old version?
      $content = $this->content($entries, filesize($tmpfile), $md5, $sha1);
      if (!$content) {
        # no, new content:
        $success = copy($tmpfile, $this->base.$dir.$this->filename($name,$newversion,FILEVCS_CONTENT));
        unlink($tmpfile);
        if (!$success) return VCS_ERROR;
      }
      # create property file
      $f = fopen($this->base.$dir.$this->filename($name,$newversion,FILEVCS_FILE,FILEVCS_NORMAL), "w");
      if ($f) {
        if ($this->user) fputs($f, "user ".$this->user."\n");
        if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
        fputs($f, "md5 $md5\n");
        fputs($f, "sha1 $sha1\n");
        if ($content) fputs($f, "content $content");
        fclose($f);
        return $newversion;
      } else {
        return VCS_ERROR;
      }
    }
  }
  
  public function revertFile($dir, $name, $version, $comment='') {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = $this->sanitizeDir($dir);
    $name = $this->sanitizeName($name);
    if (!$this->checkDir($dir, true, $comment)) return VCS_FORBIDDEN;
    $revertentry = new FileVCSFile($this->base, $dir, $this->filename($name, $version, FILEVCS_FILE, FILEVCS_NORMAL));
    if (!$revertentry->exists()) return VCS_NOTFOUND;
    $entry = $this->entry($dir, $name);
    if ($revertentry->version == $entry->version || $revertentry->_content == $entry->_content) {
      # no changes, ignore
      return VCS_NOACTION;
    } else {
      # create property file
      $newversion = $entry->version + 1;
      $f = fopen($this->base.$dir.$this->filename($name,$newversion,FILEVCS_FILE,FILEVCS_NORMAL), "w");
      if ($this->user) fputs($f, "user ".$this->user."\n");
      if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
      fputs($f, "md5 $revertentry->md5\n");
      fputs($f, "sha1 $revertentry->sha1\n");
      fputs($f, "content $revertentry->_content\n");
      fclose($f);
      return $newversion;
    }
  }
  
  public function undeleteDirectory($dir, $name, $comment='') {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = $this->sanitizeDir($dir);
    $name = $this->sanitizeName($name);
    $entry = $this->entry($dir, $name);
    if (!$entry) return VCS_NOTFOUND;
    if ($entry->type != FILEVCS_DIR) return VCS_FORBIDDEN;
    if (!$entry->deleted) return VCS_NOACTION;
    if (!$this->checkDir($dir, true, $comment)) return VCS_FORBIDDEN;
    $this->revertToDate($dir.$name.'/', $entry->date-1, $comment);
    return $entry->version + 1;
  }
  
  public function revertToDate($dir, $date=null, $comment='') {
    if ($this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = $this->sanitizeDir($dir);
    if (!$date && $this->tag) $date = $this->tag->date;
    if (!$date) return VCS_FORBIDDEN;
    if ($date >= time()) return VCS_NOACTION;
    if (!$this->checkDir($dir, true, $comment)) return VCS_FORBIDDEN;
    $this->revertEntriesToDate($dir, $date, $comment);
    return VCS_SUCCESS;
  }
  
  private function revertEntriesToDate($dir, $date=null, $comment='') {
    // get current entries in directory including deleted files/directories
    $entries = $this->listingByDate($dir, null, true, false);
    // get all non-deleted entries at the given date
    $revertentries = $this->listingByDate($dir, $date, false, false);
    $revertmap = array();
    foreach ($revertentries as $revertentry) $revertmap[$revertentry->name] = $revertentry;
    foreach ($entries as $entry) {
      $revertentry = @$revertmap[$entry->name];
      if ($revertentry && !$revertentry->deleted) {
        if ($entry->type == FILEVCS_DIR) {
          if ($entry->deleted) {
            # undelete by creating appropriate property file
            $newversion = $entry->version + 1;
            $f = fopen($this->base.$dir.$this->filename($entry->name,$newversion,FILEVCS_DIR,FILEVCS_NORMAL), "w");
            if ($this->user) fputs($f, "user ".$this->user."\n");
            if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
            fclose($f);
          }
          $this->revertEntriesToDate($dir.$entry->name.'/', $date, $comment);
        } else if ($entry->deleted || $entry->_content != $revertentry->_content) {
          # create property file
          $newversion = $entry->version + 1;
          $f = fopen($this->base.$dir.$this->filename($entry->name,$newversion,FILEVCS_FILE,FILEVCS_NORMAL), "w");
          if ($this->user) fputs($f, "user ".$this->user."\n");
          if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
          fputs($f, "md5 $revertentry->md5\n");
          fputs($f, "sha1 $revertentry->sha1\n");
          fputs($f, "content $revertentry->_content\n");
          fclose($f);
        }
      } else {
        if (!$entry->deleted) {
          $this->deleteEntry($entry, $comment);
        }
      }
    }
  }
  
  public function copy($sourcedir, $sourcename, $targetdir, $targetname, $overwrite=false, $comment='') {
    if ($this->isReadOnly()) return VCS_READONLY;
    # copying from a tag to current is allowed, thus no check for readonly
    $this->lock();
    $sourcedir = $this->sanitizeDir($sourcedir);
    $sourcename = $this->sanitizeName($sourcename);
    $targetdir = $this->sanitizeDir($targetdir);
    $targetname = $this->sanitizeName($targetname);
    $srcentry = $this->entry($sourcedir, $sourcename);
    if (!$srcentry || $srcentry->deleted) return VCS_NOTFOUND;
    if (!$this->checkDir($targetdir, false)) return VCS_CONFLICT;
    if ($this->forbidden($targetname)) return VCS_FORBIDDEN;
    # get target entry ignoring current tag
    $tgtentry = $this->entry($targetdir, $targetname, true); 
    if ($tgtentry && $tgtentry->type != $srcentry->type) return VCS_FORBIDDEN;
    if ($tgtentry && !$tgtentry->deleted && !$overwrite) return VCS_EXISTS;
    $newversion = $tgtentry ? $tgtentry->version + 1 : 1;
    $content = $srcentry->_content;
    if ($srcentry->type == FILEVCS_DIR) {
      # create directory if necessary
      if (!$tgtentry && !mkdir($this->base.$targetdir.$targetname)) return VCS_ERROR;
    } else if ($this->canPhysicallyDelete($srcentry)) {
      # copy content file, as original file might be deleted later on
      copy($this->base.$content, $this->base.$targetdir.$this->filename($targetname,$newversion,FILEVCS_CONTENT));
      $content = null;
    }
    # create property file
    $f = fopen($this->base.$targetdir.$this->filename($targetname,$newversion,$srcentry->type,FILEVCS_NORMAL), "w");
    if ($this->user) fputs($f, "user ".$this->user."\n");
    if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
    fputs($f, "copyof $sourcedir$sourcename $srcentry->version\n");
    if ($srcentry->type == FILEVCS_FILE) {
      fputs($f, "md5 $srcentry->md5\n");
      fputs($f, "sha1 $srcentry->sha1\n");
      if ($content) fputs($f, "content $content\n");
    }
    fclose($f);
    if ($srcentry->type == FILEVCS_DIR) {
      # recursively copy directory contents
      $entries = $this->listing($sourcedir.$sourcename.'/');
      if (count($entries)) foreach ($entries as $e) {
        # TODO: handle errors
        $this->copy($e->dir, $e->name, $targetdir.$targetname.'/', $e->name, $overwrite, $comment);
      }
    }
    return $newversion;
  }
  
  public function move($sourcedir, $sourcename, $targetdir, $targetname, $overwrite=false, $comment='') {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $sourcedir = $this->sanitizeDir($sourcedir);
    $sourcename = $this->sanitizeName($sourcename);
    $targetdir = $this->sanitizeDir($targetdir);
    $targetname = $this->sanitizeName($targetname);
    $srcentry = $this->entry($sourcedir, $sourcename);
    if (!$srcentry || $srcentry->deleted) return VCS_NOTFOUND;
    if (!$this->checkDir($targetdir, false)) return VCS_FORBIDDEN;
    if ($this->forbidden($targetname)) return VCS_FORBIDDEN;
    # get target entry ignoring current tag
    $tgtentry = $this->entry($targetdir, $targetname, true);
    if ($tgtentry && $tgtentry->type != $srcentry->type) return VCS_FORBIDDEN;
    if ($tgtentry && !$tgtentry->deleted && !$overwrite) return VCS_EXISTS;
    if ($srcentry->type == FILEVCS_DIR && $srcentry->isEmpty() && $srcentry->version == 1) {
      # move directory - workaround for webdav clients, which create a directory with a dummy name and then rename it
      if (!$tgtentry) {
        rename($this->base.$sourcedir.$sourcename, $this->base.$targetdir.$targetname);
      } else {
        rmdir($this->base.$sourcedir.$sourcename);
      }
      # move property file
      $newversion = $tgtentry ? $tgtentry->version + 1 : 1; 
      rename($this->base.$sourcedir.$this->filename($srcentry->name, $srcentry->version, FILEVCS_DIR, FILEVCS_NORMAL), 
             $this->base.$targetdir.$this->filename($targetname, $newversion, FILEVCS_DIR, FILEVCS_NORMAL));
      return $newversion;
    }
    $newversion = $tgtentry ? $tgtentry->version + 1 : 1;
    $content = $srcentry->_content;
    if ($srcentry->type == FILEVCS_DIR) {
      # create directory if necessary
      if (!$tgtentry && !mkdir($this->base.$targetdir.$targetname)) return VCS_ERROR;
    } else if ($this->canPhysicallyDelete($srcentry)) {
      # move content file - workaround for webdav clients, which create a file with a dummy name and then rename it
      rename($this->base.$content, $this->base.$targetdir.$this->filename($targetname,$newversion,FILEVCS_CONTENT));
      $content = null;
      # delete all versions and property files of the source
      $len = strlen($sourcename);
      $dh = opendir($this->base.$sourcedir);
      $pat = '/^\.\d\d\d\d\d('.FILEVCS_CONTENT.'|'.FILEVCS_FILE.FILEVCS_NORMAL.'|'.FILEVCS_FILE.FILEVCS_DELETED.')$/';
      while (($filename = readdir($dh)) !== false) {
        if (substr($filename,0,$len) == $sourcename && preg_match($pat, substr($filename,$len))) { 
          unlink($this->base.$sourcedir.$filename);
        }
      }
      closedir($dh);
    }
    # create property file for target (copy)
    $f = fopen($this->base.$targetdir.$this->filename($targetname,$newversion,$srcentry->type,FILEVCS_NORMAL), "w");
    if ($this->user) fputs($f, "user ".$this->user."\n");
    if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
    fputs($f, "movedfrom $sourcedir$sourcename $srcentry->version\n");
    if ($srcentry->type == FILEVCS_FILE) {
      fputs($f, "md5 $srcentry->md5\n");
      fputs($f, "sha1 $srcentry->sha1\n");
      if ($content) fputs($f, "content $content\n");
    }
    fclose($f);
    if ($srcentry->type == FILEVCS_DIR) {
      # recursively move directory entries
      $entries = $this->listing($sourcedir.$sourcename.'/');
      foreach ($entries as $e) {
        # TODO: handle errors
        $this->move($e->dir, $e->name, $targetdir.$targetname.'/', $e->name, $comment);
      }
    }
    # create property file for source (deleted), if source is not physically deleted
    if ($content || $srcentry->type == FILEVCS_DIR) {
      $delversion = $srcentry->version + 1;
      $f = fopen($this->base.$sourcedir.$this->filename($sourcename,$delversion,$srcentry->type,FILEVCS_DELETED), "w");
      if ($this->user) fputs($f, "user ".$this->user."\n");
      if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
      fputs($f, "movedto $targetdir$targetname $newversion\n");
      fclose($f);
    }
    return $newversion;
  }
  
  public function delete($dir, $name, $comment='') {
    if ($this->isTag() || $this->isReadOnly()) return VCS_READONLY;
    $this->lock();
    $dir = $this->sanitizeDir($dir);
    $name = $this->sanitizeName($name);
    $entry = $this->entry($dir, $name);
    return $this->deleteEntry($entry, $comment);
  }
  
  private function deleteEntry($entry, $comment='') {
    if (!$entry) {
      # nothing to delete
      return VCS_NOTFOUND;      
    } else if ($entry->deleted) {
      # no changes, ignore
      return VCS_NOACTION;
    } else if ($this->canPhysicallyDelete($entry)) {
      # delete all versions and property files
      $len = strlen($entry->name);
      $dh = opendir($this->base.$entry->dir);
      $pat = '/^\.\d\d\d\d\d('.FILEVCS_CONTENT.'|'.FILEVCS_FILE.FILEVCS_NORMAL.'|'.FILEVCS_FILE.FILEVCS_DELETED.')$/';
      while (($filename = readdir($dh)) !== false) {
        if (substr($filename,0,$len) == $entry->name && preg_match($pat, substr($filename,$len))) { 
          unlink($this->base.$entry->dir.$filename);
        }
      }
      closedir($dh);
      return VCS_SUCCESS;
    } else if ($entry->type == FILEVCS_DIR && $entry->version == 1 && $entry->isEmpty()) { 
      # just delete directory and property file - workaround because of some webdav clients
      if (!rmdir($this->base.$entry->dir.$entry->name)) return VCS_ERROR;
      if ($entry->movedfrom) {
        # we should remove the movedto in the delete property file
        # but we can't as this would change its date.
        # FileVCSDirectory checks for existence of the movedto anyway, so it's no problem.
      }
      unlink($this->base.$entry->dir.$this->filename($entry->name,$entry->version,FILEVCS_DIR,FILEVCS_NORMAL));
      return VCS_SUCCESS;
    } else {
      if ($entry->type == FILEVCS_DIR) {
        $entries = $this->listing($entry->dir.$entry->name);
      }
      # create property file
      $version = $entry->version + 1;
      $f = fopen($this->base.$entry->dir.$this->filename($entry->name,$version,$entry->type,FILEVCS_DELETED), "w");
      if ($this->user) fputs($f, "user ".$this->user."\n");
      if ($comment) fputs($f, "comment ".preg_replace('/\r?\n/','\n',$comment)."\n");
      fclose($f);
      if ($entry->type == FILEVCS_DIR) {
        # delete directory entries
        foreach ($entries as $e) {
          # TODO: handle errors
          $this->deleteEntry($e, $comment);
        }
      }
      return $version;
    }
  }
  
  public function addTag($name, $date=null) {
    if ($this->isReadOnly()) return VCS_READONLY;
    if (@$this->tags[$name]) return VCS_EXISTS;
    if (!$date) {
      $this->tags[$name] = new VCSTag($name, time());
    } else if (is_numeric($date)) {
      $this->tags[$name] = new VCSTag($name, (int) $date);
    } else {
      $this->tags[$name] = new VCSTag($name, VCSTag::parseDate($date));
    }
    $this->saveTags();
    return VCS_SUCCESS;
  }
  
  public function deleteTag($name) {
    if ($this->isReadOnly()) return VCS_READONLY;
    if (!isset($this->tags[$name])) return VCS_NOTFOUND;
    unset($this->tags[$name]);
    $this->saveTags();
    return VCS_SUCCESS;
  }
  
  private function entry($dir, $name, $ignoreTag=false) {
    $entry = null;
    $dh = @opendir($this->base.$dir);
    if ($dh) {
      while (($filename = readdir($dh)) !== false) {
        if (!is_dir($this->base.$dir.$filename)) {
          switch ($this->type($filename)) {
            case FILEVCS_FILE: $aentry = new FileVCSFile($this->base, $dir, $filename); break;
            case FILEVCS_DIR: $aentry = new FileVCSDirectory($this->base, $dir, $filename); break;
            default: $aentry = null;
          }
          if (!$aentry || $aentry->name != $name) continue;
          if (!$entry || $entry->version < $aentry->version) {
            if ($entry && $entry->type != $aentry->type) throw new Exception(); # should never happen
            if ($ignoreTag || !$this->tag || $aentry->date < $this->tag->date) {
              $entry = $aentry;
            }
          }
        }
      }
      closedir($dh);
    }
    return $entry;
  }
  
  private function entries($dir, $name, $date=null) {
    $type = null;
    $entries = array();
    $dh = @opendir($this->base.$dir);
    if ($dh) {
      while (($filename = readdir($dh)) !== false) {
        if (!is_dir($this->base.$dir.$filename)) {
          switch ($this->type($filename)) {
            case FILEVCS_FILE: $aentry = new FileVCSFile($this->base, $dir, $filename); break;
            case FILEVCS_DIR: $aentry = new FileVCSDirectory($this->base, $dir, $filename); break;
            default: $aentry = null;
          }
          if (!$aentry || $aentry->name != $name) continue;
          if ($type && $type != $aentry->type) throw new Exception(); # should never happen
          if (!$type) $type = $aentry->type;
          if ($date && $date < $aentry->date) continue;
          if (!$this->tag || $aentry->date < $this->tag->date) $entries[$aentry->version] = $aentry;
        }
      }
      closedir($dh);
      ksort($entries);
      $entries = array_reverse($entries, true);
    }
    return $entries;
  }
  
  private function listing($dir, $includeDeleted=false, $withHistory=false) {
    return $this->listingByDate($dir, $this->tag ? $this->tag->date : null, $includeDeleted, $withHistory);
  }

  private function listingByDate($dir, $date, $includeDeleted=false, $withHistory=false) {
    $entries = array();
    $history = array();
    $dh = @opendir($this->base.$dir);
    if ($dh) {
      while (($filename = readdir($dh)) !== false) {
        $type = self::type($filename);
        switch ($type) {
          case FILEVCS_FILE: $aentry = new FileVCSFile($this->base, $dir, $filename); break;
          case FILEVCS_DIR: $aentry = new FileVCSDirectory($this->base, $dir, $filename); break;
          default: $aentry = null;
        }
        if (!$aentry) continue;
        $centry = @$entries[$aentry->name];
        if (!$date || $aentry->date <= $date) {
          if ($withHistory && $aentry->isFile) {
            $history[$aentry->name][$aentry->version] = $aentry;
          }
          if (!$centry || $aentry->version > $centry->version) {
            $entries[$aentry->name] = $aentry;
          }
        }
      }
      closedir($dh);
      # add versions, if necessary
      if ($withHistory) {
        foreach ($history as $name => $versions) {
          $entries[$name]->setVersions($versions);
        }
      }
      # remove deleted files and directories
      if (!$includeDeleted) {
        foreach (array_keys($entries) as $name) {
          if ($entries[$name]->deleted) unset($entries[$name]);
        }
      }
    }
    return $entries;
  }
  
  private function content($entries, $size, $md5, $sha1) {
    foreach ($entries as $entry) {
      if (!$entry->deleted && $entry->md5 == $md5 && $entry->sha1 = $sha1) {
        if ($entry->size == $size) return $entry->_content;
      }
    }
    return null;
  }
  
  private static function filename($name, $version, $type, $status='') {
    // return the filename for the property or content file
    return $name.'.'.sprintf('%05d',$version).$type.$status;
  }
  
  private static function type($filename) {
    // check if file is a property file and return its type
    $len = strlen($filename);
    if ($len < 9 || $filename[$len-8] != '.') return null;
    if ($filename[$len-1] != FILEVCS_NORMAL && $filename[$len-1] != FILEVCS_DELETED) return null;
    if (!is_numeric(substr($filename,$len-7,5))) return null;
    return $filename[$len-2];
  }
  
    
  private function loadTags() {
    $this->tags = array();
    $lines = @file($this->base.'tags.txt');
    if (@$lines) foreach ($lines as $line) {
      $parts = preg_split('/ /', trim($line), 2);
      if (preg_match('/^\d{10}$/', $parts[1])) {
        // old format
      	$this->tags[$parts[0]] = new VCSTag($parts[0], (int) $parts[1]);
      } else {
      	$this->tags[$parts[1]] = new VCSTag($parts[1], (int) $parts[0]);
      }
    }
    asort($this->tags);
    $this->tags = array_reverse($this->tags, true);
  }  
  
  private function saveTags() {
    $f = fopen($this->base.'tags.txt', "w");
    foreach ($this->tags as $tag) {
      fputs($f, $tag->date.' '.$tag->name."\n");
    }
    fclose($f);
  }
  
  static public function sanitizeDir($dir=null) {
    if (!$dir) return '';
    $dir = preg_replace('@\.+[/\\\\]@', '', $dir);
    $dir = str_replace(DIRECTORY_SEPARATOR, '/', $dir);
    if (substr($dir,-1) != '/') $dir .= '/';
    if ($dir == '/') $dir = '';
    return $dir;
  }
  
  static public function sanitizeName($name) {
    return preg_replace('@/@', '', $name); 
  }

}

class VCSTag {
  
  private $name;
  private $date;
  
  public function __construct($nameOrDate, $date=null) {
    if ($date) {
      $this->name = $nameOrDate;
      $this->date = $date;
    } else {
      $this->name = $nameOrDate;
      $this->date = self::parseDate($nameOrDate);
    }
  }
  
  public function __get($name) {
    switch($name) {
      case 'name': return $this->name;
      case 'date': return $this->date;
    }
    return null;
  }
  
  static public function parseDate($date) {
    $formats = array('%Y-%m-%d %H:%M:%S', '%Y-%m-%dT%H:%M:%S', '%Y-%m-%d %H:%M', '%Y-%m-%dT%H:%M', '%Y-%m-%d');
    foreach ($formats as $format) {
      if (function_exists('strptime')) {
        $t = strptime($date, $format);
      } else { # Windows!
        $t = self::strptime($date, $format);
      }
      if ($t !== false && !@$t['unparsed']) {
        return mktime($t['tm_hour'],$t['tm_min'],$t['tm_sec'],$t['tm_mon']+1,$t['tm_mday'],$t['tm_year']+1900);
      }
    }
    return time();
  }
  
  /** partial portage of strptime to windows (from ex/yks toolkit) */
  static private function strptime($date, $format) {
    $masks = array(
      '%d' => '(?P<d>[0-9]{2})',
      '%m' => '(?P<m>[0-9]{2})',
      '%Y' => '(?P<Y>[0-9]{4})',
      '%H' => '(?P<H>[0-9]{2})',
      '%M' => '(?P<M>[0-9]{2})',
      '%S' => '(?P<S>[0-9]{2})'
    );
    $rexep = "#".strtr(preg_quote($format), $masks)."#";
    if(!preg_match($rexep, $date, $out)) return false;
    $ret = array(
      "tm_sec"  => (int) $out['S'],
      "tm_min"  => (int) $out['M'],
      "tm_hour" => (int) $out['H'],
      "tm_mday" => (int) $out['d'],
      "tm_mon"  => $out['m']?$out['m']-1:0,
      "tm_year" => $out['Y'] > 1900 ? $out['Y'] - 1900 : 0,
    );
    return $ret;
}
  

}

class FileVCSRoot {
  
  private $base;
  
  public function __construct($base) {
    $this->base = $base;
  }
  
  public function __get($name) {
    switch($name) {
      case 'dir': return '';
      case 'name': return '';
      case 'creationdate': #return filectime($this->base);
      case 'date': return filemtime($this->base);
    }
  }
   
}

class FileVCSEntry {

  protected $base;
  protected $dir;
  protected $name;
  protected $version;
  protected $type;
  protected $status;
  protected $props = null;
  
  protected function __construct($base, $dir, $filename) {
    $len = strlen($filename);
    if ($len < 9 || $filename[$len-8] != '.') throw new Exception();
    if ($filename[$len-1] != FILEVCS_NORMAL && $filename[$len-1] != FILEVCS_DELETED) throw new Exception();
    $this->base = $base;
    $this->dir = $dir && substr($dir,-1) != '/' ? $dir.'/' : $dir;
    $this->name = substr($filename,0,$len-8);
    $this->version = (int) substr($filename,$len-7,5);
    $this->type = $filename[$len-2];
    $this->status = $filename[$len-1];
  }  

  public function __get($name) {
    switch($name) {
      case 'dir': return $this->dir;
      case 'name': return $this->name;
      case 'version': return $this->version;
      case 'type': return $this->type;
      case 'isDirectory': return $this->type == FILEVCS_DIR;
      case 'isFile': return $this->type == FILEVCS_FILE;
      case 'deleted': return $this->status == FILEVCS_DELETED;
      case 'path': return $this->dir.$this->name;
      case 'creationdate': #return filectime($this->base.$this->dir.$this->filename());
      case 'date': return filemtime($this->base.$this->dir.$this->filename());
    } 
    $this->loadprops();
    switch($name) {
      case 'copyof': 
      case 'movedfrom':
      case 'movedto': return $this->relation(@$this->props[$name]);
    }
    return @$this->props[$name];
  }

  public function exists() {
    return file_exists($this->base.$this->dir.$this->filename());
  }

  protected function loadprops() {
    if ($this->props === null) {
      $this->props = array();
      $lines = @file($this->base.$this->dir.$this->filename());
      if (@$lines) foreach ($lines as $line) {
        $parts = preg_split('/ /', trim($line), 2);
        if (count($parts) == 2) $this->props[$parts[0]] = $parts[1];
      }
    }
  }

  protected function filename() {
    return $this->name.'.'.sprintf('%05d',$this->version).$this->type.$this->status;
  }
  
  protected function relation($value) {
    return null;
  }
  
}

class FileVCSFile extends FileVCSEntry {
  
  private $versions = null; // array of FileVCSFile
  
  public function __construct($base, $dir, $filename) {
    parent::__construct($base, $dir, $filename);
    if ($this->type != FILEVCS_FILE) throw new Exception();
  }
  
  public function setVersions($versions) {
    $this->versions = $versions;
    ksort($this->versions);
  }
  
  public function __get($name) {
    switch($name) {
      case 'ext': return $this->extension();
      case 'mimetype': return Filetype::getMimetype($this->extension());
      case 'size': return filesize($this->base.$this->content());
      case 'versions': return $this->versions;
      case 'stream': return fopen($this->base.$this->content(),'rb');
      case 'content': return null;        # is in property file, but should not be accessable
      case '_content': return $this->content();  # for FileVCS
      default: return parent::__get($name);
    } 
  }
  
  protected function extension() {
    return ($pos = strrpos($this->name,'.')) !== false ? substr($this->name,$pos+1) : null;
  }
  
  protected function content() {
    if ($this->status == FILEVCS_DELETED) return null;
    $this->loadprops();
    $content = @$this->props['content'];
    if ($content) return $content;
    return $this->dir.$this->name.'.'.sprintf('%05d',$this->version).FILEVCS_CONTENT;
  }
  
  protected function relation($value) {
    if (!$value || !preg_match('@^(.*/)?([^/]+) (\d+)$@', $value, $match)) return null;
    $filename = $match[2].'.'.sprintf('%05d',$match[3]).FILEVCS_FILE.FILEVCS_NORMAL;
    $entry = new FileVCSFile($this->base, @$match[1], $filename);
    return $entry->exists() ? $entry : null;
  }
  
}

class FileVCSDirectory extends FileVCSEntry {
 
  public function __construct($base, $dir, $filename) {
    parent::__construct($base, $dir, $filename);
    if ($this->type != FILEVCS_DIR) throw new Exception();
  }
  
  public function isEmpty() {
    return ($files = @scandir($this->base.$this->dir.$this->name)) && count($files) <= 2;
  }
  
  protected function relation($value) {
    if (!$value || !preg_match('@^(.*/)?([^/]+) (\d+)$@', $value, $match)) return null;
    $filename = $match[2].'.'.sprintf('%05d',$match[3]).FILEVCS_DIR.FILEVCS_NORMAL;
    $entry = new FileVCSDirectory($this->base, @$match[1], $filename);
    return $entry->exists() ? $entry : null;
  }
  
}

class FileVCSListing {
  
  private $base;
  private $dir;
  private $directories;
  private $files;
  
  public function __construct($base, $dir, $entries) {
    $this->base = $base;
    $this->dir = $dir;
    $this->directories = array();
    $this->files = array();
    foreach ($entries as $name => $entry) {
      if ($entry instanceof FileVCSDirectory) $this->directories[$name] = $entry; else $this->files[$name] = $entry;
    }
    ksort($this->directories);
    ksort($this->files);
  }
  
  public function __get($name) {
    switch($name) {
      case 'dir': return $this->dir;
      case 'directories': return $this->directories;
      case 'files': return $this->files;
    }
    return null;
  }
  
}

