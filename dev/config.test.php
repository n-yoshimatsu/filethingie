<?php
// Public fixture credentials for localhost containers only; never deploy this file.
function ft_settings_external_load() {
    define('USERNAME', 'stage2');
    define('PASSWORD', 'local-only-filethingie');
    return [
        'settings' => [
            'DIR' => 'files',
            'LANG' => 'ja',
            'MAXSIZE' => 2000000,
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
        'users' => [],
        'groups' => [],
        'plugins' => [
            'search' => true,
            'edit' => ['settings' => ['editlist' => 'txt html htm css', 'converttabs' => false]],
        ],
    ];
}
