<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

  require_once('inc/basic.php');
  require_once('inc/template.php');
  
  $msg = $err = null;
  $dir = sanitizeDir($_POST['dir']);
  if (isset($_POST['addfolder']) && @$_POST['name']) {
    $name = sanitizeName($_POST['name']);
    $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
    $result = $vcs->addDirectory($dir, $name, @$_POST['comment']);
    if ($result == VCS_SUCCESS || $result > 0) {
      $msg = 'Directory '.$name.' was successfully created. ';
    } else if ($result == VCS_NOACTION || $result == VCS_EXISTS) {
      $msg = 'Directory '.$name.' already exists. ';
    } else {
      $err = 'Error creating directory '.$name.'. ';
    }
  } else if (isset($_POST['addfile'])) {
    $msg = $err = '';
    $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
    $extract = @$_POST['extract'];
  	foreach ($_FILES as $inputname => $file) {
  	  if ($file['name']) {
        $name = basename($file['name']);
        if ($extract && ($file['type'] == 'application/zip' || substr($name,-4) == '.zip')) {
          $eerr = "";
          $zip = zip_open($file['tmp_name']);
          if ($zip) {
            while (($zipEntry = zip_read($zip)) !== false) {
              $path = zip_entry_name($zipEntry);
              $spos = strrpos($path, '/');
              if ($spos !== false) {
                $edir = $dir . substr($path,0,$spos+1);
                $ename = substr($path,$spos+1);
              } else {
                $edir = $dir;
                $ename = $path;
              }
              if (strlen($ename) > 0) {
                $tmpname = tempnam(TMP_DIR, 'vcs');
                $result = @file_put_contents($tmpname, zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)));
                if ($result === false) {
                  $eerr .= "File " . $name . ": " . $path . " could not be extracted! ";
                } else {
                  $result = $vcs->addFile($edir, $ename, $tmpname, @$_POST['comment']);
                  if ($result < VCS_SUCCESS && $result != VCS_NOACTION) {
                    $eerr .= "File " . $name . ": " . $path . " could not be stored! ";
                  }
                }
              }
            }
          }
          if ($eerr) $err .= $eerr; else $msg .= 'File '.$name.' was successfully extracted. ';
        } else {
          $result = $vcs->addFile($dir, $name, $file['tmp_name'], @$_POST['comment']);
          if ($result >= 0) {
            $msg .= 'File '.$name.' was successfully added as version '.((int) $result).'. ';
          } else if ($result == VCS_NOACTION) {
            $msg .= 'No changes in file '.$name.'. ';
          } else {
            $err .= 'Error adding file '.$name.'. ';
          }
        }
  	  }
  	}
  }
  if (!$err) $err = null;
  if (!$msg) $msg = null;
  $url = url('browse.php',array('dir'=>$dir,'all'=>@$_REQUEST['all'],'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
  