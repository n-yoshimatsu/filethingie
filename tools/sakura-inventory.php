<?php
// Read-only, non-executing inventory. Never include/eval the installed config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
function ft_inv_stop($message) { fwrite(STDERR, $message."\n"); exit(2); }
function ft_inv_stat($path) {
    if (is_link($path)) { return ['exists'=>true, 'symlink'=>true]; }
    if (!file_exists($path)) { return ['exists'=>false]; }
    return ['exists'=>true, 'symlink'=>false, 'type'=>is_dir($path)?'directory':'file',
        'mode'=>sprintf('%04o', fileperms($path)&07777), 'readable'=>is_readable($path),
        'bytes'=>is_file($path)?filesize($path):null];
}
function ft_inv_read($path) {
    if (!is_file($path) || is_link($path) || !is_readable($path) || filesize($path)>2097152) { return null; }
    $s=file_get_contents($path); return $s===false?null:$s;
}
function ft_inv_scalar($token, &$known) {
    $known=true;
    if (is_array($token) && $token[0]===T_CONSTANT_ENCAPSED_STRING) {
        $s=substr($token[1],1,-1);
        if ($token[1][0]==="'") { return str_replace(["\\'",'\\\\'],["'",'\\'],$s); }
        // Double-quoted strings with escape sequences need individual review.
        if (str_contains($s,'\\') || str_contains($s,'$')) { $known=false; return null; }
        return $s;
    }
    if (is_array($token) && $token[0]===T_LNUMBER) {
        if (preg_match('/^0[0-7]+$/D',$token[1])) { return intval($token[1],8); }
        if (preg_match('/^(?:0|[1-9][0-9]*)$/D',$token[1])) { return (int)$token[1]; }
    }
    if (is_array($token) && $token[0]===T_STRING) {
        if (strtolower($token[1])==='true') { return true; }
        if (strtolower($token[1])==='false') { return false; }
        if (strtolower($token[1])==='null') { return null; }
    }
    $known=false; return null;
}
function ft_inv_config($source) {
    $tokens=array_values(array_filter(token_get_all($source),static fn($t)=>!is_array($t)||!in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
    $settings=[]; $plugins=[]; $users=[]; $groups=[]; $defines=[]; $unknown=0; $dynamic=[]; $otherCalls=0;
    foreach ($tokens as $i=>$t) {
        if (is_array($t) && $t[0]===T_STRING && ($tokens[$i+1]??null)==='(' && strtolower($t[1])!=='define'
            && !(is_array($tokens[$i-1]??null) && $tokens[$i-1][0]===T_FUNCTION)) { $otherCalls++; }
        if (is_array($t) && in_array($t[0],[T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE,T_EVAL,T_IF,T_ELSEIF,T_ELSE,T_FOR,T_FOREACH,T_WHILE,T_DO,T_SWITCH,T_MATCH],true)) { $dynamic[token_name($t[0])]=true; }
        if (is_array($t) && $t[0]===T_STRING && strtolower($t[1])==='define' && ($tokens[$i+1]??null)==='(') {
            $key=ft_inv_scalar($tokens[$i+2]??null,$known);
            if ($known && in_array($key,['USERNAME','PASSWORD','PASSWORD_HASH','ALLOW_LEGACY_PASSWORD'],true)) { $defines[$key]=true; }
        }
        if (!is_array($t) || $t[0]!==T_VARIABLE || $t[1]!=='$ft') { continue; }
        $j=$i+1; $keys=[];
        while (($tokens[$j]??null)==='[') {
            $key=ft_inv_scalar($tokens[$j+1]??null,$known);
            if (!$known || !is_string($key) || ($tokens[$j+2]??null)!==']') { $unknown++; break; }
            $keys[]=$key; $j+=3;
        }
        if (($tokens[$j]??null)!=='=') { continue; }
        if (count($keys)===2 && $keys[0]==='settings') {
            $value=ft_inv_scalar($tokens[$j+1]??null,$known);
            $known=$known && ($tokens[$j+2]??null)===';';
            $settings[$keys[1]]=['literal'=>$known,'value'=>$known?$value:null];
            if (!$known) { $unknown++; }
        } elseif (count($keys)>=2 && $keys[0]==='plugins') {
            // Declaration only: nested settings and runtime activation are not inferred.
            $plugins[$keys[1]]=true;
        } elseif (count($keys)>=2 && $keys[0]==='users') { $users[$keys[1]]=true;
        } elseif (count($keys)>=2 && $keys[0]==='groups') { $groups[$keys[1]]=true;
        } elseif (count($keys)===1 && in_array($keys[0],['settings','plugins','users','groups'],true)) {
            $empty=($tokens[$j+1]??null)==='[' && ($tokens[$j+2]??null)===']';
            $v=$tokens[$j+1]??null;
            $empty=$empty || (is_array($v) && $v[0]===T_ARRAY && ($tokens[$j+2]??null)==='(' && ($tokens[$j+3]??null)===')');
            if (!$empty) { $unknown++; }
        }
    }
    return compact('settings','plugins','users','groups','defines','unknown','dynamic','otherCalls');
}
function ft_inv_counts($root) {
    $r=['complete'=>true,'files'=>0,'directories'=>0,'bytes'=>0,'symlinks'=>0,'unreadable_directories'=>0,'other_entries'=>0];
    $stack=[$root];$seen=0;$start=microtime(true);
    while ($stack) {
        $dir=array_pop($stack);$items=@scandir($dir);
        if ($items===false) { $r['complete']=false;$r['unreadable_directories']++;continue; }
        foreach ($items as $name) {
            if ($name==='.'||$name==='..') { continue; }
            if (++$seen>50000 || microtime(true)-$start>10) { $r['complete']=false;break 2; }
            $p=$dir.'/'.$name;
            if (is_link($p)) { $r['symlinks']++;
            } elseif (is_dir($p)) { $r['directories']++;$stack[]=$p;
            } elseif (is_file($p)) { $r['files']++;$r['bytes']+=filesize($p);
            } else { $r['other_entries']++; }
        }
    }
    return $r;
}
// Reuse only the non-executing parser from the CLI backup helper.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }
if ($argc!==4) { ft_inv_stop('Usage: php sakura-inventory.php APPLICATION_DIRECTORY HOME_DIRECTORY WEB_ROOT'); }
[$app,$home,$webroot]=array_map('realpath',array_slice($argv,1));
if (!$app||!$home||!$webroot||is_link($argv[1])||!str_starts_with($app,$webroot.'/')
    || !str_starts_with($webroot,$home.'/') || preg_match('/^ft-(?:stage5|php-check)-/',basename($app))) {
    ft_inv_stop('An existing application inside the declared home/web root is required.');
}
$config=ft_inv_read($app.'/config.php');
if ($config===null) { ft_inv_stop('Readable, non-symlink config.php under 2 MiB required.'); }
$parsed=ft_inv_config($config);
$settings=[];
foreach (['LOGIN','HIDEFILEPATHS','MAXSIZE','LIMIT','HTTPS','REMEMBERME','UPLOAD','CREATE','FILEACTIONS','DELETEFOLDERS','ADVANCEDACTIONS','PERMISSION','DIRPERMISSION'] as $key) {
    if (isset($parsed['settings'][$key])) {
        $v=$parsed['settings'][$key];
        $settings[$key]=['literal'=>$v['literal'],'value'=>is_bool($v['value'])||is_int($v['value'])?$v['value']:null];
    }
}
$lang=$parsed['settings']['LANG']??null;
if ($lang && $lang['literal'] && is_string($lang['value']) && preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/D',$lang['value'])) { $settings['LANG']=$lang; }
$path=$parsed['settings']['DIR']??null;$data=['literal_candidate'=>false];
if ($path && $path['literal'] && is_string($path['value']) && $path['value']!=='' && !str_contains($path['value'],"\0")) {
    $candidate=str_starts_with($path['value'],'/')?$path['value']:$app.'/'.$path['value'];$real=realpath($candidate);
    $data=['literal_candidate'=>true,'relative_setting'=>!str_starts_with($path['value'],'/'),'same_as_application'=>$real===$app,
        'inside_web_root'=>$real!==false&&($real===$webroot||str_starts_with($real,$webroot.'/')),
        'inside_home'=>$real!==false&&($real===$home||str_starts_with($real,$home.'/')),'metadata'=>ft_inv_stat($candidate)];
    if ($real && is_dir($real) && !is_link($candidate) && str_starts_with($real,$home.'/')) { $data['inventory']=ft_inv_counts($real); }
}
$knownPlugins=['search','edit','db','log','zip','config','fileinfo','tinymce','editorck','fckeditor'];
$declared=array_keys($parsed['plugins']);sort($declared);
$basic=[];$authFiles=[];
for ($dir=$app,$level=0;;$dir=dirname($dir),$level++) {
    $file=$dir.'/.htaccess';$s=ft_inv_read($file);
    if (file_exists($file)||is_link($file)) {
        $item=['ancestor_level'=>$level,'metadata'=>ft_inv_stat($file),'basic_directive'=>$s!==null&&preg_match('/^\s*AuthType\s+Basic\s*(?:#.*)?$/mi',$s)===1,'auth_user_file_directives'=>0,'auth_group_file_directives'=>0,'access_require_directive'=>$s!==null&&preg_match('/^\s*Require\s+/mi',$s)===1];
        if ($s!==null && preg_match_all('/^\s*(AuthUserFile|AuthGroupFile)\s+(?:"([^"\r\n]+)"|([^\s#]+))/mi',$s,$matches,PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $kind=strtolower($m[1])==='authuserfile'?'users':'groups';$item[$kind==='users'?'auth_user_file_directives':'auth_group_file_directives']++;
                $path=$m[2]!==''?$m[2]:$m[3];$resolved=str_starts_with($path,'/')&& !str_contains($path,'${')?realpath($path):false;
                $entry=['role'=>$kind,'absolute_literal_path'=>str_starts_with($path,'/')&&!str_contains($path,'${'),'resolves'=>$resolved!==false];
                // AuthUserFile contents are deliberately never read.
                if ($resolved && ($resolved===$home||str_starts_with($resolved,$home.'/'))) {
                    $entry['inside_web_root']=$resolved===$webroot||str_starts_with($resolved,$webroot.'/');$entry['metadata']=ft_inv_stat($path);
                }
                $authFiles[]=$entry;
            }
        }
        $basic[]=$item;
    }
    if ($dir===$webroot) { break; }
}
$roles=[];
foreach (['index.php','ft2.php','config.php','hooks.php','users.sh','users.txt','php.ini','php.ini~','phpinfo.php','.htpasswd'] as $name) {
    $roles[$name]=ft_inv_stat($app.'/'.$name);
}
$references=[];
foreach (['users.sh','index.php','hooks.php'] as $name) {
    $s=ft_inv_read($app.'/'.$name);
    if ($s!==null) {
        $references[$name]=[];
        foreach (['htpasswd','REMOTE_USER','PHP_AUTH_USER','users.txt','config.php'] as $needle) { $references[$name][$needle]=str_contains($s,$needle); }
    }
}
$free=disk_free_space($home);
echo json_encode(['scope'=>'段階6 本番移行前の読取り専用棚卸し','checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'sapi'=>PHP_SAPI,
    'config_analysis'=>['mode'=>'静的な単純リテラル代入の候補。設定の実行・実効値の確定ではない解析','unsupported_assignments'=>$parsed['unknown'],'other_function_calls'=>$parsed['otherCalls'],'dynamic_constructs'=>array_keys($parsed['dynamic']),'settings'=>$settings,'credential_definitions_present'=>array_keys($parsed['defines']),'additional_user_literal_keys'=>count($parsed['users']),'group_literal_keys'=>count($parsed['groups']),
        'known_plugin_declarations'=>array_values(array_intersect($declared,$knownPlugins)),'unknown_plugin_declarations'=>count(array_diff($declared,$knownPlugins))],
    'managed_directory_candidate'=>$data,'basic_auth'=>['htaccess_chain'=>$basic,'credential_file_metadata'=>$authFiles,'note'=>'Apache設定全体・条件分岐を含む実効認証範囲は別途確認。認証ファイル本文の非読取り'],
    'installed_roles'=>$roles,'source_reference_flags'=>$references,'home_free_bytes'=>$free===false?null:(int)$free,
    'privacy'=>'パスワード・ハッシュ・ユーザー名・グループ名・保存ファイル名・フルパス・設定本文の非出力。ファイル作成・変更なし。'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
