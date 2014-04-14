<?php
// runs the specified command in a chrooted jail
class chroot_jail {
  function __construct() {
    $this->path = "/tmp/" . uniqid();
    mkdir($this->path);
  }

  function run($cmd, $parameters) {
    $this->copy_libs($cmd);
    system(dirname(__FILE__)."/run_chroot {$this->path} $cmd $parameters");
  }

  function copy_libs($cmd) {
    $f = popen("ldd {$cmd}", "r");
    while($r = fgets($f)) {
      $file = null;

      if(preg_match("/^\t[^ ]+ => ([^ ]+) \(/", $r, $m))
	$file = $m[1];
      elseif(preg_match("/^\t([^ ]+) \(/", $r, $m))
	$file = $m[1];

      if($file) {
	@mkdir(dirname("{$this->path}/{$file}"), 0777, true);
	copy($file, "{$this->path}/{$file}");
	chmod("{$this->path}/{$file}", 0700);
      }
    }
    pclose($f);

    // also copy the command itself and set executable flag
    @mkdir(dirname("{$this->path}/{$cmd}"), 0777, true);
    copy($cmd, "{$this->path}/{$cmd}");
    chmod("{$this->path}/{$cmd}", 0700);
  }

  function __destruct() {
    print "destruct called\n";
    system("rm -r {$this->path}");
  }
}
