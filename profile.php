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
  require_once('inc/users.class.php');

  $errors = array();
  # get settings
  $settings = Settings::getSettings();
  # process request
  $username = getUserName();
  $users = Users::getUsers($settings->realm);
  $user = $users->getUser($username);
  if (!$user) {
    // if called by admin
    header('Location: '.url('browse.php'));
    die;
  } else if (isset($_POST['save'])) {
    # update current user
    if (@$_POST['password'] && $_POST['password'] != @$_POST['password2']) {
      $errors[] = '密码不匹配！';
    }
    $timezone = @$_POST['timezone'];
    $password = @$_POST['password'];
    if (count($errors) == 0) {
      $users->setUserTimezone($username, $timezone);
      if ($password) $users->setUserPassword($username, $password);
      $success = $users->save();
      if ($success) {
        header('Location: '.url('profile.php', array('msg'=>'已成功保存资料文件。 ')));
        die;
      }
      $errors[] = '保存资料错误! ';
    }
  } else {
    $timezone = (string) $user->timezone;
  }
  $repositories = array();
  $dh = opendir(ROOTPATH.'data');
  while (($filename = readdir($dh)) !== false) {
    if (substr($filename,0,1) != '.' && is_dir(ROOTPATH.'data/'.$filename)) {
      $repositories[] = $filename;
    }
  }
  sort($repositories);
  
  template_header();
  $timezones = timezone_identifiers_list();
?>
  <?php if (count($errors) > 0) { ?>
    <?php foreach ($errors as $error) { ?><div class="error"><?php echo hsc($error); ?></div><?php } ?>
  <?php } ?>
  <h2>用户资料</h2>
  <form method="POST" action="profile.php">
    <table class="form">
      <tr>
        <td>用户名</td>
        <td><?php echo hsc($username); ?></td>
        <td></td>
      </tr>
      <tr>
        <td>密码</td>
        <td><input type="password" name="password" value=""/></td>
        <td></td>
      </tr>
      <tr>
        <td>重复输入密码</td>
        <td><input type="password" name="password2" value=""/></td>
        <td></td>
      </tr>
      <tr>
        <td>时区</td>
        <td>
          <select name="timezone">
            <?php foreach ($timezones as $tz) echo '<option'.($timezone == $tz ? ' selected="selected"' : '').'>'.$tz."</option>\r\n"; ?>
          </select>
        </td>
        <td></td>
      <tr>
      <?php if (count($repositories) > 1 && count($user->repository) > 1) { ?>
      <tr>
        <td>权限</td>
        <td>
          <?php foreach ($user->repository as $rep) if (in_array((string) $rep->name, $repositories)) { ?>
            <p>
            <?php if (getUserRepository() == (string) $rep->name) { ?>
              <?php echo hsc((string) $rep->name); ?>
            <?php } else { ?>
              <a href="switch.php?repository=<?php echo urlencode((string) $rep->name); ?>"><?php echo hsc((string) $rep->name); ?></a> 
            <?php } ?>
            <?php if ($rep->level <= USERLEVEL_VIEW) { ?>(read only)<?php } ?>
            </p>
          <?php } ?>
        </td>
      </tr>  
      <?php } ?>
      <tr>
        <td>
          <input type="hidden" name="save" value="save"/>
        </td>
        <td colspan="2"><input type="submit" value="Save"/> or <a href="browse.php">Cancel</a></td>
      </tr>
    </table>
  </form>
<?php
  template_footer();