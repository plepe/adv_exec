#!/usr/bin/php
<?php
include "../AdvExecChroot.php";

function execute($jail, $cmd) {
  print "\nExecuting '$cmd':\n";

  $result = $jail->exec($cmd);
  print $result[1];
  print "Exitcode: {$result[0]}\n";
}

//////////////////////// A simple example ///////////////////
$jail = new AdvExecChroot(
  null, // no overridden ENV
  array(
    "mount"=>array( // these directories will be mounted read-write
      ".", // the current working directory; scripts may write here
    )
));

// The necessary commands (ls) will automatically be copied to the
// chroot jail, including necessary libraries
execute($jail, "ls");
execute($jail, "ls / /lib /usr/lib");
execute($jail, "id");

//////////////////////// A more complex example ///////////////////
$jail = new AdvExecChroot(
  null, // no overridden ENV
  array(
    "executables"=>array("/usr/bin/perl"),
    "mount-ro"=>array( // these directories will be mounted read-only
      "/proc",
      "/usr/lib",
      "/lib",
    ),
    "mount"=>array( // these directories will be mounted read-write
      ".", // the current working directory; scripts may write here
    )
));

// The necessary commands (ls, /..../java) will automatically be copied to the
// chroot jail, including necessary libraries
execute($jail, "./test.pl");
execute($jail, "java HelloWorld");
