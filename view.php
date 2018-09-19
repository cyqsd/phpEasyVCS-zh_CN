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
  
  $dir = sanitizeDir($_GET['dir']);
  $name = sanitizeName($_GET['name']);
  $version = (int) $_GET['version'];
  $vcs = new FileVCS(DATAPATH, @$_GET['tag'], getUserName(), isReadOnly());
  $tag = $vcs->getTag();
  $tagname = $tag && $tag->name ? $tag->name : ($tag ? $_GET['tag'] : null);
  $file = $vcs->getEntry($dir, $name, $version);
  $all = @$_GET['all'] ? 1 : null;
  if (!$file) {
    header("HTTP/1.0 404 Not Found");
  } else {
    $icon = Filetype::getIcon($file->ext).'.png';
    template_header();
?>
    <ul class="actions">
      <li><a href="<?php echo href('versions.php',array('tag'=>$tagname,'dir'=>$dir,'name'=>$name,'all'=>$all)); ?>">查看历史</a></li>
      <li><a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">返回文件夹</a></li>
    </ul>
    <h2>
      内容 <img src="images/<?php echo $icon; ?>" alt=""/> 
      <a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">/<?php echo htmlspecialchars(substr($dir,0,-1)); ?></a><?php echo ($dir ? '/' : '').htmlspecialchars($name); ?>,
      版本 <?php echo $file->version; ?> <span class="date">(<?php echo timestamp2string($file->date); ?>)</date>
    </h2>
<?php 
    if (substr($file->mimetype,0,6) == 'image/') { 
?>
      <p><img src="<?php echo 'get.php?dir='.urlencode($dir).'&amp;name='.urlencode($name).'&amp;version='.urlencode($version); ?>" alt="<?php echo htmlspecialchars($file->name); ?>" /></p>
<?php 
    } else if (substr($file->mimetype,0,5) == 'text/') { 
      $content = '';
      $f = $file->stream;
      while (!feof($f)) $content .= fread($f, 4096*8);
      fclose($f);
?>
      <pre class="prettyprint linenums"><?php echo htmlspecialchars($content); ?></pre>
<?php 
    } else { 
?>
      <p>无法显示文件。</p>
<?php 
    } 
    template_footer();
  }