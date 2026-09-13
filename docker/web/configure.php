<?php
// Start from the repository's settings, overriding only deployment-specific values.
$root = '/home/judge/src/web/include/';
$config = file_get_contents($root . 'db_info.inc.php.example');
$values = [
    'DB_HOST' => 'db', 'DB_NAME' => 'jol', 'DB_USER' => 'hustoj',
    'DB_PASS' => getenv('DB_PASS'), 'OJ_LANG' => 'cn',
    // Only advertise compilers actually installed in the sandbox image.
    'OJ_LANGMASK' => ((1 << 22) - 1) ^ ((1 << 0) | (1 << 1) | (1 << 2) | (1 << 3) | (1 << 6) | (1 << 13) | (1 << 14)),
    'OJ_MEMSERVER' => 'cache',
];
foreach ($values as $key => $value) {
    $count = 0;
    $config = preg_replace_callback('/static\s+\$' . $key . '\s*=.*?;/',
        fn() => 'static $' . $key . '=' . var_export($value, true) . ';', $config, 1, $count);
    if ($count !== 1) throw new RuntimeException('Missing configuration: ' . $key);
}
if (file_put_contents($root . 'db_info.inc.php', $config) === false) {
    throw new RuntimeException('Cannot write runtime configuration');
}
chmod($root . 'db_info.inc.php', 0640);
chgrp($root . 'db_info.inc.php', 'www-data');
