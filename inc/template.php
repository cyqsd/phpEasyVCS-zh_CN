<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

require_once(dirname(__FILE__).'/settings.class.php');

function hsc($value, $default='') {
  return htmlspecialchars((string) $value ? $value : $default);
}

function url($url, $params=null) {
  if (!$params || count($params) <= 0) return $url;
  $qm = strpos($url, '?') !== false;
  foreach ($params as $name => $value) {
    if ($value !== null) {
      $url .= ($qm ? '&' : '?').$name.'='.urlencode($value);
      $qm = true;
    }
  }
  return $url;
}

function href($url, $params=null) {
  return htmlspecialchars(url($url, $params));
}

function timestamp2string($ts) {
  return strftime(DATE_FORMAT, $ts);
}

function size2string($size) {
  $units = array('B', 'kB', 'MB', 'GB', 'TB');
  for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
  return round($size, 2).' '.$units[$i];
}


function template_header($sync=false) {
  $base = dirname($_SERVER["HTTP_HOST"]).'/';
  $settings = Settings::getSettings();
?>
<!DOCTYPE html>
<html>
<head>
  <title><?php echo htmlspecialchars($settings->get('title', 'phpEasyVCS')); ?></title>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="<?php echo $base; ?>css/default.css" media="screen" />
<?php if ($sync) { ?>
  <link rel="stylesheet" type="text/css" href="<?php echo $base; ?>css/mergely.css" media="screen" />
  <link rel="stylesheet" type="text/css" href="<?php echo $base; ?>css/codemirror.css" media="screen" />
  <script type="text/javascript" src="<?php echo $base; ?>js/jquery-1.10.2.min.js"></script>
  <script type="text/javascript" src="<?php echo $base; ?>js/jquery.json-2.4.min.js"></script>
  <script type="text/javascript" src="<?php echo $base; ?>js/mergely-3.3.6.min.js"></script>
  <script type="text/javascript" src="<?php echo $base; ?>js/codemirror-3.21.min.js"></script>
<?php } else { ?>
  <link rel="stylesheet" type="text/css" href="<?php echo $base; ?>css/prettify.css" media="screen" />
  <script type="text/javascript" src="<?php echo $base; ?>js/jquery-1.10.2.min.js"></script>
  <script type="text/javascript" src="<?php echo $base; ?>js/prettify.js"></script>
<?php } ?>
  <script type="text/javascript" src="<?php echo $base; ?>js/jquery.dialog.js"></script>
</head>
<body <?php if (!$sync) echo 'onload="prettyPrint()"'; ?>>
  <div id="container">
    <div id="header">
      <ul class="menu">
        <?php if ($settings && @$settings->realm) { ?>
          <li><a href="browse.php">浏览</a></li>
          <li><a href="tags.php">标签</a></li>
          <?php if (isUserLevel(USERLEVEL_ADMIN)) { ?>
            <li><a href="settings.php">设置</a></li>
            <li><a href="users.php">用户</a></li>
          <?php } else { ?>
            <li><a href="profile.php">资料</a></li>
          <?php } ?>
        <?php } else { ?>
          <li><a href="settings.php">设置</a></li>
        <?php } ?>
        <li><a href="help.php">帮助</a></li>
      </ul>
      <h1><?php echo htmlspecialchars($settings->get('title', 'phpEasyVCS')); ?></h1>
    </div>
    <div id="content" style="clear:both;">
      <pre id="debug" style="display:none;"></pre>
      <script type="text/javascript">
        function debug(s) { $('#debug').show().text($('#debug').text()+"\r\n\r\n"+s); }
      </script>
<?php 
  if (@$_GET['msg']) {
?>    
      <div class="msg" style="clear:both;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
<?php
  }
  if (@$_GET['error']) {
?>
      <div class="error" style="clear:both;"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php    
  }
?>
<?php
}

function template_footer() {
?>
      <div style="clear:both"></div>
    </div>
    <div id="footer">
      <div class="copyright">(c) Martin Vlcek</div>
      <div class="about"><a href="about.php">关于</a></div>
      <div style="clear:both"></div>
    </div>
  </div>
</body>
</html>
<?php
}