<?php
// Build-time fixtures; never reads or modifies host or production data.
$root = '/var/www/html/files';
if (file_exists($root)) {
    throw new RuntimeException('Fixture directory already exists');
}
mkdir($root . '/nested', 0755, true);
$fixtures = [
    'ascii.txt' => "File Thingie stage 2\n",
    '日本語.txt' => "日本語の検証用ファイル\n",
    'space name.txt' => "Name with a space\n",
    'empty.txt' => '',
    'nested/child.txt' => "Nested fixture\n",
    'read-only.txt' => "Read-only fixture\n",
];
foreach ($fixtures as $name => $content) {
    $path = $root . '/' . $name;
    if (file_put_contents($path, $content) !== strlen($content)) {
        throw new RuntimeException('Fixture write failed: ' . $name);
    }
    chmod($path, $name === 'read-only.txt' ? 0444 : 0644);
}
