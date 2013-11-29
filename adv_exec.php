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

  while((!$done)&&($timeout>0)) {
    $streams=$orig_streams;
    $t=time();
    stream_select(&$streams['read'], &$streams['write'], &$streams['except'], $timeout);
    $timeout-=(time()-$t);

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

  $ret[0]=proc_close($proc);

  if($timeout<=0)
    $ret[0]="timeout";

  return $ret;
}
