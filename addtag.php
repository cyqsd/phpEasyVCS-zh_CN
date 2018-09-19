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
  if (isset($_POST['addtag']) && @$_POST['name']) {
    $name = sanitizeName($_POST['name']);
    $date = @$_POST['date'] ? sanitizeName($_POST['date']) : null;
    $vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
    $result = $vcs->addTag($name, $date);
    if ($result >= 0) {
      $msg = 'Tag '.$name.' was successfully created. ';
    } else if ($result == VCS_EXISTS) {
      $err = 'A tag with name '.$name.' already exists. ';
    } else {
      $err = 'Error creating tag '.$name.'. ';
    }
  }
  $url = url('tags.php',array('msg'=>$msg,'error'=>$err));
  header('Location: '.$url);
