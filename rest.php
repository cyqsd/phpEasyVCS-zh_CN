<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

define('WEBUI', true);

require_once('inc/basic.php');
require_once('inc/vcsrest.class.php');

$service = new VCSRESTService(getUserName(), isReadOnly(), TMP_DIR);
$service->process();
