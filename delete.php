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
  $dir = sanitizeDir($_REQUEST['dir']);
  $name = sanitizeName($_REQUEST['name']);
  $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
  $result = $vcs->delete($dir, $name, @$_REQUEST['comment']);
  if ($result >= 0) {
    $msg = '文件/文件夹 '.$name.' 成功删除. ';
  } else if ($result == VCS_NOACTION || $result == VCS_NOTFOUND) {
    $err = '文件/文件夹 '.$name.' 找不到。 ';
  } else {
    $err = '删除错误文件/文件夹 '.$name.'。 ';
  }
  $url = url('browse.php',array('dir'=>$dir,'all'=>@$_REQUEST['all'],
                                  'msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
