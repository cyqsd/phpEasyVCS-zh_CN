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
  $all = @$_GET['all'] ? 1 : null;
  $vcs = new FileVCS(DATAPATH, @$_GET['tag'], getUserName(), isReadOnly());
  $tag = $vcs->getTag();
  $tagname = $tag && $tag->name ? $tag->name : ($tag ? $_GET['tag'] : null);
  $files = $vcs->getHistory($dir, $name);
  $currvcs = new FileVCS(DATAPATH, null, getUserName(), isReadOnly());
  $allfiles = $currvcs->getHistory($dir, $name);
  $currfile = $allfiles[count($allfiles)];
  $fullhistory = count($allfiles) == count($files);

  template_header();
  $tagtext = '';
  if ($tag && $tag->date) $tagtext .= ' <span>until</span> <span class="date">'.timestamp2string($tag->date).'</span>';
  if ($tag && $vcs->getTag($tag->name)) $tagtext .= ' ('.hsc($tag->name).')';
  
  $numversions = 0;
  foreach ($files as $f) if (!$f->deleted) $numversions++;
  
  foreach ($files as $file) break;
  $quickview = preg_match('@^(text|image)/@',$file->mimetype);
  $diffable = preg_match('@^(text)/@',$file->mimetype);
  $icon = Filetype::getIcon($file->ext).'.png';
  $vfrom = 0;
  $vto = 0;
?>
    <ul class="actions">
      <li><a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">Back to directory</a></li>
    </ul>

    <h2>
      History of <img src="images/<?php echo $icon; ?>" alt=""/> 
      <a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">/<?php echo hsc(substr($dir,0,-1)); ?></a><?php echo ($dir ? '/' : '').hsc($name); ?>
      <?php echo $tagtext; ?>
    </h2>
    <form action="diff.php" method="GET">
      <input type="hidden" name="tag" value="<?php echo hsc($tagname); ?>" />
      <input type="hidden" name="dir" value="<?php echo hsc($dir); ?>" />
      <input type="hidden" name="name" value="<?php echo hsc($name); ?>" />
      <table class="list">
        <thead>
          <tr>
            <th colspan="2" style="text-align:center;">
              <?php if ($diffable && $numversions >= 2) { ?>
                <input type="submit" name="diff" value="Diff"/>
              <?php } ?>
            </th>
            <th class="version">版本</th>
            <th>名称</th>
            <th class="size">Size</th>
            <th class="date" title="<?php echo hsc(TIMEZONE); ?>">日期</th>
            <th>用户</th>
            <th>备注</th>
            <?php if ($quickview && !isReadOnly()) { ?>
            <th colspan="2"></th>  
            <?php } else if ($quickview || !isReadOnly()) { ?>
            <th></th>
            <?php } ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$fullhistory) { ?>
            <tr>
              <td colspan="2"></td>
              <td colspan="<?php echo $quickview ? '8' : '7'; ?>">
                <a href="<?php echo href('versions.php',array('dir'=>$dir,'name'=>$name,'all'=>$all)); ?>">查看全部历史</a>
              </td>
            </tr>
          <?php } ?>
          <?php foreach ($files as $file) { ?>
            <tr>
              <td class="radio">
                <?php if ($diffable && $numversions >= 2 && $vto && !$file->deleted) { ?>
                  <input type="radio" name="from" value="<?php echo $file->version; ?>" <?php echo !$vfrom && ($vfrom = $file->version) ? 'checked="checked"' : ''; ?> />
                <?php } ?>
              </td>
              <td class="radio">
                <?php if ($diffable && $numversions >= 2 && !$file->deleted) { ?>
                  <input type="radio" name="to" value="<?php echo $file->version; ?>" <?php echo !$vto && ($vto = $file->version) ? 'checked="checked"' : ''; ?> />
                <?php } ?>
              </td>
              <td class="version">
                <?php echo $file->version; ?>
              </td>
              <td class="name <?php echo $file->deleted ? 'deleted' : ''; ?>">
                <?php if (!$file->deleted) { ?>
                  <a href="<?php echo 'get.php?dir='.urlencode($dir).'&amp;name='.urlencode($file->name).'&amp;version='.$file->version; ?>"
                      title="下载: <?php echo hsc($file->name); ?> (md5: <?php echo hsc($file->md5); ?>)">
                    <?php echo hsc($file->name); ?>
                  </a>
                <?php } else { ?>
                  (deleted)
                <?php } ?>
              </td>
              <td class="size"><?php echo !$file->deleted ? size2string($file->size) : ''; ?></td>
              <td class="date"><?php echo timestamp2string($file->date); ?></td>
              <td class="user"><?php echo hsc(@$file->user); ?></td>
              <td class="comment">
                <?php echo str_replace('\n','<br/>',hsc($file->comment)); ?>
                <?php display_move_copy($file); ?>
              </td>
              <?php if ($quickview && !isReadOnly() && $file->deleted) { ?>
                <td colspan="2"></td>
              <?php } else { ?>
                <?php if ($quickview && !$file->deleted) { ?>
                  <td class="link quickview">
                    <a href="<?php echo href('view.php',array('tag'=>$tagname,'dir'=>$dir,'name'=>$file->name,'version'=>$file->version,'all'=>$all)); ?>"
                        title="快速浏览: <?php echo hsc($file->name); ?>">Q</a>
                  </td>
                <?php } else if ($quickview) { ?>
                  <td></td>
                <?php } ?>
                <?php if (!isReadOnly() && $file->version < $currfile->version) { ?>
                  <td class="link revert">
                    <a href="<?php echo href('revert.php',array('dir'=>$dir,'name'=>$file->name,'version'=>$file->version,'all'=>$all)); ?>"
                        title="还原版本 <?php echo $file->version; ?>: <?php echo hsc($file->name); ?>">R</a>
                  </td>
                <?php } else if (!isReadOnly()) { ?>
                  <td></td>
                <?php } ?>
              <?php } ?>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </form>
    
<?php
  template_footer();

function display_move_copy($entry) {
  if (($rel = $entry->movedfrom)) {
    echo ' <i>(moved from <a href="'.get_display_link($rel).'">'.
      hsc($rel->dir.$rel->name).'</a>, '.$rel->version.')</i>';
  } else if (($rel = $entry->copyof)) {
    echo ' <i>(copy of <a href="'.get_display_link($rel).'">'.
      hsc($rel->dir.$rel->name).'</a>, '.$rel->version.')</i>';
  } else if (($rel = $entry->movedto)) {
    echo ' <i>(moved to <a href="'.get_display_link($rel).'">'.
      hsc($rel->dir.$rel->name).'</a>, '.$rel->version.')</i>';
  }
}

function get_display_link($entry) {
  return 'versions.php?dir='.urlencode($entry->dir).'&amp;name='.urlencode($entry->name);
}
