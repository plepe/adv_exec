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

  function _exec_prepare($cmd, $cwd) {
    $cmd=implode("\n", explode("\r\n", $cmd));

    return $cmd;
  }

  function exec($cmd, $cwd=null) {
    $descriptors=array(
      1=>array("pipe", "w"),
      2=>array("pipe", "w"),
    );

    if($cwd === null)
      $cwd = getcwd();

    $cmd=$this->_exec_prepare($cmd, $cwd);
    if(!$cmd)
      return false;

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

      stream_select($streams['read'], $streams['write'], $streams['except'], $time_left_till_terminate);

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
  }
}


