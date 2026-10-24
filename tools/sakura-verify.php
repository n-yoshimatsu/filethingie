<?php
// Read-only checks for the fixed stage-5 preview package. No config execution.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
function stop_verify($message) { fwrite(STDERR, $message."\n"); exit(2); }
if ($argc !== 4) { stop_verify('Usage: php sakura-verify.php PREVIEW_DIRECTORY PRIVATE_PARENT WEB_ROOT'); }
[$app, $parent, $webroot] = array_map('realpath', array_slice($argv, 1));
if (!$app || !$parent || !$webroot || is_link($argv[1]) || is_link($argv[2])
    || !preg_match('/^ft-stage5-\d{8}-\d{6}-[a-f0-9]{8}$/D', basename($app))
    || !str_starts_with($app, $webroot.'/')) { stop_verify('An isolated stage-5 preview inside the web root is required.'); }
$data = $parent.'/.'.basename($app);
if (!is_dir($data) || is_link($data) || realpath($data) !== $data
    || $data === $webroot || str_starts_with($data, $webroot.'/')) { stop_verify('The preview private directory must be outside the web root.'); }
$checks = [];
function check_verify($name, $actual, $expected) {
    global $checks;
    $checks[] = ['name'=>$name, 'actual'=>$actual, 'expected'=>$expected, 'verdict'=>$actual === $expected ? 'PASS' : 'FAIL'];
}
function plain_file($path) { return is_file($path) && !is_link($path) && is_readable($path); }
$manifestPath = $data.'/deployment.json';
if (!plain_file($manifestPath) || filesize($manifestPath) > 65536) { stop_verify('Readable preview deployment record required.'); }
try { $manifest = json_decode(file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR); }
catch (Throwable $e) { stop_verify('Invalid deployment record.'); }
$files = $manifest['files'] ?? null;
if (!is_array($files)) { stop_verify('Invalid deployment file list.'); }
ksort($files, SORT_STRING);
if (hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
    !== '4d2054182410328e2fd3be4086e4f2788d12d16bc0d0a738b00baa2bf502aef1') {
    stop_verify('Deployment record does not match the fixed preview package.');
}
check_verify('配備記録のソースコミット', $manifest['source_commit'] ?? null, '3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4');
check_verify('公開・非公開配置の記録一致', ($manifest['public_directory'] ?? null) === $app && ($manifest['private_directory'] ?? null) === $data, true);
$mismatches = [];
foreach ($files as $relative => $expected) {
    $file = $app.'/'.$relative;
    if (!plain_file($file) || realpath($file) !== $file || hash_file('sha256', $file) !== $expected) { $mismatches[] = $relative; }
}
check_verify('配備48ファイルのSHA-256不一致一覧', $mismatches, []);
check_verify('非公開親ディレクトリの権限', sprintf('%04o', fileperms($data) & 07777), '0700');
check_verify('管理ファイル保存先の分離', is_dir($data.'/files') && !is_link($data.'/files') && realpath($data.'/files') === $data.'/files', true);
check_verify('管理ファイル保存先の権限', is_dir($data.'/files') ? sprintf('%04o', fileperms($data.'/files') & 07777) : null, '0700');
foreach (['config.php'=>$app.'/config.php', 'php-error.log'=>$data.'/php-error.log', 'deployment.json'=>$manifestPath] as $role=>$file) {
    check_verify($role.'の通常ファイル確認', plain_file($file), true);
    check_verify($role.'の権限', plain_file($file) ? sprintf('%04o', fileperms($file) & 07777) : null, '0600');
}
$config = plain_file($app.'/config.php') ? file_get_contents($app.'/config.php') : '';
check_verify('設定内の保存先・DB・PHPログの非公開配置',
    str_contains($config, "'DIR' => ".var_export($data.'/files', true))
    && str_contains($config, "'path' => ".var_export($data.'/test.sqlite', true))
    && str_contains($config, "ini_set('error_log', ".var_export($data.'/php-error.log', true).');'), true);
$db = $data.'/test.sqlite';
check_verify('SQLiteの通常ファイル確認', plain_file($db) && realpath($db) === $db, true);
check_verify('SQLiteのヘッダー', plain_file($db) ? file_get_contents($db, false, null, 0, 16) === "SQLite format 3\0" : false, true);
// Access protection relies on the enclosing 0700 directory; no database writes.
$log = $data.'/php-error.log';
$otherLines = null;
$logHash = null;
if (plain_file($log) && filesize($log) <= 8 * 1024 * 1024) {
    $lines = file($log, FILE_IGNORE_NEW_LINES);
    $otherLines = count(array_filter($lines, static fn($line) => trim($line) !== '' && $line !== 'Acceptance setup completed. No application request yet.'));
    $logHash = hash_file('sha256', $log);
}
check_verify('PHPログのセットアップ確認行以外の行数', $otherLines, 0);
$failed = count(array_filter($checks, static fn($c) => $c['verdict'] !== 'PASS'));
echo json_encode([
    'scope'=>'SA-502／SA-503のサーバー内配置・権限・PHPログ確認',
    'checked_at_utc'=>gmdate('c'), 'php'=>PHP_VERSION, 'sapi'=>PHP_SAPI,
    'source_files'=>count($files), 'checks'=>$checks, 'php_log_sha256'=>$logHash,
    'sqlite_mode'=>plain_file($db) ? sprintf('%04o', fileperms($db) & 07777) : null,
    'verdict'=>$failed === 0 ? 'PASS' : 'FAIL',
    'privacy'=>'パスワード・設定本文・DB内容・ログ本文・サーバーのフルパスの非出力',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($failed === 0 ? 0 : 1);
