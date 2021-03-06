<?php
if(!isset($modulekit)) {
  require_once("AdvExec.php");
}

$adv_exec_chroot_cmds = array(
  'mount'	=> array(
    'prepare_cmd'	=> 'M',
    'force_dir'		=> true,
  ),
  'mount-ro'	=> array(
    'prepare_cmd'	=> 'm',
    'force_dir'		=> true,
  ),
  'sync'	=> array(
    'prepare_cmd'	=> 'R',
    'force_dir'		=> false,
  ),
  'copy'	=> array(
    'prepare_cmd'	=> 'C',
    'force_dir'		=> false,
  ),
);

function chroot_src_dest($src, $dest, $force_dir=false) {
  if(is_numeric($src))
    $src = $dest;

  $x = realpath($src);
  if($x === false) {
    print "Warning: Source {$src} does not exist.\n";
    return null;
  }
  $src = $x;

  $x = realpath($dest);
  if($x)
    $dest = $x;

  if($force_dir && !is_dir($src)) {
    print "Warning: Source {$src} is not a directory.\n";
    return null;
  }

  if(is_dir($src)) {
    $src .= "/";
    $dest .= "/";
  }

  return array($src, $dest);
}

class AdvExecChroot extends AdvExec {
  function __construct($env=null, $options=array())  {
    parent::__construct($env, $options);

    $this->chroot = "/tmp/" . uniqid();
    if(!mkdir($this->chroot)) {
      throw new Exception("AdvExecChroot: Can't create temporary directory");
    }

    $prepare_fd_desc_spec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "w"),
    );

    if(!is_executable(dirname(__FILE__)."/prepare_chroot") ||
       !is_executable(dirname(__FILE__)."/run_chroot")) {
      throw new Exception("AdvExecChroot: Can't create prepare process - prepare_chroot and/or run_chroot do not exist");
    }

    $this->prepare_proc = proc_open(dirname(__FILE__)."/prepare_chroot {$this->chroot}", $prepare_fd_desc_spec, $this->prepare_pipes);
    if(!is_resource($this->prepare_proc)) {
      throw new Exception("AdvExecChroot: Can't create prepare process");
    }

    global $adv_exec_chroot_cmds;
    foreach($adv_exec_chroot_cmds as $cmd=>$cmd_def) {
      if(array_key_exists($cmd, $this->options)) {
	foreach($this->options[$cmd] as $src=>$dest) {
	  $paths = chroot_src_dest($src, $dest, $cmd_def['force_dir']);
	  if($paths) {
	    list($src, $dest) = $paths;
	    $this->prepare($cmd_def['prepare_cmd'], array("{$src}", "{$dest}"));
	  }
	}
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

      if($file && !file_exists("{$this->chroot}/{$file}")) {
	$this->prepare("c", array($file, $file));
      }
    }
    pclose($f);

    // also copy the command itself and set executable flag
    if(!file_exists("{$this->chroot}/{$cmd}")) {
      $this->prepare("C", array($cmd, $cmd));
    }
  }

  function _exec_prepare($cmd, $cwd) {
    $cmd = parent::_exec_prepare($cmd, $cwd);

    if(!$cmd)
      return null;

    // make sure to copy all necessary libs to chroot env
    $cmd_parts = explode(" ", $cmd);
    // if command has no absolute path, try to find executable
    if(substr($cmd_parts[0], 0, 1) != '/') {
      $p = popen("which '{$cmd_parts[0]}'", "r");
      $cmd_parts[0] = trim(fgets($p));
      pclose($p);
    }

    // replace executable by real path
    $cmd_parts[0] = realpath($cmd_parts[0]);
    $cmd = implode(" ", $cmd_parts);

    // now copy necessary libs for executable
    $this->chroot_copy_libs($cmd_parts[0]);

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

    fwrite($this->prepare_pipes[0], "Q\n");
    while($r = fgets($this->prepare_pipes[1])) {
      if(preg_match("/^DONE(.*)/", $r, $m)) {
	break;
      }
      else
	print $r;
    }

    fclose($this->prepare_pipes[0]);
    fclose($this->prepare_pipes[1]);
    fclose($this->prepare_pipes[2]);
    proc_close($this->prepare_proc);
  }
}
