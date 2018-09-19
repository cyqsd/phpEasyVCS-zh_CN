<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

  require_once('inc/basic.php');

  header('Content-Type: text/plain; charset=UTF-8');
  $delete = isset($_GET['delete']);
  $dir = @opendir(DATAPATH) or die("无法打开数据目录！");
  if (!$delete) {
    echo "如果调用cleanup.php，下列文件将被删除?删除:\r\n";
  } else {
    echo "删除文件:\r\n";
  }
  while ($filename = readdir($dir)) {
    if (strpos($filename,'\\') !== false) {
      echo " - $filename\r\n";
      if ($delete) unlink(DATAPATH.$filename);
    }
  }
