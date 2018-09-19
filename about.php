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

  template_header();
?>
<h2>关于</h2>

<p><b>phpEasyVCS</b> was created by Martin Vlcek (c) and is licensed under the 
  <a href="http://www.gnu.org/licenses/gpl-3.0.html">GPL 3.0</a>.</p>

<p>It was created by including the following components:</p>

<ul>
  <li>The basic WebDAV functionality uses the 
    <a href="http://pear.php.net">PEAR</a>
    <a href="http://pear.php.net/package/HTTP_WebDAV_Server">HTTP_WebDAV_Server</a>
    (<a href="http://www.opensource.org/licenses/bsd-license.php">BSD License</a>).</li>
  <li>The difference view determines the differences between two file using the
    <a href="http://pear.php.net">PEAR</a>
    <a href="http://pear.php.net/package/Text_Diff">Text_Diff</a> library 
    (<a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a>).</li>
  <li>The file type icons are taken from the GetSimple plugin 
    <a href="http://get-simple.info/extend/plugin/simpledir/254/">SimpleDir</a> by
    <a href="http://ffaat.poweredbyclear.com/">Rob Antonishen</a>.</li>
  <li>Some of the icons are created by 
    <a href="http://p.yusukekamiyamane.com/">Yusuke Kamiyamane</a> 
    (<a href="http://creativecommons.org/licenses/by/3.0/deed">CC BY 3.0</a>).</li>
  <li>Additionally the Javascript libraries
    <a href="http://jquery.org">jQuery</a> 
    (<a href="http://www.gnu.org/licenses/gpl-2.0.html">GPL 2.0</a>) and
    <a href="http://code.google.com/p/google-code-prettify/">Prettify</a>
    (<a href="http://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>)
    are used.</li>
  <li>The pure CSS directory tree is based on an article of 
    <a href="http://www.thecssninja.com/css/css-tree-menu">the CSS Ninja</a>.</li>
</ul>
<?php 
  template_footer();