<?php
// Artificial fixtures only; run in a disposable PHP container.
if (PHP_SAPI !== 'cli' || $argc !== 2) { exit(2); }
$root=sys_get_temp_dir().'/ft-inventory-fixture-'.bin2hex(random_bytes(6));
$checks=[];
function expect_inv($name,$actual,$expected) {
    global $checks;
    $pass=$actual===$expected;$checks[]=['name'=>$name,'actual'=>$actual,'expected'=>$expected,'verdict'=>$pass?'PASS':'FAIL'];
    if (!$pass) { throw new RuntimeException($name); }
}
function tree_inv($path) {
    $r=[];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $p) {
        $name=substr($p->getPathname(),strlen($path)+1);
        $r[$name]=is_link($p->getPathname())?['link'=>readlink($p->getPathname())]:['mode'=>fileperms($p->getPathname())&07777,'sha256'=>$p->isFile()?hash_file('sha256',$p->getPathname()):null];
    }
    ksort($r);return $r;
}
function run_inv($root,$application=null) {
    $pipes=[];$process=proc_open([PHP_BINARY,'-d','error_reporting=E_ALL','-d','display_errors=stderr',$root.'/inventory.php',$application??$root.'/www/app',$root,$root.'/www'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$stdout,$stderr];
}
function remove_inv($path) {
    if (is_link($path)||is_file($path)) { unlink($path);return; }
    foreach (scandir($path) as $n) { if ($n!=='.'&&$n!=='..') { remove_inv($path.'/'.$n); } }
    rmdir($path);
}
$record=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-601':'TS-602','name'=>'移行前棚卸し','php'=>PHP_VERSION,'scope'=>'人工fixtureによるツール検証。本番移行結果ではない記録','verdict'=>'INCONCLUSIVE'];
try {
    mkdir($root,0700);mkdir($root.'/www');mkdir($root.'/www/app');mkdir($root.'/www/app/files');mkdir($root.'/private',0700);
    file_put_contents($root.'/inventory.php',base64_decode($argv[1],true));
    file_put_contents($root.'/www/app/files/日本語.txt',"日本語 UTF-8\n");file_put_contents($root.'/www/app/files/empty.txt','');
    symlink($root.'/private',$root.'/www/app/files/link');
    file_put_contents($root.'/private/passwords','fixture-basic-user:fixture-secret-hash-must-not-leak');chmod($root.'/private/passwords',0600);
    file_put_contents($root.'/www/.htaccess',"AuthType Basic\nAuthUserFile \"".$root."/private/passwords\"\nRequire valid-user\n");
    file_put_contents($root.'/www/app/.htaccess',"# AuthUserFile /do-not-read\nRequire valid-user\n");
    $config= <<<'CONFIG'
<?php
function ft_settings_external_load() {
    $ft=array();$ft['settings']=array();$ft['users']=array();$ft['groups']=array();$ft['plugins']=array();
    define('USERNAME','fixture-app-user');define('PASSWORD','fixture-password-must-not-leak');
    // $ft['settings']['LOGIN']=true;
    $ft['settings']['LOGIN']=false;$ft['settings']['DIR']='files';$ft['settings']['LANG']='ja';
    $ft['settings']['HIDEFILEPATHS']=true;$ft['settings']['MAXSIZE']=2000000;$ft['settings']['PERMISSION']=0644;
    $ft['plugins']['search']=true;$ft['plugins']['edit']=array('settings'=>array('editlist'=>'txt'));
    $ft['users']['fixture-additional-user']=array('password'=>'fixture-additional-password','group'=>'fixture-group');
    $ft['groups']['fixture-group']=array('DIR'=>'files');
    return $ft;
}
CONFIG;
    $config.="\nfile_put_contents(".var_export($root.'/executed-marker',true).", 'must-not-execute');\n";
    file_put_contents($root.'/www/app/config.php',$config);chmod($root.'/www/app/config.php',0600);
    file_put_contents($root.'/www/app/index.php','<?php // REMOTE_USER reference fixture');
    file_put_contents($root.'/www/app/users.sh',"# htpasswd users.txt fixture\n");
    $before=tree_inv($root);[$status,$stdout,$stderr]=run_inv($root);$r=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    expect_inv('正常fixtureの終了状態',$status,0);expect_inv('PHP診断通知なし',$stderr,'');
    expect_inv('LOGIN=false候補とコメント無視',$r['config_analysis']['settings']['LOGIN'],['literal'=>true,'value'=>false]);
    expect_inv('8進数権限の読取り',$r['config_analysis']['settings']['PERMISSION']['value'],0644);
    expect_inv('主ユーザー資格情報の存在のみ',$r['config_analysis']['credential_definitions_present'],['USERNAME','PASSWORD']);
    expect_inv('追加ユーザー数',$r['config_analysis']['additional_user_literal_keys'],1);expect_inv('グループ数',$r['config_analysis']['group_literal_keys'],1);
    expect_inv('プラグイン宣言',$r['config_analysis']['known_plugin_declarations'],['edit','search']);
    expect_inv('DIR候補の集計',$r['managed_directory_candidate']['inventory'],['complete'=>true,'files'=>2,'directories'=>0,'bytes'=>strlen("日本語 UTF-8\n"),'symlinks'=>1,'unreadable_directories'=>0,'other_entries'=>0]);
    expect_inv('祖先.htaccess件数',count($r['basic_auth']['htaccess_chain']),2);
    expect_inv('Basic宣言',$r['basic_auth']['htaccess_chain'][1]['basic_directive'],true);
    expect_inv('引用符付き認証ファイル',$r['basic_auth']['credential_file_metadata'][0]['metadata']['mode'],'0600');
    expect_inv('認証ファイルの非公開配置',$r['basic_auth']['credential_file_metadata'][0]['inside_web_root'],false);
    expect_inv('設定内の副作用非実行',file_exists($root.'/executed-marker'),false);
    expect_inv('関数呼出しの要確認フラグ',$r['config_analysis']['other_function_calls']>0,true);
    expect_inv('全fixture内容・権限・リンク不変',tree_inv($root),$before);
    foreach (['fixture-app-user','fixture-password-must-not-leak','fixture-additional-user','fixture-additional-password','fixture-group','fixture-basic-user','fixture-secret-hash-must-not-leak',$root,'日本語.txt'] as $secret) {
        expect_inv('秘密情報・ファイル名の非出力 '.count($checks),str_contains($stdout,$secret),false);
    }
    file_put_contents($root.'/www/app/config.php',str_replace("\$ft['settings']['DIR']='files';","\$ft['settings']['DIR']=getenv('DYNAMIC_DIRECTORY');",$config));
    [$status,$stdout,$stderr]=run_inv($root);$dynamic=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    expect_inv('動的式のPHP診断通知なし',$stderr,'');
    expect_inv('動的DIRの候補未確定',$dynamic['managed_directory_candidate']['literal_candidate'],false);
    expect_inv('動的式の未対応代入記録',$dynamic['config_analysis']['unsupported_assignments']>0,true);
    file_put_contents($root.'/www/app/config.php',str_replace("'DIR']='files'","'DIR']='linked-files'",$config));symlink($root.'/private',$root.'/www/app/linked-files');
    [$status,$stdout,$stderr]=run_inv($root);$linked=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    expect_inv('DIRリンクの集計非実行',isset($linked['managed_directory_candidate']['inventory']),false);
    unlink($root.'/www/app/config.php');symlink($root.'/private/passwords',$root.'/www/app/config.php');
    [$status,$stdout,$stderr]=run_inv($root);expect_inv('設定リンクの拒否',$status,2);expect_inv('設定リンク拒否時の本文非出力',$stdout,'');
    [$status,$stdout,$stderr]=run_inv($root,$root.'/private');expect_inv('公開ルート外アプリ指定の拒否',$status,2);
    $record['verdict']='PASS';
} catch (Throwable $error) {
    $record['verdict']='FAIL';$record['error']=$error->getMessage();
} finally {
    if (is_dir($root)) { remove_inv($root); }
    $record['checks']=$checks;echo json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
}
exit($record['verdict']==='PASS'?0:1);
