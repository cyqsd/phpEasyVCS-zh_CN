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
  require_once('inc/filetype.class.php');

  $dir = sanitizeDir($_GET['dir']);
  $vcs = new FileVCS(DATAPATH, @$_GET['tag'], getUserName(), isReadOnly());
  $tag = $vcs->getTag();
  $tagname = $tag && $tag->name ? $tag->name : ($tag ? $_GET['tag'] : null);

  template_header(true);  
  $v = filemtime(ROOTPATH.'applet/vcsapplet.jar');
  $uri = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
  $uri .= "://".$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"];
  $uri = substr($uri,0,strlen($uri)-8) . 'rest.php/'.($tagname ? urlencode($tagname) : 'current');
  $currlink = 'browse.php?'.($tagname ? 'tag='.urlencode($tagname).'&' : '').'dir='.urlencode($dir);
  $excludePattern = defined('FORBID_PATTERN') ? substr(FORBID_PATTERN,1,-1) : ''; // remote / from begin and end of pattern
?>

<noscript>
  <div class="error">本程序的网页需要JavaScript和Java Applet！</div>
</noscript>
<ul class="actions">
  <?php if (!$tagname) { ?>
  <li style="display:none"><a href="#" id="open-start">开始</a></li>
  <?php } ?>
  <li style="display:none"><a href="#" id="refresh-all">刷新</a></li>
  <li style="display:none"><a href="#" id="open-all">打开全部</a></li>
  <li style="display:none"><a href="#" id="close-all">关闭全部</a></li>
  <li style="display:none"><a href="#" id="show-identical" class="toggle">显示相同的文件</a></li>
  <li><a href="<?php echo htmlspecialchars($currlink); ?>">返回文件夹</a></li>
</ul>
<h2>
  同步 
  <a href="<?php echo htmlspecialchars($currlink); ?>">/<?php echo htmlspecialchars(substr($dir,0,-1)); ?></a>
</h2>
<p id="localrootp">
  本地文件夹: <span id="localroot"></span> 
  <button id="selectlocalroot">选择</button>
</p>
<div id="progress" class="progress" style="display:none;">
  <div class="progress-bar"></div>
  <div class="progress-text"></div>
</div>
<table id="entries" class="list hide-identical" style="display:none;">
  <thead>
    <tr><th rowspan="2">名称</th><th colspan="3" class="side">本地</th><th rowspan="2" colspan="5" class="action">活动</th><th colspan="3" class="side">远程</th><th rowspan="2" colspan="3"></th></tr>
    <tr><th class="version">版本</th><th class="size">大小</th><th class="date">日期</th><th class="version">版本</th><th class="size">大小</th><th class="date">日期</th></tr> 
  </thead>
  <tbody>
  </tbody>
</table>

<?php if (!$tagname) { ?>
<a id="dialog-background" class="dialog-background" href="<?php echo $currlink; ?>" style="display:<?php echo isset($_GET['addfolder']) || isset($_GET['addfile']) ? 'block' : 'none'; ?>"></a>

<div class="dialog-container">
  <div id="start-dialog" class="dialog" style="display:none;">
    <a href="<?php echo $currlink; ?>"><img src="images/close.png" class="close" alt="Close dialog"/></a>
    <form action="" method="POST">
      <h2>同步启动</h2>
      <p>这将开始复制和删除文件，如所示。</p>
      <label for="comment">备注:</label>
      <textarea name="comment" id="comment" style="width:90%; height:4em;"></textarea>
      <div id="sync-progress" class="progress" style="display:none;">
        <div class="progress-bar"></div>
        <div class="progress-text"></div>
      </div>
      <input type="submit" class="submit" id="start" name="start" value="Start"/>
    </form>
  </div>
</div>

<div class="dialog-container">
  <div id="merge-dialog" class="dialog" style="display:none;width:80%">
    <a href="<?php echo $currlink; ?>"><img src="images/close.png" class="close" alt="Close dialog"/></a>
    <h2>合并 <span id="merge-path"></span> <button id="merge-save">保存</button></h2>
    <div style="position:relative;">
    	<div id="merge-div" style="position:static;"></div>
    </div>
  </div>
</div>
<?php } ?>

<div class="dialog-container">
  <div id="quickview-dialog" class="dialog" style="display:none;width:80%">
    <a href="<?php echo $currlink; ?>"><img src="images/close.png" class="close" alt="Close dialog"/></a>
    <h2>快速浏览 <span id="quickview-local">本地</span><span id="quickview-remote">远程</span> <span id="quickview-path"></span></h2>
    <div style="position:relative;">
      <textarea name="quickview-text" id="quickview-text" wrap="off" style="position:static;"></textarea>
    </div>
    <div style="position:relative">
      <img id="quickview-image" src="" alt="" style="position:static;"/>
    </div>
  </div>
</div>

<p style="text-align: center;">
  <applet id="syncApplet" name="SyncApplet" code="net.sf.phpeasyvcs.SynchronizerApplet" 
       archive="applet/vcsapplet.jar?v=<?php echo $v; ?>" width="1"  height="1" style="opacity:0">
    <param name="root" value="<?php echo hsc($uri) . ($dir ? '/'.substr($dir,0,-1)    : ''); ?>">
    <param name="excludePattern" value="<?php echo $excludePattern; ?>">
    <param name="java_arguments" value="-Xmx256m">
  </applet>
</p>

<script type="text/javascript">
  // <![CDATA[
  var num = 0;
  var dirIds = { };
  var icons = <?php echo json_encode(Filetype::getIcons()); ?>;
  var mimetypes = <?php echo json_encode(Filetype::getMimetypes()); ?>;

  function showLocalRoot(localRoot) {
    // set a cookie for the local root, which will be used the next time
    var d = new Date();
    d.setTime(d.getTime()+(60*24*3600*1000));
    document.cookie="localroot="+localRoot+"; expires="+d.toGMTString();
    $('#localroot').text(localRoot);
  }

  function showScanProgress(progress, total, percent) {
    $('#selectlocalroot').hide();
    $('#progress').show();
    $('#progress .progress-text').text(progress+" directories of "+total);
    $('#progress .progress-bar').css({ 'width': percent+'%' });
  }

  function showScanResult(dir, jsonEntries) {
    var entries = $.parseJSON(jsonEntries);
    displayEntries(dir, entries);
    $('#progress').hide();
    $('#entries').show();
    $('ul.actions, ul.actions li').show();
  }

  function showSyncProgress(progress, total, percent) {
    $('#start-dialog .progress-text').text("Synchronizing...");
    $('#start-dialog .progress-bar').show().css('width',percent+'%');
  }

  function showSyncResult(result) {
    if (result !== true) {
      var failedActions = $.parseJSON(result);
      var failedUploads = [];
      var failedDownloads = [];
      var failedLocalDeletes = [];
      var failedRemoteDeletes = [];
      for (var i=0; i<failedActions.length; i++) {
        var action = failedActions[i];
        if (action.action == 'DELETE_LOCAL') failedLocalDeletes[failedLocalDeletes.length] = action.path; else
        if (action.action == 'COPY_TO_LOCAL') failedDownloads[failedDownloads.length] = action.path; else
        if (action.action == 'COPY_TO_REMOTE') failedUploads[failedUploads.length] = action.path; else
        if (action.action == 'DELETE_REMOTE') failedRemoteDeletes[failedRemoteDeletes.length] = action.path;
      }
      var text = '';
      if (failedUploads.length > 0) text += "The following files could not be uploaded:\r\n - "+failedUploads.join('\r\n - ')+"\r\n";
      if (failedDownloads.length > 0) text += "The following files could not be downloaded:\r\n - "+failedDownloads.join('\r\n - ')+"\r\n";
      if (failedLocalDeletes.length > 0) text += "The following files could not be deleted locally:\r\n - "+failedLocalDeletes.join('\r\n - ')+"\r\n";
      if (failedRemoteDeletes.length > 0) text += "The following files could not be deleted:\r\n - "+failedRemoteDeletes.join('\r\n - ')+"\r\n";
      alert(text);
    }
    if ($('#start-dialog:visible').size() != 0) {
      $('#start-dialog').dialog('close');
      document.SyncApplet.scan();
    }
  }    

  function showError(error) {
    alert("Error: "+error);
  }

  function displayEntries(dir, entries) {
    var id = null;
    var level = 0;
    var dirclasses = '';
    var $prevRow = null;
    if (dir) {
      parentId = dirIds[dir];
      if (!parentId) return;
      $('#entries tbody tr.'+parentId).remove();
      dirclasses = $('#'+parentId).attr('class');
      dirclasses = dirclasses.replace(/[^\s]+-curr|open|closed|directory|root/g, '').replace(/\s+/g, ' ').trim();
      dirclasses = dirclasses + ' ' + parentId + ' ' + parentId + '-curr';
      level = dir.split("/").length;
      $prevRow = $('#'+parentId);
    } else {
      dirclasses = 'root';
      $('#entries tbody tr').remove();
    }
    var $tbody = $('#entries tbody');
    for (var i in entries) {
      var entry = entries[i];
      var isdir = (entry['local'] && entry['local']['dir']) || (entry['remote'] && entry['remote']['dir']);
      var dirId = null;
      var dirName = null;
      var entryNum = num++;
      if (isdir) {
        dirId = 'dir-'+entryNum;
        dirName = (dir ? dir+'/' : '')+entry['name'];
        dirIds[dirName] = dirId;
      }
      var $row = $('<tr '+(dirId ? 'id="'+dirId+'" ' : '')+'class="'+(isdir ? 'open directory' : entry['action'])+' '+dirclasses+'"/>');
      var $td = $('<td class="name" style="padding-left:'+(7+16*level)+'px"></td>').text(entry['name']);
      $td.append($('<input type="hidden" name="entry-'+entryNum+'" value=""/>').val((dir ? dir+'/' : '')+entry['name']));
      var extension = entry['name'].substring(entry['name'].lastIndexOf('.')+1).toLowerCase();
      var mimetype = mimetypes[extension];
      if (!mimetype) mimetype = '';
      if (isdir) {
        $td.prepend('<img class="closed" src="images/folder.png" alt="Open folder" /> ');
        $td.prepend('<img class="open" src="images/folder_open.png" alt="Close folder" /> ');
        $td.append('<img class="refresh" src="images/refresh.png" alt="Refresh" />');
      } else {
        var icon = icons[extension];
        if (!icon) icon = 'unknown';
        $td.prepend('<img src="images/'+icon+'.png" alt=""/> ');
        var mimetypemod = mimetype == 'text/x-php' ? 'application/x-httpd-php' : mimetype;
        $td.append($('<input type="hidden" name="entrymimetype-'+entryNum+'" value=""/>').val(mimetypemod));
      }
      $row.append($td);
      if (entry['local']) {
        var size = entry['local']['size'];
        $td.append($('<input type="hidden" name="entrysize-local-'+entryNum+'" value=""/>').val(size));
        if (!size) size ='';
        var version = entry['local']['version'];
        if (version < 0) version = "";
        $row.append('<td class="version">'+version+'</td>');
        $row.append('<td class="size">'+size+'</td>');
        $row.append('<td class="date">'+entry['local']['date']+'</td>');
      } else {
        $row.append('<td colspan="3"></td>');
      }
      if (isdir) {
        $row.append('<td colspan="5"></td>');
      } else {
        if (entry['local']) {
          $row.append('<td class="link delete ACTION DELETE_LOCAL"><a href="#" title="Delete local">X</a></td>');
        } else $row.append('<td class="nolink"></td>');
        if (entry['remote'] && entry['action'] != 'IDENTICAL') {
          $row.append('<td class="link ACTION COPY_TO_LOCAL"><a href="#" title="Copy to local">&lt;&lt;</a></td>');
        } else $row.append('<td class="nolink"></td>');
        $row.append('<td class="link ACTION NONE"><a href="#" title="Do nothing">-</a></td>');
        if (entry['local'] && entry['action'] != 'IDENTICAL') {
          $row.append('<td class="link ACTION COPY_TO_REMOTE"><a href="#" title="Copy to remote">&gt;&gt;</a></td>');
        } else $row.append('<td class="nolink"></td>');
        if (entry['remote']) {
         $row.append('<td class="link delete ACTION DELETE_REMOTE"><a href="#" title="Delete remote">X</a></td>');
        } else $row.append('<td class="nolink"></td>');
      }
      if (entry['remote']) {
        var size = entry['remote']['size'];
        $td.append($('<input type="hidden" name="entrysize-remote-'+entryNum+'" value=""/>').val(size));
        if (!size) size = "";
        $row.append('<td class="version">'+entry['remote']['version']+'</td>');
        $row.append('<td class="size">'+size+'</td>');
        $row.append('<td class="date">'+entry['remote']['date']+'</td>');
      } else {
        $row.append('<td colspan="3"></td>');
      }
      if (isdir) {
        $row.append('<td colspan="3"></td>');
      } else if (mimetype.substring(0,5) != 'text/' && mimetype.substring(0,6) != 'image/') {
        $row.append('<td colspan="3"></td>');
      } else if (entry['local'] && entry['remote']) {
        $row.append('<td class="link QUICKVIEW_LOCAL"><a href="#" title="Quick view local">&lt;Q</a></td>');
        if (entry['action'] != 'IDENTICAL' && mimetype.substring(0,5) == 'text/') {
          $row.append('<td class="link MERGE"><a href="#" title="Compare/Merge">M</a></td>');
        } else {
          $row.append('<td class="nolink"></td>');
        }
        $row.append('<td class="link QUICKVIEW_REMOTE"><a href="#" title="Quick view remote">Q&gt;</a></td>');
      } else if (entry['local']) {
        $row.append('<td class="link QUICKVIEW_LOCAL"><a href="#" title="Quick view local">&lt;Q</a></td>');
        $row.append('<td colspan="2"></td>');
      } else {
        $row.append('<td colspan="2"></td>');
        $row.append('<td class="link QUICKVIEW_REMOTE"><a href="#" title="Quick view remote">Q&gt;</a></td>');
      }
      if ($prevRow) $prevRow.after($row); else $tbody.append($row);
      $prevRow = $row;
      if (isdir) {
        $prevRow = displayEntries(dirName, entry['entries']);
        if ($('#entries tr.'+dirId+':not(.IDENTICAL)').size() <= 0) $row.addClass('IDENTICAL');
      } 
    }
    return $prevRow;
  }

  $(function() {
    $('#localrootp').show();
    var localroot = document.SyncApplet.getLocalRoot();
    $('#localroot').text(localroot);
    $('#selectlocalroot').click(function(e) {
      e.preventDefault();
      document.SyncApplet.selectLocalRoot(true);
    });
    
    $('#synchronize').click(function(e) {
      e.preventDefault();
      document.SyncApplet.scan();
    });
    
    // click on action items
    $('#entries').delegate('td.link.ACTION a','click',function(e) {
      e.preventDefault();
      var $td = $(e.target).closest('td');
      var $tr = $(e.target).closest('tr');
      var cl = "";
      if ($td.hasClass('DELETE_LOCAL') && $tr.hasClass('DELETE_REMOTE')) {
        cl = 'DELETE_BOTH';
      } else if ($td.hasClass('DELETE_REMOTE') && $tr.hasClass('DELETE_LOCAL')) {
        cl = 'DELETE_BOTH';
      } else if ($td.hasClass('DELETE_LOCAL')) {
        cl = 'DELETE_LOCAL';
      } else if ($td.hasClass('DELETE_REMOTE')) {
        cl = 'DELETE_REMOTE';
      } else if ($td.hasClass('COPY_TO_LOCAL')) {
        cl = 'COPY_TO_LOCAL';
      } else if ($td.hasClass('COPY_TO_REMOTE')) {
        cl = 'COPY_TO_REMOTE';
      } else if ($td.hasClass('NONE')) {
        cl = 'NONE';
      }
      $tr.removeClass('DELETE_LOCAL COPY_TO_LOCAL NONE COPY_TO_REMOTE DELETE_REMOTE MERGE DELETE_BOTH');
      $tr.addClass(cl);
    });
    
    // click on merge
    $('#entries').delegate('td.link.MERGE a','click',function(e) {
      e.preventDefault();
      var width = $(window).width() * 0.8 - 20;
      var height = $(window).height() * 0.8;
      var path = $(e.target).closest('tr').find('[name^=entry-]').val();
      var mimetype = $(e.target).closest('tr').find('[name^=entrymimetype-]').val();
      $('#merge-path').text(path);
      $('#merge-div').mergely({
        width: width, height: height,
        cmsettings: { lineWrapping: false, mode: mimetype },
        lhs_cmsettings: { readOnly: false },
        rhs_cmsettings: { readOnly: true },
        lhs: function(setValue) {
          setValue("");
        },
        rhs: function(setValue) {
          setValue("");
        }
      });
      var content = document.SyncApplet.getLocalFileContent(path);
      $('#merge-div').mergely('lhs', content);
      var remotePath = 'rest.php/<?php echo urlencode($tagname ? urlencode($tagname) : 'current'); ?><?php echo $dir ? '/'.substr($dir,0,-1) : ''; ?>/'+escape(path);
      $.ajax({
        type: 'GET', async: true, dataType: 'text',
        url: remotePath,
        success: function (response) {
          $('#merge-div').mergely('rhs', response);
        },
        error: function(e) { 
          alert(e);
        }
      });
      $('#merge-save').attr('disabled', 'disabled');
      $('#merge-div').mergely('cm', 'lhs').on('change', function(instance, changeObj) {
        $('#merge-save').removeAttr('disabled');
      });
      $('#merge-dialog').dialog();
    });
    $('#merge-save').click(function(e) {
      e.preventDefault();
      var path = $('#merge-path').text();
      var content = $('#merge-div').mergely('get','lhs');
      var ok = document.SyncApplet.setLocalFileContent(path, content);
      if (ok) {
        $('#merge-save').attr('disabled', 'disabled');
      } else {
      	alert('The file could not be saved!');
      }
    });
    $('#merge-dialog .close').click(function(e) {
      e.preventDefault();
      $(e.target).closest('.dialog').dialog('close');
    });

    // quick view local/remote
    var cm = null;
    $('#entries').delegate('td.link.QUICKVIEW_LOCAL a','click',function(e) {
      e.preventDefault();
      var width = $(window).width() * 0.8 - 20;
      var height = $(window).height() * 0.8;
      var path = $(e.target).closest('tr').find('[name^=entry-]').val();
      var mimetype = $(e.target).closest('tr').find('[name^=entrymimetype-]').val();
      $('#quickview-local').show();
      $('#quickview-remote').hide();
      $('#quickview-path').text(path);
      if (mimetype.substring(0,5) == 'text/' || mimetype == 'application/x-httpd-php') {
        var content = document.SyncApplet.getLocalFileContent(path);
        $('#quickview-text').show().width(width).height(height).text(content);
        $('#quickview-image').hide();
        $('#quickview-dialog').dialog();
        cm = CodeMirror.fromTextArea($('#quickview-text').get(0), { mode:mimetype, readOnly:true, lineNumbers:true });
        cm.setSize(width, height);
        cm.getDoc().setValue(content);
      } else if (mimetype.substring(0,6) == 'image/') {
        var content = document.SyncApplet.getLocalFileContentBase64(path);
        $('#quickview-image').closest('div').width(width).height(height);
        $('#quickview-text').hide();
        $('#quickview-dialog').dialog();
        $('#quickview-image').closest('div').width('auto').height('auto');
        $('#quickview-image').css('max-width',width).css('max-height',height).attr('src','data:'+mimetype+';base64,'+content).show();
      }
    });
    $('#entries').delegate('td.link.QUICKVIEW_REMOTE a','click',function(e) {
      e.preventDefault();
      var width = $(window).width() * 0.8 - 20;
      var height = $(window).height() * 0.8;
      var path = $(e.target).closest('tr').find('[name^=entry-]').val();
      var mimetype = $(e.target).closest('tr').find('[name^=entrymimetype-]').val();
      $('#quickview-local').hide();
      $('#quickview-remote').show();
      $('#quickview-path').text(path);
      var remotePath = 'rest.php/<?php echo urlencode($tagname ? urlencode($tagname) : 'current'); ?><?php echo urlencode($dir ? '/'.substr($dir,0,-1) : ''); ?>/'+escape(path);
      if (mimetype.substring(0,5) == 'text/' || mimetype == 'application/x-httpd-php') {
        $.ajax({
          type: 'GET', async: true, dataType: 'text',
          url: remotePath,
          success: function (content) {
            $('#quickview-text').show().width(width).height(height).text(content);
            $('#quickview-image').attr('src','').hide();
            $('#quickview-dialog').dialog();
            cm = CodeMirror.fromTextArea($('#quickview-text').get(0), { mode:mimetype, readOnly:true, lineNumbers:true });
            cm.setSize(width, height);
            cm.getDoc().setValue(content);
          },
          error: function(e) { 
            alert(e);
          }
        });
      } else if (mimetype.substring(0,6) == 'image/') {
        $('#quickview-image').closest('div').width(width).height(height);
        $('#quickview-text').hide();
        $('#quickview-dialog').dialog();
        $('#quickview-image').closest('div').width('auto').height('auto');
        $('#quickview-image').css('max-width',width).css('max-height',height).attr('src',remotePath).show();
      }
    });
    $('#quickview-dialog .close').click(function(e) {
      e.preventDefault();
      $(e.target).closest('.dialog').dialog('close');
      if (cm != null) cm.toTextArea($('#quickview-text').get(0));
    });
    
    // open/close directories
    $('#entries').delegate('tr.directory td.name img.open, tr.directory td.name img.closed','click',function(e) {
      e.preventDefault();
      var $tr = $(e.target).closest('tr');
      var id = $tr.attr('id');
      if ($tr.hasClass('open')) {
        $('#entries tr.'+id).addClass('hidden');
        $('#entries tr.open.'+id).removeClass('open').addClass('closed');
        $tr.removeClass('open').addClass('closed');
      } else {
        $('#entries tr.'+id+'-curr').removeClass('hidden');
        $tr.removeClass('closed').addClass('open');
      }
    });
    
    // open all directories
    $('#open-all').click(function(e) {
      e.preventDefault();
      $('#entries tbody tr').removeClass('hidden');
      $('#entries tbody tr.directory').removeClass('closed').addClass('open');
    });
    
    // close all directories
    $('#close-all').click(function(e) {
      e.preventDefault();
      $('#entries tbody tr:not(.root)').addClass('hidden');
      $('#entries tbody tr.directory').removeClass('open').addClass('closed');
    });
    
    // refresh a directory
    $('#entries').delegate('tr.directory td.name img.refresh','click',function(e) {
      e.preventDefault();
      var dir = $(e.target).closest('td').find('[name^=entry-]').val();
      document.SyncApplet.scan(dir);
    });
    
    // refresh everything
    $('#refresh-all').click(function(e) {
      e.preventDefault();
      document.SyncApplet.scan();
    });
    
    // show/hide identical files
    $('#show-identical').click(function(e) {
      e.preventDefault();
      $(e.target).toggleClass('on');
      $('#entries').toggleClass('hide-identical');
    });

    // start copying/deleting files
    $('#open-start').click(function(e) {
      e.preventDefault();
      $('#start-dialog').dialog();
      $('#sync-progress').hide();
      $('#start-dialog .progress-text').text("");
      $('#start-dialog .progress-bar').css('width','0%');
      $('#start').show();
    });
    $('#start').click(function(e) {
      e.preventDefault();
      var comment = $('#comment').val();
      var actions = [];
      $('#entries tr').each(function(i,tr) {
        var $tr = $(tr);
        var path = $tr.find('[name^=entry-]').val();
        if ($tr.hasClass('DELETE_LOCAL')) {
          actions[actions.length] = { action:'DELETE_LOCAL', path:path, size:100 }; // dummy size
        } else if ($tr.hasClass('COPY_TO_LOCAL')) {
          actions[actions.length] = { action:'COPY_TO_LOCAL', path:path, size:parseInt($tr.find('[name^=entrysize-remote-]').val()) };
        } else if ($tr.hasClass('COPY_TO_REMOTE')) {
          actions[actions.length] = { action:'COPY_TO_REMOTE', path:path, size:parseInt($tr.find('[name^=entrysize-local-]').val()) };
        } else if ($tr.hasClass('DELETE_REMOTE')) {
          actions[actions.length] = { action:'DELETE_REMOTE', path:path, size:100 }; // dummy size
        }
      });
      document.SyncApplet.sync($.toJSON(actions), comment);
      $('#start').hide();
      $('#sync-progress').show();
    });
    $('#start-dialog .close').click(function(e) {
      e.preventDefault();
      $(e.target).closest('.dialog').dialog('close');
    })
  });
  // ]]>
</script>

<?php
  template_footer();