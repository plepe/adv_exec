Like system() but with advanced options

Usage:
```php
adv_exec($cmd, [$cwd], [$env], [$timeout]);
```
Return:
```php
array(result, "stdout", "stderr");
```

result is exitcode or 'timeout' when execution needed to be terminated

