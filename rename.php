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
  $srcdir = sanitizeDir($_REQUEST['sourcedir']);
  $srcname = sanitizeName($_REQUEST['sourcename']);
  $tgtname = sanitizeName($_REQUEST['targetname']);
  $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
  $result = $vcs->move($srcdir, $srcname, $srcdir, $tgtname, false, @$_REQUEST['comment']);
  if ($result >= 0) {
    $msg = '文件/文件夹 '.$srcname.' 已成功更名为 '.$tgtname.'。 ';
  } else if ($result == VCS_NOACTION || $result == VCS_NOTFOUND) {
    $err = '文件/文件夹 '.$srcname.' 没有找到。';
  } else {
    $err = '重命名文件/文件夹错误 '.$srcname;
  }
  $url = url('browse.php',array('dir'=>$srcdir,'all'=>@$_REQUEST['all'],
                                  'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
