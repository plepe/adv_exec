Like system() but with advanced options. Can be used either as class or function

== Class: AdvExec ==
Usage:
```php
$x = new AdvExec([$env], [$options]);
$x->exec($cmd, [$cwd]);
```

Return:
```php
array(result, "stdout", "stderr");
```

result is exitcode or 'timeout' when execution needed to be terminated


=== Options ===
The following options are available:
* timeout: when timeout is exceeded terminate process

=== Available Methods ===
| Method | Parameters | Return value | Description
|--------|------------|--------------|-------------
| exec | cmd, [cwd] | array(result, stdout, stderr) | Execute a command in the specified environment; result is exitcode or 'timeout' when execution needed to be terminated
| set_option | key, value | null | Change option to the new value; see below for a list of options

== Class: AdvExecChroot ==
Like AdvExec, but executes command in a chroot jail which is created as temporary directory. Necessary libs for execution of the command will automatically be copied there.

=== Options ===
All options from AdvExec are available, and additionally:

| Key | Value | Description | Example
|-----|-------|-------------|---------
| executables | array of executables with absolute paths | Copies those executables and their required shared libraries to the chroot jail. | 'commands'=>array('/bin/sh', '/usr/bin/perl')

== Function ==
Usage:
```php
adv_exec($cmd, [$cwd], [$env], [$options]);
```

Return:
```php
array(result, "stdout", "stderr");
```
result is exitcode or 'timeout' when execution needed to be terminated

For options see "Class: AdvExec".
