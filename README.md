Like system() but with advanced options. Can be used either as class or function

== Class ==
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

=== Available Methods ===
| Method | Parameters | Return value | Description
|--------|------------|--------------|-------------
| exec | cmd, [cwd] | array(result, stdout, stderr) | Execute a command in the specified environment; result is exitcode or 'timeout' when execution needed to be terminated
| set_option | key, value | null | Change option to the new value; see below for a list of options

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

== Options ==
The following options are available:
* timeout: when timeout is exceeded terminate process
