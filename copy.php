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
  $tgtdir = isset($_REQUEST['targetdir']) ? sanitizeDir($_REQUEST['targetdir']) : $srcdir;
  $tgtname = sanitizeName($_REQUEST['targetname']);
  $vcs = new FileVCS(DATAPATH, @$_REQUEST['tag'], getUserName(), isReadOnly());
  $result = $vcs->copy($srcdir, $srcname, $tgtdir, $tgtname, true, @$_REQUEST['comment']);
  if ($result >= 0) {
    $msg = '文件/文件夹 '.$name.' 成功复制。';
  } else if ($result == VCS_NOACTION || $result == VCS_NOTFOUND) {
    $err = '文件/文件夹 '.$name.' 没找到。';
  } else {
    $err = '错误移动文件/文件夹 '.$name;
  }
  $url = url('browse.php',array('dir'=>$srcdir,'tag'=>@$_REQUEST['tag'],'all'=>@$_REQUEST['all'],
                                  'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
