<?php
// Local test endpoint: selected runtime facts only, no phpinfo or environment dump.
$extensions = [];
foreach (['session', 'json', 'mbstring', 'fileinfo', 'PDO', 'pdo_sqlite', 'sqlite3', 'zip', 'zlib', 'openssl', 'curl'] as $name) {
    $extensions[$name] = extension_loaded($name) ? phpversion($name) : false;
}
$ini = [];
foreach (['error_reporting', 'display_errors', 'log_errors', 'error_log', 'default_charset', 'date.timezone', 'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'allow_url_fopen', 'disable_functions', 'open_basedir', 'opcache.enable'] as $name) {
    $ini[$name] = ini_get($name);
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os_family' => PHP_OS_FAMILY,
    'integer_bytes' => PHP_INT_SIZE,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'extensions' => $extensions,
    'ini' => $ini,
    'functions' => [
        'exec' => function_exists('exec'),
        'get_magic_quotes_gpc' => function_exists('get_magic_quotes_gpc'),
        'sqlite_query' => function_exists('sqlite_query'),
    ],
    'ZipArchive' => class_exists('ZipArchive'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
