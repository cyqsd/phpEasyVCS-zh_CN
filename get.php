<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

  require_once('inc/basic.php');

  $dir = sanitizeDir($_GET['dir']);
  $name = sanitizeName($_GET['name']);
  $version = (int) $_GET['version'];
  $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
  $file = $vcs->getEntry($dir, $name, $version);
  if (!$file) {
    header("HTTP/1.0 404 Not Found");
  } else {
    header("Content-type: ".$file->mimetype);
    header('Content-disposition: attachment; filename="'.$file->name.'"');
    $f = $file->stream;
    fpassthru($f);
    fclose($f);
  }