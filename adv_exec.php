<?
// like system(), but returns:
// array(result, "stdout", "stderr");
function adv_exec($cmd, $cwd=null, $env=null, $timeout=60) {
  $descriptors=array(
    1=>array("pipe", "w"),
    2=>array("pipe", "w"),
  );

  $cmd=implode("\n", explode("\r\n", $cmd));
  $proc=proc_open($cmd, $descriptors, $pipes, $cwd, $env);
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

  $timestamp_terminate = time() + $timeout;

  while((!$done)&&($timestamp_terminate > time())) {
    $streams=$orig_streams;
    $time_left_till_terminate = $timestamp_terminate - time();

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

  if($timestamp_terminate <= time()) {
    proc_terminate($proc);
    $ret[0]="timeout";

    proc_close($proc);
  }
  else
    $ret[0]=proc_close($proc);

  return $ret;
}
