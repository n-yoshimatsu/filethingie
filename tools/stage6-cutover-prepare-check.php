<?php
// Component self-check using only the existing disposable local fixture.
if(PHP_SAPI!=='cli'||$argc!==2){exit(2);}
require $argv[1].'/sakura-cutover-prepare.php';
$state=json_decode(file_get_contents('/tmp/ft-rehearsal-local/state.json'),true);
$app=$state['original'];$home='/tmp/ft-rehearsal-local/home';$id=basename($state['public']);$checks=[];
ftb_need($app===$home.'/www/app','Local synthetic fixture required.');
function cpcheck($name,$ok){global $checks;$checks[]=['name'=>$name,'verdict'=>$ok?'PASS':'FAIL'];if(!$ok){throw new RuntimeException($name);}}
function cpprepare(&$created){global $home,$id;return ftcp_prepare($home,$id,$created);}
function cpreject($name){$created=null;$rejected=false;try{cpprepare($created);}catch(RuntimeException $e){$rejected=true;}cpcheck($name,$rejected&&$created===null);}
try{
    $before=ftb_tree($app);$created=null;$result=cpprepare($created);
    cpcheck('非公開準備完了',$result['verdict']==='PREPARED');
    cpcheck('アプリ46・配置予定49ファイル',$result['application_files']===46&&$result['planned_target_files']===49);
    cpcheck('本番・検証版の全エントリー不変',ftb_tree($app)===$before);
    cpcheck('設定PHPの非実行',!defined('USERNAME')&&$result['configuration_executed']===false);
    cpcheck('停止・切替・本番書込みなし',!$result['production_written']&&!$result['maintenance_started']&&!$result['cutover_performed']);
    cpcheck('最終停止バックアップとの区別',$result['final_stopped_backup']==='NOT RUN');
    $plan=json_decode(ftb_read($created.'/plan.json'),true);$complete=json_decode(ftb_read($created.'/COMPLETE.json'),true);
    cpcheck('計画ファイルのハッシュ一致',hash_file('sha256',$created.'/plan.json')===$complete['plan_sha256']);
    cpcheck('コピーではなく既存保存先を使う設定',str_contains(ftb_read($created.'/config.php'),'./files')&&$plan['application']===$app);
    cpcheck('元のBasic認証ファイル参照の維持',str_contains(ftb_read($created.'/new/.htaccess'),$app.'/.htpasswd'));
    cpcheck('旧診断・管理ファイルのHTTP保護準備',str_contains(ftb_read($created.'/new/.htaccess'),'phpinfo\\.php|users\\.sh|users\\.txt'));
    $privateTree=ftb_tree($created);$modes=true;foreach($privateTree as $e){if($e['mode']!==($e['type']==='file'?0600:0700)){$modes=false;}}
    cpcheck('準備先の全ディレクトリ0700・全ファイル0600',$modes);
    $match=true;foreach($plan['new'] as $rel=>$e){if(hash_file('sha256',$created.'/new/'.$rel)!==$e['sha256']){$match=false;}}
    cpcheck('49ファイルの準備内容と計画の一致',$match);
    $text=ftb_json($result);cpcheck('設定・認証・フルパス・固有表示の非出力',!str_contains($text,$home)&&!str_contains($text,'local-rehearsal-only')&&!str_contains($text,'Fixture & files'));
    // A new user file after rehearsal is preserved in the next inventory, not overwritten from the old copy.
    $t=filemtime($app.'/files');$fresh=$app.'/files/current-new.txt';
    try{file_put_contents($fresh,'new user data');$newBefore=ftb_tree($app);$extra=null;$r=cpprepare($extra);$p=json_decode(ftb_read($extra.'/plan.json'),true);cpcheck('直近の保存データの取得と維持',$r['current_managed_files']===4&&isset($p['managed_before']['current-new.txt'])&&ftb_tree($app)===$newBefore);}
    finally{unlink($fresh);touch($app.'/files',$t);clearstatcache();}
    $old=ftb_read($app.'/index.php');$t=filemtime($app.'/index.php');
    try{file_put_contents($app.'/index.php',$old."\n// source changed\n");cpreject('レビュー後のソース変更の拒否');}
    finally{file_put_contents($app.'/index.php',$old);touch($app.'/index.php',$t);clearstatcache();}
    $old=ftb_read($app.'/config.php');$t=filemtime($app.'/config.php');
    try{file_put_contents($app.'/config.php',str_replace('<?php','<?php file_put_contents("'.$home.'/must-not-exist", "side effect");',$old));cpreject('実行式を追加した設定の拒否');cpcheck('設定内の副作用コードの非実行',!file_exists($home.'/must-not-exist'));}
    finally{file_put_contents($app.'/config.php',$old);touch($app.'/config.php',$t);clearstatcache();}
    $t=filemtime($app.'/files');
    try{file_put_contents($app.'/files/.htaccess','Require all granted');cpreject('保存先への未レビュー保護設定の拒否');}
    finally{unlink($app.'/files/.htaccess');touch($app.'/files',$t);clearstatcache();}
    $t=filemtime($app);
    try{symlink($app.'/files/existing',$app.'/js');cpreject('配置予定先の親シンボリックリンクの拒否');}
    finally{unlink($app.'/js');touch($app,$t);clearstatcache();}
    cpcheck('人工原本の最終復元一致',ftb_tree($app)===$before);
    $out=['scope'=>'本番切替前の資材準備ツールのローカル自己検証。実機の準備・切替とは別の記録','php'=>PHP_VERSION,'verdict'=>'PASS','checks'=>$checks];
}catch(Throwable $e){$out=['scope'=>'本番切替前の資材準備ツールのローカル自己検証','php'=>PHP_VERSION,'verdict'=>'FAIL','checks'=>$checks,'error'=>$e->getMessage()];}
echo ftb_json($out);exit($out['verdict']==='PASS'?0:1);
