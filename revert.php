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

  $msg = $err = '';
  $dir = sanitizeDir($_REQUEST['dir']);
  $name = sanitizeName($_REQUEST['name']);
  $version = (int) @$_REQUEST['version'];
  $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
  $result = $vcs->revertFile($dir, $name, $version);
  if ($result >= 0) {
    $msg = 'File '.$name.' was successfully reverted to version '.$version;
  } else if ($result == VCS_NOACTION) {
    $msg = 'No changes in file '.$name;
  } else {
    $err = 'Error reverting file '.$name.' to version '.$version;
  }
  $url = url('browse.php',array('dir'=>$dir,'all'=>@$_REQUEST['all'],
                                  'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
