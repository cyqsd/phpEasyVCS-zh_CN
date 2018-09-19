<?php
# +--------------------------------------------------------------------+
# | phpEasyVCS                                                         |
# | The file-based version control system                              |
# +--------------------------------------------------------------------+
# | Copyright (c) 2011 Martin Vlcek                                    |
# | License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)          |
# +--------------------------------------------------------------------+

    require_once "HTTP/WebDAV/Server.php";
    require_once "System.php";
    
    class VCSWebDAVPath {
      
      private $tag = null;
      private $dir = null;
      private $name = null;
      
      public function __construct($path) {
        if (@$path[0] == '/') $path = substr($path,1);
        if (@$path[strlen($path)-1] == '/') $path = substr($path,0,strlen($path)-1);
        $parts = preg_split('@/@',$path);
        $this->tag = count($parts) > 0 ? $parts[0] : '';
        $this->name = count($parts) > 1 ? $parts[count($parts)-1] : '';
        $this->dir = count($parts) > 2 ? implode('/',array_slice($parts,1,count($parts)-2)).'/' : '';
      }
      
      public function __get($name) {
        switch ($name) {
          case 'tag': return $this->tag;
          case 'dir': return $this->dir;
          case 'name': return $this->name;
        }
        return null;
      }
      
    }
    
    /**
     * Filesystem access using WebDAV
     *
     * @access public
     */
    class VCSWebDAVServer extends HTTP_WebDAV_Server 
    {
        protected $tmp;
        protected $vcs;
        
        public function __construct($vcs, $tmp='/tmp') {
          $this->tmp = $tmp;
          $this->vcs = $vcs;
		      parent::__construct();
        }


        /**
         * Serve a webdav request
         *
         * @access public
         * @param  string  
         */
        function ServeRequest() 
        {
            // special treatment for litmus compliance test
            // reply on its identifier header
            // not needed for the test itself but eases debugging
            if (function_exists("apache_request_headers")) {
      				foreach(apache_request_headers() as $key => $value) {
      					if (stristr($key,"litmus")) {
      						error_log("Litmus test $value");
      						header("X-Litmus-reply: ".$value);
      					}
      				}
      			}

            // let the base class do all the work
            parent::ServeRequest();
        }
        
        /**
         * No authentication is needed here
         *
         * @access private
         * @param  string  HTTP Authentication type (Basic, Digest, ...)
         * @param  string  Username
         * @param  string  Password
         * @return bool    true on successful authentication
         */
        function checkAuth($type, $user, $pass) 
        {
            // TODO
            return true;
        }


        /**
         * PROPFIND method handler
         *
         * @param  array  general parameter passing array
         * @param  array  return array for file properties
         * @return bool   true on success
         */
        function PROPFIND(&$options, &$files) 
        {
            $path = new VCSWebDAVPath($options["path"]);
            $this->vcs->setTag($path->tag);
            $depth = 0;
            if (@$options["depth"]) $depth = is_numeric($options["depth"]) ? (int) $options["depth"] : 99;
            
            $files["files"] = array();
            if (!$path->tag) {
              $this->rootinfo($files, $depth);
            } else if (!$path->name) {
              $this->taginfo($files, $depth);
            } else {
              $entry = $this->vcs->getEntry($path->dir, $path->name);
              if ($entry && $entry->isDirectory) {
                $this->dirinfo($files, $entry, $depth);
              } else if ($entry && $entry->isFile) {
                $this->fileinfo($files, $entry);
              } else {
                return false;
              }
            }
            return true;
        } 
        
        private function rootinfo(&$files, $depth=0) {

            $info = array();
            $info["path"]  = '/'; 
            $info["props"] = array();
            
            $root = $this->vcs->getRoot();
            $info["props"][] = $this->mkprop("displayname",     '/');
            $info["props"][] = $this->mkprop("creationdate",    $root->creationdate);
            $info["props"][] = $this->mkprop("getlastmodified", $root->date);
            $info["props"][] = $this->mkprop("resourcetype",    "collection");
            $info["props"][] = $this->mkprop("getcontenttype",  "httpd/unix-directory");    
            # Microsoft:
      			$info["props"][] = $this->mkprop("lastaccessed",    $root->date);
      			$info["props"][] = $this->mkprop("ishidden",        false);
            $files["files"][] = $info;        

            if ($depth) {
              $this->vcs->setTag(null);
              $this->taginfo($files, $depth-1);
              foreach ($this->vcs->getTags() as $tag) {
                $this->vcs->setTag($tag->name);
                $this->taginfo($files, $depth-1);
              }
            }
        }
        
        private function taginfo(&$files, $depth=0) {

            $tag = $this->vcs->getTag();

            $info = array();
            $info["path"]  = '/'.($tag ? $tag->name : 'current').'/'; 
            $info["props"] = array();
            
            $tags = $this->vcs->getTags();
            $root = $this->vcs->getRoot();
            $info["props"][] = $this->mkprop("displayname",     $tag ? $tag->name : 'current');
            $info["props"][] = $this->mkprop("creationdate",    $root->creationdate);
            $info["props"][] = $this->mkprop("getlastmodified", $tag ? $tag->date : $root->date);
            $info["props"][] = $this->mkprop("resourcetype",    "collection");
            $info["props"][] = $this->mkprop("getcontenttype",  "httpd/unix-directory");   
            # Microsoft: 
      			$info["props"][] = $this->mkprop("lastaccessed",    $tag ? $tag->date : $root->date);
      			$info["props"][] = $this->mkprop("ishidden",        false);
            $files["files"][] = $info;        

            if ($depth) {
              $listing = $this->vcs->getListing('');
              foreach ($listing->directories as $directory) {
                $this->dirinfo($files, $directory, $depth-1);
              }
              foreach ($listing->files as $file) {
                $this->fileinfo($files, $file);
              }
            }
        }
        
        private function dirinfo(&$files, $directory, $depth=0) {

            $tag = ($this->vcs->getTag() ? $this->vcs->getTag()->name : 'current');
            $info = array();
            $info["path"]  = $this->_slashify('/'.$tag.'/'.$directory->dir.$directory->name); 
            $info["props"] = array();
            
            $info["props"][] = $this->mkprop("displayname",     $directory->name);
            $info["props"][] = $this->mkprop("creationdate",    $directory->creationdate);
            $info["props"][] = $this->mkprop("getlastmodified", $directory->date);
            $info["props"][] = $this->mkprop("resourcetype",    "collection");
            $info["props"][] = $this->mkprop("getcontenttype",  "httpd/unix-directory"); 
            # Microsoft:
      			$info["props"][] = $this->mkprop("lastaccessed",    $directory->date);
      			$info["props"][] = $this->mkprop("ishidden",        false);
            $this->optdirinfo($info["props"]);
            $files["files"][] = $info;        

            if ($depth) {
              $listing = $this->vcs->getListing($directory->dir.$directory->name);
              foreach ($listing->directories as $directory) {
                $this->dirinfo($files, $directory, $depth-1);
              }
              foreach ($listing->files as $file) {
                $this->fileinfo($files, $file);
              }
            }
        }
        
        protected function optdirinfo(&$props) {
          // no optional file info available here
        }
        
        private function fileinfo(&$files, $file) {

            $tag = ($this->vcs->getTag() ? $this->vcs->getTag()->name : 'current');
            $info = array();
            $info["path"]  = '/'.$tag.'/'.$file->dir.$file->name; 
            $info["props"] = array();
            
            $info["props"][] = $this->mkprop("displayname",     $file->name);
            $info["props"][] = $this->mkprop("creationdate",    $file->creationdate);
            $info["props"][] = $this->mkprop("getlastmodified", $file->date);
            $info["props"][] = $this->mkprop("resourcetype",    "");
            $info["props"][] = $this->mkprop("getcontenttype",  $file->mimetype);
            $info["props"][] = $this->mkprop("getcontentlength",$file->size);
            # Microsoft:
      			$info["props"][] = $this->mkprop("lastaccessed",    $file->date);
      			$info["props"][] = $this->mkprop("ishidden",        false);
            $this->optfileinfo($info["props"]);
            $files["files"][] = $info;        
        }
     
        protected function optfileinfo(&$props) {
          // no optional file info available here
        }
        
        /**
         * GET method handler
         * 
         * @param  array  parameter passing array
         * @return bool   true on success
         */
        function GET(&$options) 
        {
            $path = new VCSWebDAVPath($options["path"]);
            $this->vcs->setTag($path->tag);
            $entry = $this->vcs->getEntry($path->dir, $path->name);
            
            if (!$path->name || ($entry && $entry->isDirectory)) {
              
              $p = $this->_slashify($options["path"]);
              if ($p != $options["path"]) {
                  header("Location: ".$this->base_uri.$p);
                  exit;
              }
              
              $listing = $path->tag ? $this->vcs->getListing($path->dir.$path->name) : null;
              return $this->GetDir($listing);
              
            } else if ($entry && $entry->isFile) {
              // detect resource type
              $options['mimetype'] = $entry->mimetype; 
                  
              // detect modification time
              // see rfc2518, section 13.7
              // some clients seem to treat this as a reverse rule
              // requiering a Last-Modified header if the getlastmodified header was set
              $options['mtime'] = $entry->date;
              
              // detect resource size
              $options['size'] = $entry->size;
              
              // no need to check result here, it is handled by the base class
              $options['stream'] = $entry->stream;
              
              return true;
              
            }
            return false;
        }

        /**
         * GET method handler for directories
         *
         * This is a very simple mod_index lookalike.
         * See RFC 2518, Section 8.4 on GET/HEAD for collections
         *
         * @param  string  directory path
         * @return void    function has to handle HTTP response itself
         */
        function GetDir($listing) 
        {
            
            // fixed width directory column format
            $format = "%15s  %-19s  %-s\n";
            
            if ($listing) {
              $tag = ($this->vcs->getTag() ? $this->vcs->getTag()->name : 'current');
  
              echo "<html><head><title>Index of ".htmlspecialchars('/'.$tag.'/'.$listing->dir)."</title></head>\n";
              echo "<h1>Index of ".htmlspecialchars('/'.$tag.'/'.$listing->dir)."</h1>\n";
              echo "<pre>";
              printf($format, "Size", "Last modified", "Filename");
              echo "<hr>";
              foreach ($listing->directories as $directory) {
                $link = '<a href="'.htmlspecialchars($this->base_uri.'/'.$tag.'/'.$directory->dir.$directory->name.'/').'">'.htmlspecialchars($directory->name).'</a>';
                printf($format, '--', strftime("%Y-%m-%d %H:%M:%S", $directory->date), $link);
              }
              foreach ($listing->files as $file) {
                $link = '<a href="'.htmlspecialchars($this->base_uri.'/'.$tag.'/'.$file->dir.$file->name).'">'.htmlspecialchars($file->name).'</a>';
                printf($format, number_format($file->size), strftime("%Y-%m-%d %H:%M:%S", $file->date), $link);
              }
              echo "</pre>";
              echo "</html>\n";
              
            } else {
              
              echo "<html><head><title>Index of /</title></head>\n";
              echo "<h1>Index of /</h1>\n";
              echo "<pre>";
              printf($format, "Size", "Last modified", "Filename");
              echo "<hr>";
              $link = '<a href="'.htmlspecialchars($this->base_uri.'/current/').'">current</a>';
              printf($format, '--', strftime("%Y-%m-%d %H:%M:%S", $this->vcs->getRoot()->date), $link);
              foreach ($this->vcs->getTags() as $tag) {
                $link = '<a href="'.htmlspecialchars($this->base_uri.'/'.$tag->name.'/').'">'.htmlspecialchars($tag->name).'</a>';
                printf($format, '--', strftime("%Y-%m-%d %H:%M:%S", $tag->date), $link);
              }
              echo "</pre>";
              echo "</html>\n";

            }

            exit;
        }

        /**
         * PUT method handler
         * 
         * @param  array  parameter passing array
         * @return bool   true on success
         */
        function PUT(&$options) 
        {
            if (!empty($options["ranges"])) {
                # not supported
                return "500 Server Error: Ranges not supported";
            } else {
                $tmpname = tempnam($this->tmp, 'vcs');
                $fp = fopen($tmpname, "w");
                while (!feof($options["stream"])) {
                    if (false === fwrite($fp, fread($options["stream"], 4096))) {
                        return "403 Forbidden"; 
                    }
                }
                fclose($fp);
            }
          
            $path = new VCSWebDAVPath($options["path"]);
            $this->vcs->setTag($path->tag);
            $result = $this->vcs->addFile($path->dir, $path->name, $tmpname, '', false);
            
            switch ($result) {
              case VCS_ERROR: return "500 Server Error";
              case VCS_NOTFOUND: return "404 Not Found";
              case VCS_FORBIDDEN:
              case VCS_READONLY: return "403 Forbidden";
              case VCS_CONFLICT: return "409 Conflict";
              default: return "201 Created"; // not 200 according to litmus
            }
        }


        /**
         * MKCOL method handler
         *
         * @param  array  general parameter passing array
         * @return bool   true on success
         */
        function MKCOL($options) 
        {           

            $path = new VCSWebDAVPath($options["path"]);
            $this->vcs->setTag($path->tag);
            
            // for compatibility test (litmus)
            $stream = fopen("php://input", "r");
            $body = fread($stream, 4096);
            if ($body !== false && $body !== '') return "415 Unsupported Media Type";
             
            $result = $this->vcs->addDirectory($path->dir, $path->name, '', false); // do not create intermediate directories (litmus)!

            switch ($result) {
              case VCS_ERROR: return "500 Server Error";
              case VCS_NOTFOUND: return "404 Not Found";
              case VCS_CONFLICT: return "409 Conflict";
              case VCS_FORBIDDEN:
              case VCS_READONLY: return "403 Forbidden";
              case VCS_NOACTION: return "405 Method Not Allowed";
              default: "201 Created";
            }
        }
        
        
        /**
         * DELETE method handler
         *
         * @param  array  general parameter passing array
         * @return bool   true on success
         */
        function DELETE($options) 
        {
            $path = new VCSWebDAVPath($options["path"]);
            $this->vcs->setTag($path->tag);
            $result = $this->vcs->delete($path->dir, $path->name);

            switch ($result) {
              case VCS_ERROR: return "500 Server Error";
              case VCS_NOTFOUND: return "404 Not Found";
              case VCS_FORBIDDEN:
              case VCS_READONLY: return "403 Forbidden";
              default: return $result == 1 ? "201 Created" : "204 No Content";
            }
        }


        /**
         * MOVE method handler
         *
         * @param  array  general parameter passing array
         * @return bool   true on success
         */
        function MOVE($options) 
        {
            // TODO Property updates still broken (Litmus should detect this?)

            if (!empty($_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
                return "415 Unsupported media type";
            }

            // no copying to different WebDAV Servers yet
            if (isset($options["dest_url"])) {
                return "502 bad gateway";
            }

            $sourcepath = new VCSWebDAVPath($options["path"]);
            $targetpath = new VCSWebDAVPath($options["dest"]);
            
            $this->vcs->setTag($targetpath->tag);
            if ($this->vcs->isTag()) return "403 Forbidden";
            
            $this->vcs->setTag($sourcepath->tag);
            $result = $this->vcs->move($sourcepath->dir, $sourcepath->name, $targetpath->dir, $targetpath->name, $options['overwrite']);

            switch ($result) {
              case VCS_ERROR: return "500 Server Error";
              case VCS_NOTFOUND: return "404 Not Found";
              case VCS_FORBIDDEN:
              case VCS_READONLY: return "403 Forbidden";
              case VCS_EXISTS: return "412 Precondition Failed";
              case VCS_CONFLICT: return "409 Conflict";
              default: 
                header("Location: ".$this->base_uri."/".$targetpath->dir.$targetpath->name);
                return $result == 1 ? "201 Created" : "204 No Content";
            }
        }

        /**
         * COPY method handler
         *
         * @param  array  general parameter passing array
         * @return bool   true on success
         */
        function COPY($options, $del=false) 
        {
            // TODO Property updates still broken (Litmus should detect this?)

            if (!empty($_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
                return "415 Unsupported media type";
            }

            // no copying to different WebDAV Servers yet
            if (isset($options["dest_url"])) {
                return "502 bad gateway";
            }

            $sourcepath = new VCSWebDAVPath($options["path"]);
            $targetpath = new VCSWebDAVPath($options["dest"]);
            
            $this->vcs->setTag($targetpath->tag);
            if ($this->vcs->isTag()) return "403 Forbidden";
            
            $this->vcs->setTag($sourcepath->tag);
            $result = $this->vcs->copy($sourcepath->dir, $sourcepath->name, $targetpath->dir, $targetpath->name, $options['overwrite']);

            switch ($result) {
              case VCS_ERROR: return "500 Server Error";
              case VCS_NOTFOUND: return "404 Not Found";
              case VCS_FORBIDDEN:
              case VCS_READONLY: return "403 Forbidden";
              case VCS_EXISTS: return "412 Precondition Failed";
              case VCS_CONFLICT: return "409 Conflict";
              default: return "201 Created";
            }
        }

        /**
         * PROPPATCH method handler
         *
         * @param  array  general parameter passing array
         * @return bool   true on success
         */
        function PROPPATCH(&$options) 
        {
            foreach ($options["props"] as $key => $prop) {
                //if ($prop["ns"] == "DAV:") {
                    $options["props"][$key]['status'] = "403 Forbidden"; // TODO: not used in http_PROPPATCH?
                //}
            }
            return "403 Forbidden";
        }

   
    }


    class VCSWebDAVServerWithLockSupport extends VCSWebDAVServer {
      
      private $locksupport;

      public function __construct($vcs, $locksupport, $tmp='/tmp') {
        parent::__construct($vcs, $tmp);
        $this->locksupport = $locksupport;
      }
      
      # TODO: According to the standard we should add null resources (no file, but path is locked)
      # to the files returned by PROPINFO - but as long as no one complains...

      protected function optdirinfo(&$props) {
        $props[] = $this->mkprop("supportedlock", array("exclusive write")); 
      }

      protected function optfileinfo(&$props) {
        $props[] = $this->mkprop("supportedlock", array("exclusive write")); 
      }

      public function checkLock($path) {
        $path = new VCSWebDAVPath($path);
        $lock = $this->locksupport->getLock($path->dir.$path->name);
        if (!$lock) return null;
        $props = array();
        $props['token'] = $lock->token;
        $props['scope'] = 'exclusive';
        $props['expires'] = $lock->timeout;
        $props['type'] = 'write';
        $props['owner'] = $lock->owner;
        $props['depth'] = $lock->recursive ? "infinity" : 0;
        return $props;
      }
      
      
      public function LOCK(&$options) {
        
        if (strtolower($options['scope']) != 'exclusive') return "412 Precondition Failed";
        if (strtolower($options['type']) != 'write') return "412 Precondition Failed";
        
        $path = new VCSWebDAVPath($options['path']);
        if ($path->tag && $path->tag != 'current') return "412 Precondition Failed";
        
        $timeout = 365*24*3600;
        foreach ($options['timeout'] as $t) {
          if (substr(strtolower($t),0,7) == 'second-' && (int) substr($t,7) < $timeout) $timeout = (int) substr($t,7);
        }
        
        if (@$options['update']) {
          $result = $this->locksupport->updateLock($options['update'], $path->dir.$path->name, time()+$timeout);
        } else {
          $recursive = $options['depth'] != '0';
          $result = $this->locksupport->addLock($options['locktoken'], $path->dir.$path->name, $recursive, time()+$timeout, $options['owner']);
        }

        if ($result) {
          $options['timeout'] = $this->locksupport->getLock($path->dir.$path->name)->timeout;
          $entry = $this->vcs->getEntry($path->dir,$path->name);
          return $entry == null ? "201 Created" : "200 OK";
        } else {
          return "423 Locked";
        }
        
      }
      
      
      public function UNLOCK(&$options) {
        
        $path = new VCSWebDAVPath($options['path']);
        if ($path->tag && $path->tag != 'current') return "412 Precondition Failed";
        
        $result = $this->locksupport->removeLock($options['token'], $path->dir.$path->name);
        return $result ? true : "423 Locked";
        
      }
      
    }