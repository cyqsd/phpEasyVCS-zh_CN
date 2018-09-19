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
  
  $secure = (@$_SERVER["HTTPS"] == "on" ? 's' : '');
  $server = $_SERVER['SERVER_NAME'];
  $port = $_SERVER["SERVER_PORT"];
  $serverandport = $_SERVER['SERVER_NAME'] . ($port != '80' ? ':'.$port : '');
  $requesturi = (!isset($_SERVER['REQUEST_URI']) ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI']);
  $requesturi = preg_replace('@/help.php/?$@', '/webdav.php', $requesturi);

  function selfURL() { 
    return ($_SERVER["HTTPS"] == "on" ? "https://" : "http://") . $_SERVER['SERVER_NAME'] .
           ($_SERVER["SERVER_PORT"] != "80" ? ":".$_SERVER["SERVER_PORT"] : "") . 
           (!isset($_SERVER['REQUEST_URI']) ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI']);
  }

  template_header();
?>
  <h2>帮助</h2>
  
  <?php if (isUserLevel(USERLEVEL_ADMIN)) { ?>

  <p>You can create additional repositories by creating new directores in the data folder (on the same level as the <i>default</i> folder).</p>    
    
  <?php } else { ?>
    
  <p>If you have access to multiple repositories, you will see them on your <em>Profile</em> page. On this page you can also switch
  to another repository. If you want to directly log in to a specific repository, then prefix the user name with the repository name 
  and a backslash when logging in, e.g. "specialrepository\<?php echo getUserName(); ?>".</p>

  <?php } ?>

  <h3>WebDAV</h3>
  
  <p>Use <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?></em> for WebDAV access. The actual syntax may vary
  depending on your operating system and WebDAV program.</p>
  
  <p>You have to identify yourself with your user name and password. If you have access to
  multiple repositories, you must prefix the user name with the repository name and a backslash, e.g. "specialrepository\<?php echo getUserName(); ?>", 
  otherwise it is not sure, into which repository you will be logged in.</p>
  
  <p>The root level of the WebDAV drive shows at least the directory <em>current</em>, which represents the
  currently saved files. You will also see the tags created in the web interface, which represent
  read-only views of your VCS at a specific time. Additionally you can view the VCS at a specific
  point in time by manually specifying a date and time in the format <em>YYYY-MM-DD</em> or <em>YYYY-MM-DDTHH:MM</em>,
  e.g. <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?>/2011-01-01</em> or
  <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?>/2011-01-01T16:00</em> (this might not work with your WebDAV client).</p>
  

  <h4>Linux</h4>  

  <p>Enter the following URL in <b>Nautilus</b> or <b>Caja</b>: 
    <em>dav<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?></em></p>
  <p>If this does not work, connect explicitely by use of its menu <i>File/Connect to Server</i> and set
    Server to <em><?php echo $server; ?></em>,  type to <em>WebDAV (HTTP)</em> or <em>Secure WebDAV (HTTPS)</em>, 
    path to <em><?php echo $requesturi; ?></em> and enter your user name and password.</p>
  <p>You can also connect using <b>Gnome Commander</b>: set type to <em>WebDAV</em>, server to 
    <em><?php echo $server; ?></em><?php if ($port && $port != "80") { ?>, port to <em><?php echo $port; ?></em><?php } ?> 
    and remote directory to <em><?php echo $requesturi; ?></em>. </p>
  <p>Or install <b>davfs2</b> and mount the WebDAV, e.g.:</p>
  <pre class="prettyprint">
sudo apt-get install davfs2
sudo mkdir /media/easyvcs
sudo mount -t davfs http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?> /media/easyvcs</pre>
  <p>You probably need to add options like <em>-o rw,user,uid=myusername</em> to be able to write, too.</p>


  <h4>Windows XP</h4>
  
  <p>Preparation:</p>
  <ul>
    <li>Download and install 
    <a href="http://www.microsoft.com/downloads/de-de/details.aspx?familyid=17C36612-632E-4C04-9382-987622ED1D64">KB907306</a> 
    for web folders</li>
    <li>To use basic authentication, set the DWORD registry entry <em>UseBasicAuth</em> in
    <em>HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters</em> to 
    <em>1</em> and restart Windows.</li>
  </ul>
  <p>Goto explorer - Tools - Map Network Drive - Connect to a Web site and enter
  <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?></em> as URL</p>
  <p>Or goto explorer - Tools - Map Network Drive and directly add 
  <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?></em>
  as folder (this only seems to work if your phpEasyVCS installation requires no authentication)</p>


  <h4>Windows Vista/Windows 7</h4>

  <p>Preparation:</p>
  <ul>
  	<li>Go to Settings in your phpEasyVCS instance and make sure that authentication method is Digest.
	<li>Or, if you really want to use Basic authentication, follow the steps in <a href="http://support.microsoft.com/kb/841215/en-us">KB841215</a>: Set the DWORD registry entry <em>BasicAuthLevel</em> in
    <em>HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\WebClient\Parameters</em> to <em>2</em> and restart Windows.
	</li>
  </ul>
  <p>Goto explorer - Map Network Drive - Connect to a Web site and enter
  <em>http<?php echo $secure; ?>://<?php echo $serverandport.$requesturi; ?></em> as URL.</p>
  <p>The combination of http and basic authentication might not  work in Windows 7.</p>

  <h4>Alternatives for Windows XP/Windows Vista/Windows 7</h4>  

  <p><a href="http://www.ghisler.com/">TotalCommander</a> has a 
    <a href="http://ghisler.fileburst.com/fsplugins/webdav.zip">WebDAV plugin</a>.</p>
  <p><a href="http://www.bitkinex.com/">BitKinex</a> - All-in-one FTP/SFTP/HTTP/WebDAV Client (Freeware)</p>
  <ul>
  <li>When setting up the connection you need to specify first the server 
    <em><?php echo $server; ?></em> and then set
    <em><?php echo $requesturi; ?></em> as default directory.</li>
  </ul>
  <p><a href="http://www.netdrive.net/">NetDrive</a> (free for home use): You can assign a drive letter
  to the WebDAV drive and use it like a local drive.</p>
  
<?php
  template_footer();