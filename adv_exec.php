<?php
if(!isset($modulekit)) {
  require_once("AdvExec.php");
}

// like system(), but returns:
// array(result, "stdout", "stderr");
function adv_exec($cmd, $cwd=null, $env=null, $options=array()) {
  $x = new AdvExec($env, $options);
  return $x->exec($cmd, $cwd);
}
