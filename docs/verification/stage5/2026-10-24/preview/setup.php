<?php
// Disposable acceptance setup only. Never overwrite an existing installation.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function stop_setup($message) { fwrite(STDERR, $message."\n"); exit(1); }
if ($argc !== 5) { stop_setup('Usage: php setup.php PUBLIC_PARENT PRIVATE_PARENT PUBLIC_ROOT HTTPS_BASE_URL'); }
[$public, $private, $webroot] = array_map('realpath', [$argv[1], $argv[2], $argv[3]]);
if (!$public || !$private || !$webroot || !is_writable($public) || !is_writable($private)) { stop_setup('Parent directories must exist and be writable.'); }
if ($public !== $webroot && !str_starts_with($public, $webroot.'/')) { stop_setup('Public parent must be within the declared public root.'); }
if (!filter_var($argv[4], FILTER_VALIDATE_URL) || parse_url($argv[4], PHP_URL_SCHEME) !== 'https'
    || parse_url($argv[4], PHP_URL_QUERY) !== null || parse_url($argv[4], PHP_URL_FRAGMENT) !== null) { stop_setup('An HTTPS base URL without query or fragment is required.'); }
$id = 'ft-stage5-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
$app = $public.'/'.$id;
$data = $private.'/.'.$id;
if ($data === $webroot || str_starts_with($data, $webroot.'/')) { stop_setup('Private files must be outside the public root.'); }
$manifest = json_decode(file_get_contents(__DIR__.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest['files'] as $path => $sha) {
    if (str_starts_with($path, '/') || in_array('..', explode('/', $path), true) || is_link(__DIR__.'/app/'.$path)
        || hash_file('sha256', __DIR__.'/app/'.$path) !== $sha) { stop_setup('Source verification failed: '.$path); }
}
if (!mkdir($app, 0755) || !mkdir($data, 0700) || !mkdir($data.'/files', 0700)) { stop_setup('Could not create fresh directories.'); }
foreach ($manifest['files'] as $path => $sha) {
    $target = $app.'/'.$path;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) { stop_setup('Could not create asset directory.'); }
    $output = fopen($target, 'xb');
    $input = fopen(__DIR__.'/app/'.$path, 'rb');
    if (!$input || !$output || stream_copy_to_stream($input, $output) !== filesize(__DIR__.'/app/'.$path)) { stop_setup('Could not copy source.'); }
    fclose($input); fclose($output); chmod($target, 0644);
}
$password = bin2hex(random_bytes(16));
$config = file_get_contents($app.'/config.sample.php');
$config = str_replace('define("USERNAME", "")', 'define("USERNAME", "stage5")', $config);
$config = str_replace('define("PASSWORD_HASH", "")', 'define("PASSWORD_HASH", '.var_export(password_hash($password, PASSWORD_DEFAULT), true).')', $config);
$settings = [
    'DIR'=>$data.'/files', 'LANG'=>'ja', 'MAXSIZE'=>2000000, 'PERMISSION'=>0644,
    'DIRPERMISSION'=>0755, 'LOGIN'=>true, 'HIDEFILEPATHS'=>true, 'HTTPS'=>true,
    'LIMIT'=>20000000, 'URL_HOSTS'=>'',
];
$plugins = ['search'=>true, 'edit'=>['settings'=>['editlist'=>'txt html htm css','converttabs'=>false]],
    'db'=>['settings'=>['path'=>$data.'/test.sqlite','flushtables'=>false]],
    'log'=>['settings'=>['viewlogs'=>true]], 'zip'=>true];
$settingsCode = '$ft["settings"] = array_replace($ft["settings"], '.var_export($settings,true).');' . "\n"
    . '$ft["plugins"] = '.var_export($plugins,true).';' . "\n  return \$ft;";
$config = str_replace('  return $ft;', $settingsCode, $config);
$logging = "\nerror_reporting(E_ALL);\nini_set('display_errors', '0');\nini_set('log_errors', '1');\n"
    . "ini_set('error_log', ".var_export($data.'/php-error.log',true).");\n";
$config = preg_replace('/^<\?php/', '<?php'.$logging, $config, 1);
$handle = fopen($app.'/config.php', 'xb');
if (!$handle || fwrite($handle, $config) !== strlen($config)) { stop_setup('Could not write isolated configuration.'); }
fclose($handle); chmod($app.'/config.php', 0600);
// A marker proves this private log exists and is writable; no manufactured PHP error.
file_put_contents($data.'/php-error.log', "Acceptance setup completed. No application request yet.\n");
chmod($data.'/php-error.log', 0600);
file_put_contents($data.'/deployment.json', json_encode(['source_commit'=>$manifest['source_commit'],'public_directory'=>$app,'private_directory'=>$data,'files'=>$manifest['files']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
chmod($data.'/deployment.json', 0600);
echo "Preview URL: ",rtrim($argv[4],'/'),'/', $id, "/index.php\n";
echo "Username: stage5\nPassword: ",$password,"\n";
echo "Public test directory: ",$app,"\nPrivate test directory: ",$data,"\n";
echo "Private PHP log: ",$data,"/php-error.log\n";
echo "Source commit: ",$manifest['source_commit'],"\n";
echo "Only these new directories were created. Do not send the password to chat.\n";
