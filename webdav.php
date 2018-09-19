<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

define('WEBUI', false);

require_once("inc/basic.php");
require_once("inc/locksupport.class.php");

chdir (dirname(__FILE__) . "/inc");
require_once("vcswebdav.class.php");

$vcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
$locksupport = new LockSupport(DATAPATH.'locks.txt');

$server = new VCSWebDAVServerWithLockSupport($vcs, $locksupport, TMP_DIR);
$server->ServeRequest();
