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
  $fromVersion = (int) $_GET['from'];
  $toVersion = (int) $_GET['to'];
  $vcs = new FileVCS(DATAPATH, @$_GET['tag'], getUserName(), isReadOnly());
  $tag = $vcs->getTag();
  $tagname = $tag && $tag->name ? $tag->name : ($tag ? $_GET['tag'] : null);
  $fromFile = $vcs->getEntry($dir, $name, $fromVersion);
  $toFile = $vcs->getEntry($dir, $name, $toVersion);

  /**
   * Paul's Simple Diff Algorithm v 0.1
   * (C) Paul Butler 2007 <http://www.paulbutler.org/>
   * May be used and distributed under the zlib/libpng license.
   */
  function diff($old, $new) {
    $maxlen = 0;
    foreach($old as $oindex => $ovalue) {
      $nkeys = array_keys($new, $ovalue);
      foreach($nkeys as $nindex) {
        $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
                $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
        if($matrix[$oindex][$nindex] > $maxlen) {
          $maxlen = $matrix[$oindex][$nindex];
          $omax = $oindex + 1 - $maxlen;
          $nmax = $nindex + 1 - $maxlen;
        }
      }       
    }
    if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
    return array_merge(
            diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
            array_slice($new, $nmax, $maxlen),
            diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
  }
  
  function htmlDiff($old, $new) {
    preg_match_all('/\p{L}+|./',$old,$matches_old,PREG_PATTERN_ORDER);
    preg_match_all('/\p{L}+|./',$new,$matches_new,PREG_PATTERN_ORDER);
    $diff = diff($matches_old[0], $matches_new[0]);
    $ret = '';
    foreach($diff as $k){
      if(is_array($k))
        $ret .= (!empty($k['d'])?"<del>".hsc(implode('',$k['d']))."</del>":'').
                (!empty($k['i'])?"<ins>".hsc(implode('',$k['i']))."</ins>":'');
      else $ret .= hsc($k);
    }
    return $ret;
  }

  if (!$fromFile || !$toFile) {
    header("HTTP/1.0 404 Not Found");
  } else {
    chdir (dirname(__FILE__) . "/inc");
    require_once("Text/Diff.php");
    
    $diffable = preg_match('@^(text)/@',$toFile->mimetype);
    $icon = Filetype::getIcon($toFile->ext).'.png';
    $all = @$_REQUEST['all'] ? 1 : null;
    template_header();
?>
    <ul class="actions">
      <li><a href="<?php echo href('versions.php',array('tag'=>$tagname,'dir'=>$dir,'name'=>$name,'all'=>$all)); ?>">查看历史</a></li>
      <li><a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">返回文件夹</a></li>
    </ul>

    <h2>
      差异性 <img src="images/<?php echo $icon; ?>" alt=""/> 
      <a href="<?php echo href('browse.php',array('tag'=>$tagname,'dir'=>$dir,'all'=>$all)); ?>">/<?php echo hsc(substr($dir,0,-1)); ?></a><?php echo ($dir ? '/' : '').hsc($name); ?>,
      版本 <?php echo $fromVersion; ?> <span class="date">(<?php echo timestamp2string($fromFile->date); ?>)</span> 
      和 <?php echo $toVersion; ?> <span class="date">(<?php echo timestamp2string($toFile->date); ?>)</date>
    </h2>
<?php 
    if ($diffable) { 
      $from_lines = file(DATAPATH.$fromFile->_content);
      $to_lines = file(DATAPATH.$toFile->_content);
      $textdiff = new Text_Diff('native', $from_lines, $to_lines);
      $edits = $textdiff->getDiff();
      $text = '';
      foreach ($edits as $edit) {
        if (is_a($edit, 'Text_Diff_Op_delete')) {
          foreach ($edit->orig as $line) $text .= "<del>".hsc($line)."\r\n</del>";
        } else if (is_a($edit, 'Text_Diff_Op_add')) {
          foreach ($edit->final as $line) $text .= "<ins>".hsc($line)."\r\n</ins>";
        } else if (is_a($edit, 'Text_Diff_Op_change')) {
          $count = min(count($edit->orig),count($edit->final));
          for ($i=0; $i<$count; $i++) {
            $line = htmlDiff($edit->orig[$i],$edit->final[$i]);
            if (preg_match('/^<del>[^<]*<\/del>$|^<ins>[^<]*<\/ins>$/i', $line)) {
              $text .= substr($line,0,strlen($line)-6)."\r\n".substr($line,strlen($line)-6);
            } else {
              $text .= "<em>".$line."\r\n</em>";
            }
          }
          if (count($edit->orig) > $count) {
            foreach (array_slice($edit->orig,$count) as $line) $text .= "<del>".hsc($line)."\r\n</del>";
          }
          if (count($edit->final) > $count) {
            foreach (array_slice($edit->final,$count) as $line) $text .= "<ins>".hsc($line)."\r\n</ins>";
          }
        } else {
          foreach ($edit->orig as $line) $text .= hsc($line)."\r\n";
        }
      }
      // debug output
      //echo "<pre>".hsc(implode("",$from_lines))."</pre>\r\n";
      //echo "<pre>".hsc(implode("",$to_lines))."</pre>\r\n";
      //echo "<pre>".hsc(print_r($edits,true))."</pre>\r\n";
?>
      <pre class="prettyprint linenums"><?php echo $text; ?></pre>
<?php 
    } else { 
?>
      <p>无法显示差异。</p>
<?php 
    } 
    template_footer();
  }