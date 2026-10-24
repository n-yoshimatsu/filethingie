<?php
// Synthetic filesystem fixtures only, inside a disposable container.
if (PHP_SAPI!=='cli' || $argc!==2) { exit(2); }
$kit=$argv[1];$base=sys_get_temp_dir().'/ft-backup-test-'.bin2hex(random_bytes(6));$home=$base.'/home';$app=$home.'/www/app';$checks=[];
function assert_backup($name,$actual,$expected) {
    global $checks;$ok=$actual===$expected;
    $checks[]=['name'=>$name,'actual'=>$actual,'expected'=>$expected,'verdict'=>$ok?'PASS':'FAIL'];
    if (!$ok) { throw new RuntimeException($name); }
}
function independent_tree($root) {
    $r=[];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $p) {
        $rel=substr($p->getPathname(),strlen($root)+1);clearstatcache(true,$p->getPathname());
        $r[$rel]=['mode'=>fileperms($p->getPathname())&0777,'mtime'=>filemtime($p->getPathname()),'size'=>$p->isFile()?$p->getSize():null,
            'sha256'=>$p->isFile()?hash_file('sha256',$p->getPathname()):null];
    }
    ksort($r);return $r;
}
function call_backup($action,$path) {
    global $kit,$home;
    $p=proc_open([PHP_BINARY,'-d','memory_limit=128M','-d','error_reporting=E_ALL',$kit.'/sakura-backup.php',$action,$path,$home,$home.'/www'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    return [$status,json_decode($out,true,512,JSON_THROW_ON_ERROR),$err,$out];
}
function delete_fixture($path) {
    if (is_file($path)||is_link($path)) { unlink($path);return; }
    chmod($path,0700);foreach(scandir($path) as $n) { if ($n!=='.'&&$n!=='..') { delete_fixture($path.'/'.$n); } }rmdir($path);
}
$record=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-604':'TS-605','name'=>'保全コピーと隔離復元','php'=>PHP_VERSION,
    'scope'=>'使い捨てfixtureでのCLIコピー・復元。さくら実機での実行結果ではない記録','verdict'=>'INCONCLUSIVE'];
try {
    mkdir($base,0700);mkdir($home);mkdir($home.'/www');mkdir($app);mkdir($app.'/files');mkdir($app.'/files/empty-directory',0751);
    file_put_contents($app.'/files/日本語.txt',"fixture content 日本語\n");file_put_contents($app.'/files/empty.txt','');
    $h=fopen($app.'/files/large.bin','wb');for($i=0;$i<12;$i++) { fwrite($h,str_repeat("0123456789abcdef",65536)); }fclose($h);
    file_put_contents($app.'/.hidden','fixture-hidden-content');chmod($app.'/.hidden',0600);
    $config= <<<'CONFIG'
<?php
function ft_settings_external_load() {
  $ft=array();$ft['settings']=array();$ft['users']=array();$ft['groups']=array();$ft['plugins']=array();
  define('USERNAME','fixture-app-user');define('PASSWORD','fixture-app-secret');
  $ft['settings']['LOGIN']=false;$ft['settings']['DIR']='files';$ft['settings']['MAXSIZE']=52428800;
  $ft['plugins']['search']=true;$ft['plugins']['edit']=array('settings'=>array('editlist'=>'txt'));
  return $ft;
}
CONFIG;
    file_put_contents($app.'/config.php',$config);chmod($app.'/config.php',0644);
    file_put_contents($app.'/index.php',"<?php\r\n// fixture upstream\r\necho 'index';\r\n");
    file_put_contents($app.'/hooks.php',"<?php // modified\n");
    file_put_contents($app.'/users.sh',"#!/bin/sh\ntouch '$home/must-not-execute'\n");chmod($app.'/users.sh',0700);
    file_put_contents($app.'/.htpasswd','fixture-basic-user:fixture-basic-secret-hash');
    file_put_contents($home.'/auth-users','fixture-external-user:fixture-external-secret-hash');chmod($home.'/auth-users',0640);
    file_put_contents($home.'/auth-groups','fixture-group: fixture-basic-user');chmod($home.'/auth-groups',0600);
    file_put_contents($app.'/files/.htaccess',"AuthGroupFile \"$home/auth-groups\"\n");
    file_put_contents($app.'/.htaccess',"AuthType Basic\nAuthUserFile \"$app/.htpasswd\"\nRequire valid-user\n");
    file_put_contents($home.'/www/.htaccess',"AuthType Basic\nAuthUserFile \"$home/auth-users\"\nAuthUserFile \"$home/missing-users\"\n");
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $p) { touch($p->getPathname(),1700000000); }
    $before=independent_tree($home.'/www');$externalBefore=hash_file('sha256',$home.'/auth-users');
    [$code,$result,$stderr,$stdout]=call_backup('backup',$app);
    assert_backup('正常コピーの終了状態',$code,0);assert_backup('正常コピーのPHP診断なし',$stderr,'');
    assert_backup('コピー結果',$result['verdict'],'PASS');assert_backup('原本ツリーの内容・権限・時刻不変',independent_tree($home.'/www')===$before,true);
    assert_backup('外部認証原本の内容不変',hash_file('sha256',$home.'/auth-users'),$externalBefore);
    assert_backup('users.shの非実行',file_exists($home.'/must-not-execute'),false);
    assert_backup('未解決の認証参照を保持',$result['basic_auth_missing_references'],1);
    assert_backup('直接のfiles配置を検出',$result['managed_data_is_files_directory'],true);
    assert_backup('CRLF差分の分類',$result['upstream_comparison']['files']['index.php'],'LINE_ENDINGS_ONLY');
    assert_backup('変更済みソースの分類',$result['upstream_comparison']['files']['hooks.php'],'MODIFIED');
    $backup=$home.'/'.$result['backup_id'];$m=json_decode(file_get_contents($backup.'/manifest.json'),true);
    assert_backup('バックアップ親の0700',fileperms($backup)&0777,0700);
    $allPrivate=true;foreach(independent_tree($backup) as $r) { if($r['mode']!==($r['size']===null?0700:0600)) {$allPrivate=false;} }
    assert_backup('バックアップ全体の0700／0600',$allPrivate,true);
    assert_backup('上位・子フォルダー参照の外部ファイル3本の保全',$result['extra_files'],3);
    assert_backup('完了マーカーの存在',is_file($backup.'/COMPLETE.json'),true);
    assert_backup('隠しファイルの保全',file_get_contents($backup.'/app/.hidden'),'fixture-hidden-content');
    $secretOutput=false;foreach(['fixture-app-user','fixture-app-secret','fixture-basic-user','fixture-basic-secret-hash','fixture-external-user','fixture-external-secret-hash',$home,'日本語.txt','large.bin'] as $needle) { $secretOutput=$secretOutput||str_contains($stdout,$needle); }
    assert_backup('秘密情報・実保存ファイル名・フルパスの非出力',$secretOutput,false);
    [$code,$restore,$stderr]=call_backup('restore',$backup);$restored=$home.'/'.$restore['restore_id'];
    assert_backup('隔離復元の終了状態',$code,0);assert_backup('隔離復元のPHP診断なし',$stderr,'');
    assert_backup('復元結果',$restore['verdict'],'PASS');
    assert_backup('独立走査による全復元内容・権限・時刻一致',independent_tree($restored.'/app')===independent_tree($app),true);
    assert_backup('復元後も原本不変',independent_tree($home.'/www')===$before,true);
    assert_backup('復元親の0700',fileperms($restored)&0777,0700);
    // A modified backup must not be accepted or create a restoration directory.
    file_put_contents($backup.'/app/files/empty.txt','tampered');
    [$code,$bad,$stderr]=call_backup('restore',$backup);
    assert_backup('改変コピーの拒否',$code,2);assert_backup('改変検出時の復元先非作成',$bad['private_directory_id'],null);
    assert_backup('改変拒否のPHP診断なし',$stderr,'');
    // The completion marker does not excuse a malicious relative path.
    $encoded=file_get_contents($backup.'/manifest.json');$badManifest=json_decode($encoded,true);$badManifest['entries']['../outside']=$badManifest['entries']['config.php'];
    $badEncoded=json_encode($badManifest);file_put_contents($backup.'/manifest.json',$badEncoded);file_put_contents($backup.'/COMPLETE.json',json_encode(['manifest_sha256'=>hash('sha256',$badEncoded)]));
    [$code,$bad,$stderr]=call_backup('restore',$backup);assert_backup('manifestパス逸脱の拒否',$code,2);
    assert_backup('逸脱先の非作成',file_exists($home.'/outside'),false);
    unlink($backup.'/COMPLETE.json');[$code,$bad,$stderr]=call_backup('restore',$backup);assert_backup('未完了コピーの復元拒否',$code,2);
    symlink($home.'/auth-users',$app.'/files/link');[$code,$bad,$stderr]=call_backup('backup',$app);
    assert_backup('原本リンクの拒否',$code,2);assert_backup('リンク拒否時のコピー先非作成',$bad['private_directory_id'],null);unlink($app.'/files/link');
    file_put_contents($app.'/config.php',$config."\nfile_put_contents(".var_export($home.'/must-not-execute',true).",'bad');\n");
    [$code,$bad,$stderr]=call_backup('backup',$app);assert_backup('副作用コードを含む設定の拒否',$code,2);
    assert_backup('設定の非実行',file_exists($home.'/must-not-execute'),false);
    file_put_contents($app.'/config.php',str_replace("'DIR']='files'","'DIR']='../outside-data'",$config));mkdir($home.'/www/outside-data');
    [$code,$bad,$stderr]=call_backup('backup',$app);assert_backup('アプリ外データの不完全保全の防止',$code,2);
    [$code,$bad,$stderr]=call_backup('backup',$home);assert_backup('公開範囲外アプリ指定の拒否',$code,2);
    $record['verdict']='PASS';
} catch(Throwable $e) {
    $record['verdict']='FAIL';$record['error']=$e->getMessage();
} finally {
    if(is_dir($base)) { delete_fixture($base); }
    $record['checks']=$checks;echo json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
}
exit($record['verdict']==='PASS'?0:1);
