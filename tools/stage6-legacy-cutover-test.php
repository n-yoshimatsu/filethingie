<?php
// Synthetic local fixture only. The Web runtime switch itself remains a Sakura check.
if(PHP_SAPI!=='cli'||$argc!==2){exit(2);}require $argv[1].'/sakura-cutover.php';
$local=json_decode(file_get_contents('/tmp/ft-rehearsal-local/state.json'),true);
$home='/tmp/ft-rehearsal-local/home';$app=$home.'/www/app';$checks=[];
ftb_need($local['original']===$app&&posix_geteuid()===fileowner($home),'Disposable local fixture owned by Web account required.');
function lc($name,$ok){global $checks;$checks[]=['name'=>$name,'verdict'=>$ok?'PASS':'FAIL'];if(!$ok){throw new RuntimeException($name);}}
function lr($name,$call){$rejected=false;try{$call();}catch(RuntimeException $e){$rejected=true;}lc($name,$rejected);}
function lh($entry='index.php',$auth=true,$post=false){
    $h=curl_init('https://127.0.0.1/app/'.$entry);$o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_TIMEOUT=>15];
    if($auth){$o[CURLOPT_USERPWD]='fixture:local-rehearsal-only';}if($post){$o[CURLOPT_POST]=true;$o[CURLOPT_POSTFIELDS]='';}
    curl_setopt_array($h,$o);$body=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);return [$status,$body];
}
function lp($home,$id){$created=null;ftcp_prepare($home,$id,$created);$p=ftc_load($home,basename($created));return [$p,ftc_state($p)];}
try{
    $oldLegacy="<?php echo 'legacy fixture entry';\n";ftb_write($app.'/ft2.php',$oldLegacy);chmod($app.'/ft2.php',0604);
    $backup=null;ftb_backup($app,$home,$home.'/www',$backup);$rehearsal=null;$r=ftr_run($backup,$home,$home.'/www','https://127.0.0.1/app/',$rehearsal);$id=$r['rehearsal_id'];
    $before=ftb_tree($app);[$p,$s]=lp($home,$id);
    lc('既存ft2.phpを含む50対象の準備',count($p['new'])===50&&$p['legacy_entry_present']===true);
    lc('準備時の原本不変',ftb_tree($app)===$before);
    lc('旧ft2.phpの内容・権限の別保全',ftc_private($p['private'].'/before/ft2.php')===$oldLegacy&&$p['before']['ft2.php']['mode']===0604);
    ftc_stop($p,$s,true);
    foreach(['index.php','ft2.php'] as $entry){
        lc('停止中の認証なし401 '.$entry,lh($entry,false)[0]===401);
        foreach([false,true] as $post){[$status,$body]=lh($entry,true,$post);lc('停止中の'.($post?'POST':'GET').' 503 '.$entry,$status===503&&str_contains($body,'temporarily unavailable'));}
    }
    lr('Web実行環境の確認なしの取消拒否',function()use($p,&$s){ftc_cancel($p,$s,false);});
    ftc_cancel($p,$s,true);lc('停止のみからの取消',$s['phase']==='CANCELLED');
    foreach($p['before'] as $rel=>$entry){lc('取消後の元ファイル一致 '.$rel,ftcp_target($app,$rel)===$entry);}
    lc('旧入口のHTTP復帰',lh('ft2.php')===[200,'legacy fixture entry']);
    ftc_cancel($p,$s,true);lc('取消の再実行',$s['phase']==='CANCELLED');
    [$p,$s]=lp($home,$id);ftc_stop($p,$s,true);ftc_backup($p,$s,true);
    $m=ftc_final_backup($p,$s);lc('最終保全内のft2.phpも停止用転送',$m['entries']['ft2.php']['sha256']===hash('sha256',ftcp_legacy_entry()));
    ftc_apply($p,$s);
    $alias=ftb_read($app.'/ft2.php');$stamp=filemtime($app.'/ft2.php');
    try{file_put_contents($app.'/ft2.php',$alias.'// tamper');lr('旧入口の改変時の再開拒否',function()use($p,&$s){ftc_resume($p,$s);});}
    finally{file_put_contents($app.'/ft2.php',$alias);touch($app.'/ft2.php',$stamp);clearstatcache();}
    lr('配置済み状態から直接取消の拒否',function()use($p,&$s){ftc_cancel($p,$s,true);});
    ftc_resume($p,$s);lc('新版両入口のHTTP 200',lh()[0]===200&&lh('ft2.php')[0]===200);
    $verified=ftc_verify($p,$s);lc('新版50対象のCLI照合',$verified['verdict']==='PASS'&&$verified['checks'][0]['name']==='配置50ファイルの不一致数');
    file_put_contents($app.'/files/post-activation.txt','preserve new synthetic data');$data=ftc_data($p);
    ftc_rollback($p,$s);lc('復元後も両入口のHTTP 503',$s['phase']==='RESTORED_STOPPED'&&lh()[0]===503&&lh('ft2.php')[0]===503);
    lc('復元中の更新データ保持',ftc_data($p)===$data);
    ftc_cancel($p,$s,true);lc('復元後の取消で旧入口復帰',$s['phase']==='CANCELLED'&&lh('ft2.php')===[200,'legacy fixture entry']);
    lc('取消後も更新データ保持',ftc_data($p)===$data);
    foreach($p['before'] as $rel=>$entry){lc('復旧後の元ファイル一致 '.$rel,ftcp_target($app,$rel)===$entry);}
    $out=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-620':'TS-621','name'=>'旧入口を含む停止・切替・取消・復旧','php'=>PHP_VERSION,'verdict'=>'PASS','checks'=>$checks,
        'scope_note'=>'使い捨てLinux Apache fixtureのHTTPとファイル照合。さくら実機のPHP版変更・旧実アプリの稼働復旧は未検証'];
}catch(Throwable $e){$out=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-620':'TS-621','php'=>PHP_VERSION,'verdict'=>'FAIL','checks'=>$checks,'error'=>$e->getMessage()];}
echo ftb_json($out);exit($out['verdict']==='PASS'?0:1);
