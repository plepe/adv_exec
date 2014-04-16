<?
include "adv_exec.php";

class AdvExecChroot extends AdvExec {
  function __construct($env=null, $options=array())  {
    parent::__construct($env, $options);

    $this->chroot = "/tmp/" . uniqid();
    mkdir($this->chroot);
  }

  function chroot_copy_libs($cmd) {
    $f = popen("ldd {$cmd}", "r");
    while($r = fgets($f)) {
      $file = null;

      if(preg_match("/^\t[^ ]+ => ([^ ]+) \(/", $r, $m))
	$file = $m[1];
      elseif(preg_match("/^\t([^ ]+) \(/", $r, $m))
	$file = $m[1];

      if($file) {
	@mkdir(dirname("{$this->chroot}/{$file}"), 0777, true);
	copy($file, "{$this->chroot}/{$file}");
	chmod("{$this->chroot}/{$file}", 0700);
      }
    }
    pclose($f);

    // also copy the command itself and set executable flag
    @mkdir(dirname("{$this->chroot}/{$cmd}"), 0777, true);
    copy($cmd, "{$this->chroot}/{$cmd}");
    chmod("{$this->chroot}/{$cmd}", 0700);
  }

  function _exec_prepare($cmd, $cwd) {
    $cmd = parent::_exec_prepare($cmd, $cwd);

    // make sure to copy all necessary libs to chroot env
    $c = explode(" ", $cmd);
    $c = $c[0];
    // if command has no absolute path, try to find executable
    if(substr($c, 0, 1) != '/') {
      $p = popen("which '{$c}'", "r");
      $c = trim(fgets($p));
      pclose($p);
    }
    $this->chroot_copy_libs($c);

    // call command via chroot wrapper
    $cmd = dirname(__FILE__)."/run_chroot {$this->chroot} {$cmd}";

    return $cmd;
  }

  function __destruct() {
    parent::__destruct();

    system("rm -r {$this->chroot}");
  }
}


