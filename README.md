Like system() but with advanced options

Usage:
```php
adv_exec($cmd, [$cwd], [$env], [$options]);
```

The following options are available:
* timeout: when timeout is exceeded terminate process

Return:
```php
array(result, "stdout", "stderr");
```

result is exitcode or 'timeout' when execution needed to be terminated

