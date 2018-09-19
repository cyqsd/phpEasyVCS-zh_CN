<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

class VCSRESTService {
  
  protected $username;
  protected $readonly;
  protected $tmp;
  
  protected $uri;
  protected $path;
  protected $method;
  protected $headers;
  
  public function __construct($username, $readonly, $tmp='/tmp') {
    $this->username = $username;
    $this->readonly = $readonly;
    $this->tmp = $tmp;
    // default uri is the complete request uri
    $this->uri = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
    $this->uri .= "://".$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"];
    $this->path = substr($_SERVER["REQUEST_URI"], strlen($_SERVER["SCRIPT_NAME"]));
    if (($pos = strpos($this->path,'?')) !== false) $this->path = substr($this->path,0,$pos);
    if (!$this->path) $this->path = '/';
    $this->path = urldecode($this->path);
    $this->method = $_SERVER["REQUEST_METHOD"];
    $this->headers = array();
    foreach ($_SERVER as $k => $v) {
      if (substr($k, 0, 5) == "HTTP_") {
        $k = str_replace('_', ' ', substr($k, 5));
        $k = str_replace(' ', '-', ucwords(strtolower($k)));
        $this->headers[$k] = $v;
      }
    }
  }
  
  public function process() {
    $method = strtoupper($this->method);
    if (method_exists($this, $method)) {
      $this->$method();
    } else {
      header("HTTP/1.1 501 Not Implemented");
    }
  }
  
  private function getPathInfo($path) {
    $info = array();
    if (@$path[0] == '/') $path = substr($path,1);
    if (@$path[strlen($path)-1] == '/') $path = substr($path,0,strlen($path)-1);
    $parts = preg_split('@/@',$path);
    $info['tag'] = count($parts) > 0 ? $parts[0] : '';
    $info['dir'] = count($parts) > 2 ? implode('/',array_slice($parts,1,count($parts)-2)).'/' : '';
    $info['name'] = count($parts) > 1 ? $parts[count($parts)-1] : '';
    return $info;
  }
  
  private function setStatusHeader($result) {
    switch ($result) {
      case VCS_ERROR: header("HTTP/1.1 500 Server Error"); break;
      case VCS_NOTFOUND: header("HTTP/1.1 404 Not Found"); break;
      case VCS_FORBIDDEN:
      case VCS_READONLY: header("HTTP/1.1 403 Forbidden"); break;
      case VCS_EXISTS: header("HTTP/1.1 412 Precondition Failed"); break;
      case VCS_CONFLICT: header("HTTP/1.1 409 Conflict"); break;
      default: header("HTTP/1.1 200 Ok");
    }
  }
  
  private function setContentHeader($mimetype, $filename=null) {
    if (substr($mimetype,0,5) == 'text/') {
      header("Content-Type: $mimetype; charset=utf-8");
    } else if ($mimetype == 'application/json') {
      header("Content-Type: $mimetype");
    } else {
      header("Content-Type: $mimetype");
      header("Content-Disposition: attachment; filename=$filename");
    }
  }
  
  function GET() {
    $target = $this->getPathInfo($this->path);
    $includeDeleted = @$_GET['deleted'];
    $withHistory = @$_GET['history'];
    $vcs = new FileVCS(DATAPATH, $target['tag'], $this->username, $this->readonly);
    $entry = $vcs->getEntry($target['dir'], $target['name']);
    if (($entry && $entry->isDirectory) || (!$target['dir'] && !$target['name'])) {
      $listing = $vcs->getListing($target['dir'].$target['name'], $includeDeleted, $withHistory);
      $result = array();
      foreach ($listing->directories as $dir) {
        $entry = array('directory'=>true, 'name'=>$dir->name, 'version'=>$dir->version,
                       'date'=>$dir->date, 'creationdate'=>$dir->creationdate, 
                       'deleted'=>$dir->deleted);
        $result[] = $entry;
      }
      foreach ($listing->files as $file) {
        $entry = array('directory'=>false, 'name'=>$file->name, 'version'=>$file->version,
                       'mimetype'=>$file->mimetype, 'size'=>$file->size,
                       'md5'=>$file->md5, 'sha1'=>$file->sha1,
                       'date'=>$file->date, 'creationdate'=>$file->creationdate, 
                       'deleted'=>$file->deleted);
        if ($withHistory) {
          $versions = array();
          foreach ($file->versions as $v) {
            $versions[] = array('version'=>$v->version, 'size'=>$v->size,
                                'md5'=>$file->md5, 'sha1'=>$file->sha1,
                                'date'=>$file->date, 'creationdate'=>$file->creationdate, 
                                'deleted'=>$file->deleted);
          }
          $entry['versions'] = $versions;
        }
        $result[] = $entry;
      }
      header("HTTP/1.1 200 OK");
      $this->setContentHeader('application/json');
      echo json_encode($result);
    } else if ($entry && $entry->isFile){
      $this->setStatusHeader(null);
      $this->setContentHeader($entry->mimetype, $entry->name);
      header("Content-Length: $entry->size");
      $stream = $entry->stream;
      while (!feof($stream)) {
        $buffer = fread($stream, 4096);
        echo $buffer;
      }
    } else {
      header("HTTP/1.1 404 Not Found");
    }
  }
  
  function PUT() {
    $target = $this->getPathInfo($this->path);
    $vcs = new FileVCS(DATAPATH, $target['tag'], $this->username, $this->readonly);
    $comment = @$_GET['comment'];
    if ($vcs->isTag()) {
      header("HTTP/1.1 403 Forbidden");
    } else {
      $tmpname = tempnam($this->tmp, 'vcs');
      $fp = fopen($tmpname, "w");
      $in = fopen('php://input', "r");
      while (!feof($in)) {
        fwrite($fp, fread($in, 64*1024));
      }
      fclose($fp);
      $result = $vcs->addFile($target['dir'], $target['name'], $tmpname, $comment);
      $this->setStatusHeader($result);
      if ($result >= 0) {
        $this->setContentHeader('application/json');
        echo json_encode(array('path' => $this->path, 'version' => $result));
      }
    }
  }
  
  function POST() {
    $target = $this->getPathInfo($this->path);
    $vcs = new FileVCS(DATAPATH, $target['tag'], $this->username, $this->readonly);
    $comment = @$_POST['comment'];
    $msg = '';
    if ($vcs->isTag()) {
      header("HTTP/1.1 403 Forbidden");
      return;
    } else if (isset($_POST['source'])) {
      // copy or move
      $source = $this->getPathInfo($_POST['source']);
      $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] && strtoupper($_POST['overwrite']) != 'FALSE';
      $move = isset($_POST['move']) && $_POST['move'] && strtoupper($_POST['move']) != 'FALSE';
      $vcs->setTag($source['tag']);
      if ($move) {
        $result = $vcs->move($source['dir'], $source['name'], $target['dir'], $target['name'], $overwrite, $comment);
      } else {
        $result = $vcs->copy($source['dir'], $source['name'], $target['dir'], $target['name'], $overwrite, $comment);
      }
    } else if (@$_POST['version']) {
      // revert
      $version = (int) $_POST['version'];
      $result = $vcs->revertFile($target['dir'], $target['name'], $version, $comment);
    } else if (@$_FILES['file']) {
      // upload file
      if (@$_FILES['file']['tmp_name']) {
        $name = basename($_FILES['file']['name']);
        $result = $vcs->addFile($target['dir'], $target['name'], $_FILES['file']['tmp_name'], $comment);
      } else {
        // file size exceeds limit
        $result = VCS_FORBIDDEN;
      }
    } else if (@$_FILES['zip']) {
      // add all files in the zip
      if (@$_FILES['file']['tmp_name']) {
        $zip = zip_open($_FILES['file']['tmp_name']);
        if ($zip) {
          while (($zipEntry = zip_read($zip)) !== false) {
            $path = zip_entry_name($zipEntry);
            $spos = strrpos($path, '/');
            if ($spos !== false) {
              $dir = $target['dir'] . $target['name'] . '/' . substr($path,0,$spos+1);
              $name = substr($path,$spos+1);
            } else {
              $dir = $target['dir'] . $target['name'] . '/';
              $name = $path;
            }
            if (strlen($name) > 0) {
              $tmpname = tempnam($this->tmp, 'tmp');
              $result = @file_put_contents($tmpname, zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)));
              if ($result === false) {
                $msg .= $path . " could not be extracted! ";
              } else {
                $result = $vcs->addFile($dir, $name, $tmpname, $comment);
                if ($result < VCS_SUCCESS && $result != VCS_NOACTION) {
                  $msg .= $path . " could not be stored! ";
                }
              }
            }
          }
        }
        $result = $msg ? VCS_ERROR : VCS_SUCCESS;
      } else {
        // file size exceeds limit
        $result = VCS_FORBIDDEN;
      }
    } else {
      // add directory
      $result = $vcs->addDirectory($target['dir'], $target['name'], $comment);
    }
    $this->setStatusHeader($result);
    if ($result >= 0) {
      $this->setContentHeader('application/json');
      echo json_encode(array('path' => $this->path, 'version' => $result, 'message' => $msg));
    }
  }
  
  function DELETE() {
    $target = $this->getPathInfo($this->path);
    $vcs = new FileVCS(DATAPATH, $target['tag'], $this->username, $this->readonly);
    $comment = @$_POST['comment']; // DELETE does not allow post parameters, so this is theoretically unnecessary
    if (!$comment) $comment = @$_GET['comment'];
    $result = $vcs->delete($target['dir'], $target['name'], $comment);
    $this->setStatusHeader($result);
  }
  
}