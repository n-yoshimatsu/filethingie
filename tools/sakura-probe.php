<?php
// CLI helper: create a short-lived, randomly named Web PHP probe, never phpinfo().
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 3 || !is_dir($argv[1]) || !is_writable($argv[1])
    || !filter_var($argv[2], FILTER_VALIDATE_URL) || parse_url($argv[2], PHP_URL_SCHEME) !== 'https'
    || parse_url($argv[2], PHP_URL_QUERY) !== null || parse_url($argv[2], PHP_URL_FRAGMENT) !== null) {
    fwrite(STDERR, "Usage: php sakura-probe.php EXISTING_PUBLIC_DIRECTORY https://example.com/path/\n");
    exit(1);
}
$name = 'ft-php-check-'.bin2hex(random_bytes(16));
$directory = rtrim($argv[1], '/').'/'.$name;
if (!mkdir($directory, 0755)) { exit(1); }
$path = $directory.'/index.php';
$source = <<<'CODE'
<?php
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (time() > EXPIRES_AT) { http_response_code(404); exit; }
header('Content-Type: application/json; charset=UTF-8');
$extensions = [];
foreach (['session','json','PDO','pdo_sqlite','zip','openssl','curl'] as $name) {
    $extensions[$name] = extension_loaded($name);
}
$limits = [];
foreach (['upload_max_filesize','post_max_size','memory_limit','max_execution_time',
          'display_errors','log_errors','opcache.enable','opcache.validate_timestamps'] as $name) {
    $limits[$name] = ini_get($name);
}
echo json_encode([
    'php' => PHP_VERSION, 'sapi' => PHP_SAPI,
    'os' => PHP_OS, 'os_release' => php_uname('r'),
    'https_flag' => $_SERVER['HTTPS'] ?? '',
    'extensions' => $extensions, 'limits' => $limits,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
CODE;
$source = str_replace('EXPIRES_AT', (string)(time()+1800), $source);
$file = fopen($path, 'xb');
if ($file === false) { exit(1); }
$ok = fwrite($file, $source) === strlen($source);
fclose($file);
if (!$ok) { unlink($path); rmdir($directory); exit(1); }
chmod($path, 0644);
echo 'Browser URL (30 minutes): ', rtrim($argv[2], '/'), '/', $name, '/index.php', "\n";
echo 'After checking: rm -- ', escapeshellarg($path), ' && rmdir -- ', escapeshellarg($directory), "\n";
