<?
if(!isset($adv_exec_default_options))
  $adv_exec_default_options = array();

class AdvExec {
  function __construct($env=null, $options=array())  {
    $this->env = $env;
    $this->options = $options;

    // copy default options into options
    global $adv_exec_default_options;
    foreach($adv_exec_default_options as $k=>$v)
      if(!isset($this->options[$k]))
	$this->options[$k] = $v;

    if(array_key_exists('chroot', $this->options) && $this->options['chroot']) {
      $this->chroot = "/tmp/" . uniqid();
      mkdir($this->chroot);
    }
  }

  function set_option($option, $value) {
    $this->options[$option] = $value;
  }

  function chroot_copy_libs($cmd) {
    if(!isset($this->chroot))
      return;

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

  function exec($cmd, $cwd=null) {
    $descriptors=array(
      1=>array("pipe", "w"),
      2=>array("pipe", "w"),
    );

    $cmd=implode("\n", explode("\r\n", $cmd));

    if($this->chroot) {
      // make sure to copy all necessary libs to chroot env
      $c = explode(" ", $cmd);
      $c = $c[0];
      $this->chroot_copy_libs($c);

      // call command via chroot wrapper
      $cmd = dirname(__FILE__)."/run_chroot {$this->chroot} {$cmd}";
    }

    $proc=proc_open($cmd, $descriptors, $pipes, $cwd, $this->env);
    if(!is_resource($proc))
      return false;

    $ret=array(
      "",
      "",
      "",
    );

    $orig_streams=array(
      'read'=>array($pipes[1], $pipes[2]),
      'write'=>array(),
      'except'=>array()
    );
    $done=false;

    $timestamp_terminate = null;
    if (isset($this->options['timeout']))
      $timestamp_terminate = time() + $this->options['timeout'];

    while((!$done) &&
	  ($timestamp_terminate === null || $timestamp_terminate > time())) {
      $streams=$orig_streams;
      $time_left_till_terminate = ($timestamp_terminate === null ? 60 : $timestamp_terminate - time());

      stream_select(&$streams['read'], &$streams['write'], &$streams['except'], $time_left_till_terminate);

      foreach($streams['read'] as $stream) {
	if(feof($stream))
	  $done=true;

	foreach($pipes as $i=>$pipe) {
	  if($stream==$pipe) {
	    $ret[$i].=fgets($pipe);
	  }
	}
      }
    }

    $status = proc_get_status($proc);
    if($status['running'] == true) {
      // from: http://us3.php.net/manual/en/function.proc-terminate.php#81353
      // close pipes by hand
      fclose($pipes[1]); //stdout
      fclose($pipes[2]); //stderr

      // get the parent pid of the process we want to kill
      $ppid = $status['pid'];

      // use ps to get all the children of this process, and kill them
      $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $ppid`);
      foreach($pids as $pid) {
	if(is_numeric($pid)) {
	  posix_kill($pid, 15); // 15 is the SIGTERM signal
	}
      }

      proc_close($proc);
      $ret[0]="timeout";
    }
    else
      $ret[0]=$status['exitcode'];

    return $ret;
  }

  function __destruct() {
    print "destruct called\n";
    if($this->chroot) {
      system("rm -r {$this->chroot}");
    }
  }
}

// like system(), but returns:
// array(result, "stdout", "stderr");
function adv_exec($cmd, $cwd=null, $env=null, $options=array()) {
  $x = new AdvExec($env, $options);
  return $x->exec($cmd, $cwd);
}
