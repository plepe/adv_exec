<?
if(!isset($modulekit)) {
  require_once("AdvExec.php");
}

function chroot_src_dest($src, $dest) {
  if(is_numeric($src))
    $src = $dest;

  $src = realpath($src);
  $x = realpath($dest);
  if($x)
    $dest = $x;

  return array($src, $dest);
}

class AdvExecChroot extends AdvExec {
  function __construct($env=null, $options=array())  {
    parent::__construct($env, $options);

    $this->chroot = "/tmp/" . uniqid();
    mkdir($this->chroot);

    $this->prepare_fd = popen(dirname(__FILE__)."/prepare_chroot {$this->chroot}", "w");

    if(array_key_exists('copy', $this->options)) {
      $this->chroot_copy_dirs($this->options['copy']);
    }
    if(array_key_exists('sync', $this->options)) {
      $this->chroot_copy_dirs($this->options['sync']);
    }
    if(array_key_exists('mount', $this->options)) {
      foreach($this->options['mount'] as $src=>$dest) {
	list($src, $dest) = chroot_src_dest($src, $dest);

	fwrite($this->prepare_fd, "M{$src}\t{$dest}\n");
      }
    }
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

  function chroot_copy_dirs($list) {
    foreach($list as $src=>$dest) {
      list($src, $dest) = chroot_src_dest($src, $dest);

      @mkdir(dirname("{$this->chroot}/{$dest}"), 0777, true);
      chmod("{$this->chroot}/{$dest}", 0700);
      system("rsync -a {$src}/ {$this->chroot}/{$dest}/");
    }
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

    if(array_key_exists('executables', $this->options)) {
      foreach($this->options['executables'] as $additional_executable) {
	$this->chroot_copy_libs($additional_executable);
      }
    }

    // if it doesn't exist, create the current working directory
    @mkdir("{$this->chroot}/{$cwd}", 0777, true);
    chmod("{$this->chroot}/{$cwd}", 0700);

    // call command via chroot wrapper
    $cmd = dirname(__FILE__)."/run_chroot {$this->chroot} {$cwd} {$cmd}";

    return $cmd;
  }

  function __destruct() {
    parent::__destruct();

    if(array_key_exists('sync', $this->options)) {
      foreach($this->options['sync'] as $src=>$dest) {
	list($src, $dest) = chroot_src_dest($src, $dest);

	system("rsync -a {$this->chroot}/{$dest}/ {$src}/");
      }
    }

    pclose($this->prepare_fd);

    system("rm -rf {$this->chroot}");
  }
}
