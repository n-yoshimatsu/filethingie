<?php
// Local synthetic application only. Fault injection and direct fixture changes never target Sakura.
if(PHP_SAPI!=='cli'||$argc!==2){exit(2);}require $argv[1].'/sakura-cutover.php';
$local=json_decode(file_get_contents('/tmp/ft-rehearsal-local/state.json'),true);$home='/tmp/ft-rehearsal-local/home';$app=$home.'/www/app';
ftb_need($local['original']===$app,'Local fixture required.');
ftb_need(posix_geteuid()===fileowner($home),'Run as the local Web account, matching the Sakura account model.');
$case=str_starts_with(PHP_VERSION,'8.3.')?'TS-616':'TS-617';$checks=[];
function ct($name,$ok){global $checks;$checks[]=['name'=>$name,'verdict'=>$ok?'PASS':'FAIL'];if(!$ok){throw new RuntimeException($name);}}
function rejected($name,$call){$yes=false;try{$call();}catch(RuntimeException $e){$yes=true;}ct($name,$yes);}
function http_local($auth=true,$post=false,$relative='index.php'){
    $h=curl_init('https://127.0.0.1/app/'.$relative);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_TIMEOUT=>15];
    if($auth){$options[CURLOPT_USERPWD]='fixture:local-rehearsal-only';}if($post){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]='';}
    curl_setopt_array($h,$options);$body=curl_exec($h);$code=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);return [$code,$body];
}
function restored_fixture($app,$before,$oldIndex){
    file_put_contents($app.'/index.php',$oldIndex);chmod($app.'/index.php',$before['index.php']['mode']);touch($app.'/index.php',$before['index.php']['mtime']);
    foreach(array_reverse($before,true) as $rel=>$e){if($e['type']==='directory'){touch($rel==='.'?$app:$app.'/'.$rel,$e['mtime']);}}
    clearstatcache();
}
try{
    $before=ftb_tree($app);$oldIndex=ftb_read($app.'/index.php');$created=null;ftcp_prepare($home,basename($local['public']),$created);$p=ftc_load($home,basename($created));$s=ftc_state($p);
    ct('準備状態の読取り',$s['phase']==='PREPARED');
    rejected('利用終了確認なしの停止拒否',function()use($p,&$s){ftc_stop($p,$s,false);});ct('拒否後の原本不変',ftb_tree($app)===$before);
    rejected('配置前の再開拒否',function()use($p,&$s){ftc_resume($p,$s);});
    $asset=$p['private'].'/new/css/ft.css';$bytes=ftb_read($asset);
    try{file_put_contents($asset,$bytes.'tamper');rejected('候補改変時の停止前拒否',function()use($p,&$s){ftc_stop($p,$s,true);});ct('候補改変拒否で原本不変',ftb_tree($app)===$before);}
    finally{file_put_contents($asset,$bytes);}
    ftc_stop($p,$s,true);ct('停止状態と503入口の配置',$s['phase']==='STOPPED'&&ftc_is_maintenance($p));
    [$code,$body]=http_local();ct('Basic認証後の実HTTP GET 503',$code===503&&trim($body)==='File Thingie is temporarily unavailable for maintenance.');
    [$code,$body]=http_local(true,true);ct('空POSTの実HTTP 503',$code===503&&str_contains($body,'temporarily unavailable'));
    ct('停止中のBasic認証継続',http_local(false)[0]===401);
    rejected('Web停止確認なしのバックアップ拒否',function()use($p,&$s){ftc_backup($p,$s,false);});
    ftc_backup($p,$s,true);$m=ftc_final_backup($p,$s);ct('停止時バックアップと最新保存データ一致',$s['phase']==='BACKED_UP'&&ftc_data($p)===ftc_backup_data($m));
    ct('停止用入口と停止前入口の別保全',$m['entries']['index.php']['sha256']===$p['maintenance_index_sha256']&&hash_file('sha256',$p['private'].'/before/index.php')===$before['index.php']['sha256']);
    $n=0;try{ftc_apply($p,$s,function()use(&$n){if(++$n===3){throw new RuntimeException('Synthetic interruption after three writes.');}});}catch(RuntimeException $e){ct('3ファイル配置後の中断注入',$n===3&&$s['phase']==='APPLYING');}
    ct('途中中断後も実HTTP 503',http_local()[0]===503);ct('途中中断で保存データ不変',ftc_data($p)===ftc_backup_data($m));
    rejected('部分配置からの再開拒否',function()use($p,&$s){ftc_resume($p,$s);});
    ftc_apply($p,$s);ct('中断後の再実行で48ファイル配置完了',$s['phase']==='APPLIED');ct('配置後も実HTTP 503',http_local()[0]===503);
    foreach(['config.php','config.sample.php','.htaccess','files/','phpinfo.php','users.sh','users.txt'] as $rel){ct('Basic通過後のHTTP保護 '.$rel,http_local(true,false,$rel)[0]===403);}
    $lockPath=$p['private'].'/operations.lock';ftb_write($lockPath,'');$lock=fopen($lockPath,'r+b');flock($lock,LOCK_EX);
    try{rejected('同時操作の排他',fn()=>ftc_execute('status',$home,basename($created)));}finally{flock($lock,LOCK_UN);fclose($lock);}
    ftc_resume($p,$s);ct('入口再開後の実HTTP 200',$s['phase']==='LIVE'&&http_local()[0]===200);ct('再開後CLIの全照合',ftc_verify($p,$s)['verdict']==='PASS');
    // Synthetic post-activation data as component input: rollback must never replace it with a backup.
    $extra=$app.'/files/after-activation.txt';file_put_contents($extra,'new data after activation');$currentData=ftc_data($p);
    ct('停止時データとの違いの検出',ftc_verify($p,$s)['verdict']==='FAIL');
    ftc_rollback($p,$s);ct('復元後の停止維持',$s['phase']==='RESTORED_STOPPED'&&http_local()[0]===503);
    ct('復元時の再開後データ保持',ftc_data($p)===$currentData&&file_get_contents($extra)==='new data after activation');
    $matched=true;foreach($p['before'] as $rel=>$e){if($rel!=='index.php'&&ftcp_target($app,$rel)!==$e){$matched=false;}}ct('変更前48対象の復元と新規対象の除去',$matched);
    unlink($extra);restored_fixture($app,$before,$oldIndex);ct('自己検証の原本後片付け',ftb_tree($app)===$before);
    // Fresh end-to-end preparation for the following normal Chrome UI case.
    $fresh=null;ftcp_prepare($home,basename($local['public']),$fresh);$p=ftc_load($home,basename($fresh));$s=ftc_state($p);ftc_stop($p,$s,true);
    ct('2回目の実HTTP停止確認',http_local()[0]===503);ftc_backup($p,$s,true);ftc_apply($p,$s);ftc_resume($p,$s);
    ct('通常経路の新規切替とCLI照合',ftc_verify($p,$s)['verdict']==='PASS'&&http_local()[0]===200);
    $url=preg_replace('~/ft-stage6-rehearsal-[^/]+/index.php$~','/index.php',$local['url']);
    ftb_write('/tmp/ft-cutover-browser-state.json',ftb_json(['home'=>$home,'preparation_id'=>basename($fresh),'url'=>$url,'private'=>$fresh,'public'=>$app]));
    $out=['case_id'=>$case,'name'=>'切替・中断・復元の自己検証','php'=>PHP_VERSION,'verdict'=>'PASS','checks'=>$checks];
}catch(Throwable $e){$out=['case_id'=>$case,'name'=>'切替・中断・復元の自己検証','php'=>PHP_VERSION,'verdict'=>'FAIL','checks'=>$checks,'error'=>$e->getMessage()];}
echo ftb_json($out);exit($out['verdict']==='PASS'?0:1);
