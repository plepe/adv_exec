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

    $prepare_fd_desc_spec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "w"),
    );

    $this->prepare_proc = proc_open(dirname(__FILE__)."/prepare_chroot {$this->chroot}", $prepare_fd_desc_spec, $this->prepare_pipes);
    if(!is_resource($this->prepare_proc)) {
      print "AdvExecChroot: Can't create prepare process\n";
      exit(1);
    }

    if(array_key_exists('copy', $this->options)) {
      foreach($this->options['copy'] as $src=>$dest) {
	list($src, $dest) = chroot_src_dest($src, $dest);
	$this->prepare("C", array("{$src}/", "{$dest}/"));
      }
    }

    if(array_key_exists('sync', $this->options)) {
      foreach($this->options['sync'] as $src=>$dest) {
	list($src, $dest) = chroot_src_dest($src, $dest);
	$this->prepare("R", array("{$src}/", "{$dest}/"));
      }
    }

    if(array_key_exists('mount', $this->options)) {
      foreach($this->options['mount'] as $src=>$dest) {
	list($src, $dest) = chroot_src_dest($src, $dest);
	$this->prepare("M", array("{$src}/", "{$dest}/"));
      }
    }
  }

  function prepare($cmd, $param=array()) {
    $s = $cmd . implode("\t", $param) . "\n";
    fwrite($this->prepare_pipes[0], $s);

    while($r = fgets($this->prepare_pipes[1])) {
      if(preg_match("/^DONE(.*)/", $r, $m)) {
	return $m[1];
      }
    }

    return -1;
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
	$this->prepare("c", array($file, $file));
      }
    }
    pclose($f);

    // also copy the command itself and set executable flag
    $this->prepare("C", array($cmd, $cmd));
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

    fclose($this->prepare_pipes[0]);
    fclose($this->prepare_pipes[1]);
    fclose($this->prepare_pipes[2]);
    proc_close($this->prepare_proc);
  }
}
