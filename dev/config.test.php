<?php
// Public fixture credentials for localhost containers only; never deploy this file.
function ft_settings_external_load() {
    define('USERNAME', 'stage2');
    define('PASSWORD_HASH', '$2y$10$9NPcPoyxJGSxRLWPwb66PeAw5Dkc0ObCC1grogVxdRKkZHnZvgA0C');
    return [
        'settings' => [
            'DIR' => 'files',
            'URL_HOSTS' => getenv('FT_TEST_URL_HOSTS') ?: '',
            'LANG' => 'ja',
            'MAXSIZE' => getenv('FT_TEST_MAXSIZE') !== false ? (int)getenv('FT_TEST_MAXSIZE') : 2000000,
            'PERMISSION' => 0644,
            'DIRPERMISSION' => 0755,
            'LOGIN' => true,
            'UPLOAD' => true,
            'CREATE' => true,
            'FILEACTIONS' => true,
            'HIDEFILEPATHS' => true,
            'DELETEFOLDERS' => false,
            'SHOWDATES' => false,
            'FILEBLACKLIST' => 'ft2.php ft.css config.php index.php config.sample.php LICENSE README.markdown .DS_store .gitignore',
            'FOLDERBLACKLIST' => 'plugins js css locales data',
            'FILETYPEBLACKLIST' => 'php phtml php3 php4 php5',
            'FILETYPEWHITELIST' => '',
            'ADVANCEDACTIONS' => false,
            'LIMIT' => 0,
            'REQUEST_URI' => false,
            'HTTPS' => false,
            'REMEMBERME' => false,
            'PLUGINDIR' => 'plugins',
        ],
        'users' => [
            'legacy' => ['password' => 'local-only-legacy'],
            'brokenhash' => ['password_hash' => '', 'password' => 'must-not-work'],
            'viewer' => ['password_hash' => '$2y$10$MxkWDnm92Bw4eBm34qSmJeHf12i0Nfec331fjPzBayOg3Si4HrNSy', 'group' => 'readers'],
            'ungrouped' => ['password_hash' => '$2y$10$cx8xerfYgs4w/9gfLS3OT.qCcOnjyAtIP6iWf2xFpTzaKnJeNYZn2'],
        ],
        'groups' => [
            'readers' => [
                'DIR' => 'files/nested',
                'UPLOAD' => false,
                'CREATE' => false,
                'FILEACTIONS' => false,
                'plugins' => ['search' => false, 'edit' => true],
            ],
        ],
        'plugins' => [
            'log' => ['settings' => ['viewlogs' => true]],
            'db' => ['settings' => ['path' => getenv('FT_TEST_DEGRADED') ? '/var/www/html/files/unsafe.sqlite' : '/var/lib/filethingie/test.sqlite', 'flushtables' => true]],
            'zip' => true,
            'config' => (bool)getenv('FT_TEST_DEGRADED'),
            'fileinfo' => (bool)getenv('FT_TEST_DEGRADED'),
            'tinymce' => (bool)getenv('FT_TEST_DEGRADED'),
            'editorck' => (bool)getenv('FT_TEST_DEGRADED'),
            'fckeditor' => (bool)getenv('FT_TEST_DEGRADED'),
            'search' => true,
            'edit' => ['settings' => ['editlist' => 'txt html htm css', 'converttabs' => false]],
        ],
    ];
}
