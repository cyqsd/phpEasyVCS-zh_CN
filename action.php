<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

  if (isset($_REQUEST['start-delete']) || isset($_REQUEST['start-copy']) || isset($_REQUEST['start-move'])) {
    include('browse.php');
    exit(0);
  }

  require_once('inc/basic.php');
  require_once('inc/template.php');

  $msg = $err = null;
  $dir = sanitizeDir($_REQUEST['dir']);
  $vcs = new FileVCS(DATAPATH, $_REQUEST['tag'], getUserName(), isReadOnly());
  $names = @$_REQUEST['name'];
  if ($names) {
    foreach ($names as $i => $name) {
      $names[$i] = sanitizeName($name);
    }
    if (isset($_REQUEST['delete'])) {
    	$num = 0;
    	foreach ($names as $name) {
        if ($vcs->delete($dir, $name, @$_REQUEST['comment']) >= 0) $num++;
      }
    	if ($num == count($names)) {
    		$msg = "$num files/directories have been successfully deleted.";
    	} else if ($num > 0) {
    		$failed = count($names) - $num;
    		$err = "$num files/directories have been successfully deleted, $failed files/directories could not be deleted.";
    	} else {
    		$err = "Error deleting files/directories.";
    	}
    } else if (isset($_REQUEST['move'])) {
      $targetdir = sanitizeDir(@$_REQUEST['targetdir']);
      $overwrite = true && @$_REQUEST['overwrite'];
      $num = 0;
      foreach ($names as $name) {
        if ($vcs->move($dir, $name, $targetdir, $name, $overwrite, @$_REQUEST['comment']) >= 0) $num++;
      }
      if ($num == count($names)) {
        $msg = "$num files/directories have been successfully moved.";
      } else if ($num > 0) {
        $failed = count($names) - $num;
        $err = "$num files/directories have been successfully moved, $failed files/directories could not be moved.";
      } else {
        $err = "Error moving files/directories.";
      }
    } else if (isset($_REQUEST['copy'])) {
      $targetdir = sanitizeDir(@$_REQUEST['targetdir']);
      $overwrite = true && @$_REQUEST['overwrite'];
      $num = 0;
      foreach ($names as $name) {
        if ($vcs->copy($dir, $name, $targetdir, $name, $overwrite, @$_REQUEST['comment']) >= 0) $num++;
      }
      if ($num == count($names)) {
        $msg = "$num files/directories have been successfully copied.";
      } else if ($num > 0) {
        $failed = count($names) - $num;
        $err = "$num files/directories have been successfully copied, $failed files/directories could not be copied.";
      } else {
        $err = "Error copying files/directories.";
      }
    } else if (isset($_REQUEST['download'])) {
      $tmpfile = tempnam(TMP_DIR,'vcs');
      $zip = new ZipArchive();
      if (!$zip || ($result = $zip->open($tmpfile, ZipArchive::CREATE)) !== true) {
        $err = "Error downloading files/directories.";
      } else {
        $result = null;
        foreach ($names as $name) {
          $result = addFile($zip, $vcs->getEntry($dir, $name));
          if ($result) break;
        }
        if ($result) {
          $zip->close();
          $err = "Error adding $result to zip. ";
        } else if (!$zip->close()) {
          $err = "Error creating ZIP.";
        } else {
          header('Content-type: application/zip');
          header('Content-disposition: attachment; filename="download.zip"');
          readfile($tmpfile);
          unlink($tmpfile);
          exit(0);
        }
      }
    }
  } else {
    $msg = 'Please select at least one file or directory.';
  }
  $url = url('browse.php',array('dir'=>$dir,'tag'=>@$_REQUEST['tag'],'all'=>@$_REQUEST['all'],
                                  'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);

  function addFile(&$zip, $entry) {
    global $dir, $vcs;
    $path = substr($entry->dir.$entry->name, strlen($dir));
    if ($entry->isDirectory) {
      if (!$zip->addEmptyDir($path.'/')) return $entry->dir.$entry->name;
      $listing = $vcs->getListing($entry->dir.$entry->name);
      foreach ($listing->directories as $directory) {
        if ($result = addFile($zip, $directory)) return $result;
      }
      foreach ($listing->files as $file) {
        if ($result = addFile($zip, $file)) return $result;
      }
    } else {
      if (!$zip->addFile(DATAPATH.$entry->_content, $path)) return $entry->dir.$entry->name;
    }
  }