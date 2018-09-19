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
  if (@$_GET['name']) {
    $name = sanitizeName($_GET['name'], null, getUserName());
    $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
    $result = $vcs->deleteTag($name);
    if ($result >= 0) {
      $msg = '标签 '.$name.' 已成功删除。 ';
    } else if ($result == VCS_NOTFOUND) {
      $err = '标签 '.$name.' 没找到。 ';
    } else {
      $err = '删除标签错误 '.$name.'。';
    }
  }
  $url = url('tags.php', array('msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
