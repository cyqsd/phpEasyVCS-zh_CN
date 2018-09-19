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
  require_once('inc/settings.class.php');

  $hasSettings = Settings::existSettings();
  if ($hasSettings) checkUserLevel(USERLEVEL_ADMIN); 
  
  $errors = array();
  $timezones = timezone_identifiers_list();
  $settings = Settings::getSettings();
  $deftmpdir = ini_get('upload_tmp_dir');
  if (!$deftmpdir) $deftmpdir = '/tmp';
  if (substr($deftmpdir,-1) == '/') $deftmpdir = substr($deftmpdir,0,-1);
  if (isset($_POST['save'])) {
    $settings->title = @$_POST['title'] ? $_POST['title'] : 'phpEasyVCS';
    $settings->auth = isset($_POST['auth']) ? $_POST['auth'] : '';
    $settings->secret = @$_POST['secret'] ? $_POST['secret'] : uniqid();
    if (@$_POST['timezone'] && !in_array($_POST['timezone'], $timezones)) $errors[] = '无效时区！';
    $settings->timezone = @$_POST['timezone'] ? $_POST['timezone'] : 'UTC';
    $settings->dateformat = @$_POST['dateformat'] ? $_POST['dateformat'] : '%Y-%m-%d %H:%M';
    $settings->tmpdir = @$_POST['tmpdir'] ? $_POST['tmpdir'] : $deftmpdir;
    if (@$_POST['forbidpattern']) {
      if (preg_match('/'.$_POST['forbidpattern'].'/','') !== false) { 
        $settings->forbidpattern = '/'.$_POST['forbidpattern'].'/';
      } else { 
        $errors[] = '无效禁止模式'; 
      }
    }
    if (@$_POST['deletepattern']) {
      if (preg_match('/'.$_POST['deletepattern'].'/','') !== false) { 
        $settings->deletepattern = '/'.$_POST['deletepattern'].'/';
      } else { 
        $errors[] = '无效删除模式'; 
      }
    }
    if (@$_POST['debugging']) $settings->debugging = 1;
    if (!@$_POST['username']) {
      $errors[] = '缺少用户名！';
    } else if (!$settings->admin_name && !@$_POST['password']) {
      $errors[] = '您需要指定密码';
    } else if ($settings->admin_name && @$_POST['username'] != (string) $settings->admin_name && !@$_POST['password']) {
      $errors[] = '您需要指定密码, 如果您更改管理员的用户名。';
    } else if ($settings->admin_name && @$_POST['realm'] != (string) $settings->realm) {
      $errors[] = '如果更改域，则需要指定密码。';
    }
    $settings->realm = @$_POST['realm'] ? $_POST['realm'] : 'phpEasyVCS';
    if (@$_POST['password']) {
      if ($_POST['password'] != @$_POST['password2']) {
        $errors[] = '密码不匹配！';
      } else {
        $settings->admin_name = $_POST['username'];
        $settings->admin_a1 = md5($_POST['username'].':'.$settings->realm.':'.$_POST['password']);  
        $settings->admin_a1r = md5('default\\'.$_POST['username'].':'.$settings->realm.':'.$_POST['password']);  
      }
    }
    if (count($errors) <= 0) {
      $settings->save();
      if (!$hasSettings) {
        header('Location: '.url('browse.php'));
        die;
      }
    }
  }
  $timezone = @$settings->timezone ? (string) $settings->timezone : 'UTC';
  $forbidpattern = @$settings->forbidpattern ? substr($settings->forbidpattern,1,-1) : '';
  $deletepattern = @$settings->deletepattern ? substr($settings->deletepattern,1,-1) : '';
  template_header();
?>
<?php if (count($errors) > 0) { ?>
  <?php foreach ($errors as $error) { ?><div class="error"><?php echo hsc($error); ?></div><?php } ?>
<?php } else if (isset($success) && $success) { ?>
  <div class="msg">设置已成功保存。</div>
<?php } else if (isset($success) && !$success) { ?>
  <div class="error">无法保存设置。</div>
<?php } ?>  
  <h2>设置</h2>
  <form method="post">
    <table class="form">
      <tr>
        <td>标题</td>
        <td><input type="text" name="title" value="<?php echo hsc(@$settings->title,'phpEasyVCS'); ?>"/></td>
        <td>您可以自定义站点的标题。</td>
      </tr>
      <tr>
        <td>认证方法</td>
        <td>
          <select name="auth">
            <option value="basic">基本</option>
            <option value="digest" <?php if ((string) @$settings->auth == 'digest') echo 'selected="selected"'; ?> >摘要</option>
            <option value="" <?php if ((string) @$settings->auth === '') echo 'selected="selected"'; ?> >空</option>
          </select>
        </td>
        <td>使用基本身份验证，密码在纯文本中传输，如果您没有
            SSL连接。摘要身份验证只传送用户凭证的摘要。
            这样就更安全了。</td>
      </tr>
      <tr>
        <td>认证领域</td>
        <td>
          <?php if (!$settings->realm) { ?>
            <input type="text" name="realm" value="phpEasyVCS"/></td>
          <?php } else { ?>
            <?php echo hsc($settings->realm); ?>
            <input type="hidden" name="realm" value="<?php echo hsc($settings->realm); ?>" />
          <?php } ?>
        </td>
        <td>身份验证域显示给客户端。</td>
      </tr>
      <tr>
        <td>随机字符串</td>
        <td><input type="text" name="secret" value="<?php echo hsc(@$settings->secret,uniqid()); ?>"/></td>
        <td>需要一个随机(秘密)字符串来使用摘要身份验证来验证响应。</td>
      </tr>
      <tr>
        <td>时区</td>
        <td>
          <select name="timezone">
            <?php foreach ($timezones as $tz) echo '<option'.($timezone == $tz ? ' selected="selected"' : '').'>'.$tz."</option>\r\n"; ?>
          </select>
        </td>
        <td>phpEasyVCS使用的时区。</td>
      <tr>
      <tr>
        <td>日期格式化</td>
        <td><input type="text" name="dateformat" value="<?php echo hsc(@$settings->dateformat,'%Y-%m-%d %H:%M'); ?>"/></td>
        <td>用于显示Web界面中日期的日期格式。</td>
      </tr>
      <tr>
        <td>临时目录</td>
        <td><input type="text" name="tmpdir" value="<?php echo hsc(@$settings->tmpdir,$deftmpdir); ?>"/></td>
        <td>用于文件上传的临时目录。根据服务器设置phpEasyVCS，
            phpEasyVCS可能不允许从/tmp复制文件。在这种情况下，应该将此设置为
            主机提供商指定的目录, 例如：/users/myusername/temp</td>
      </tr>
      <tr>
        <td>禁止模式</td>
        <td><input type="text" name="forbidpattern" value="<?php echo hsc($forbidpattern,''); ?>"/></td>
        <td>匹配不应存储在服务器上的文件名的正则表达式。</td>
      </tr>
      <tr>
        <td>删除模式</td>
        <td><input type="text" name="deletepattern" value="<?php echo hsc($deletepattern,''); ?>"/></td>
        <td>正则表达式匹配的文件名，不应被版本化，而是
            删除请求。</td>
      </tr>
      <tr>
        <td>管理员用户名</td>
        <td><input type="text" name="username" value="<?php echo hsc(@$settings->admin_name,'admin'); ?>"/></td>
        <td>管理员的用户名。</td>
      </tr>
      <tr>
        <td>管理员密码</td>
        <td><input type="password" name="password" value=""/></td>
        <td>管理员的密码。</td>
      </tr>
      <tr>
        <td>重复输入管理员密码</td>
        <td><input type="password" name="password2" value=""/></td>
        <td>管理员的密码。</td>
      </tr>
      <tr>
        <td><input type="hidden" name="save" value="save"/></td>
        <td colspan="2"><input type="submit" value="保存"/> or <a href="browse.php">取消</a></td>
      </tr>
    </table>
  </form>
  <?php template_footer();