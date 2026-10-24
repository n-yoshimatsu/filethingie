<?php
// Verifier self-check on the existing disposable LOCAL fixture only; no Sakura access.
if(PHP_SAPI!=='cli' || $argc!==2){exit(2);}
require $argv[1].'/sakura-rehearsal-verify.php';
$state=json_decode(file_get_contents('/tmp/ft-rehearsal-local/state.json'),true);
$public=$state['public'];$private=$state['private'];
ftb_need(str_starts_with($public,'/tmp/ft-rehearsal-local/home/www/app/ft-stage6-rehearsal-'),'Local synthetic fixture required.');
$checks=[];
function selfcheck($name,$condition){global $checks;$checks[]=['name'=>$name,'verdict'=>$condition?'PASS':'FAIL'];if(!$condition){throw new RuntimeException($name);}}
function verify_local(){global $public;return ftrv_run($public,'/tmp/ft-rehearsal-local/home','/tmp/ft-rehearsal-local/home/www');}
function detects($file,$replacement,$label){
    $bytes=file_get_contents($file);$mtime=filemtime($file);
    try{
        file_put_contents($file,$replacement);$r=verify_local();
        $matches=array_values(array_filter($r['checks'],fn($c)=>$c['name']===$label));
        selfcheck('不一致検出 '.$label,$r['verdict']==='FAIL' && count($matches)===1 && $matches[0]['verdict']==='FAIL');
    }finally{file_put_contents($file,$bytes);touch($file,$mtime);clearstatcache();}
}
try{
    $before=ftb_tree($public);$privateBefore=ftb_tree($private);
    $r=verify_local();selfcheck('配置後の正常な人工fixture', $r['verdict']==='PASS' && count($r['checks'])===19);
    selfcheck('検証処理による公開・非公開ファイル変更なし',ftb_tree($public)===$before && ftb_tree($private)===$privateBefore);
    detects($public.'/index.php',"<?php // tamper\n",'配備アプリファイルの不一致数');
    detects($public.'/files/既存.txt','tamper','複製データの内容・権限・更新時刻の不一致数');
    detects($private.'/config.php',"<?php // tamper\n",'非公開設定の生成時内容との一致');
    detects($private.'/php-error.log','synthetic error','PHPログのバイト数');
    $mode=fileperms($private.'/config.php')&0777;
    try{chmod($private.'/config.php',0644);clearstatcache();$r=verify_local();selfcheck('設定権限の不一致検出',in_array('FAIL',array_column($r['checks'],'verdict'),true));}
    finally{chmod($private.'/config.php',$mode);clearstatcache();}
    $dirTime=filemtime($public.'/files');$extra=$public.'/files/self-check-leftover.txt';
    try{file_put_contents($extra,'extra');$r=verify_local();$c=array_values(array_filter($r['checks'],fn($c)=>$c['name']==='試験データ等の残存エントリー数'));selfcheck('試験ファイル残存の検出',$c[0]['actual']===1 && $c[0]['verdict']==='FAIL');}
    finally{unlink($extra);touch($public.'/files',$dirTime);clearstatcache();}
    try{symlink($public.'/files/既存.txt',$extra);$rejected=false;try{verify_local();}catch(RuntimeException $e){$rejected=true;}selfcheck('シンボリックリンクの拒否',$rejected);}
    finally{unlink($extra);touch($public.'/files',$dirTime);clearstatcache();}
    selfcheck('人工fixtureの後片付け・復元一致',ftb_tree($public)===$before && ftb_tree($private)===$privateBefore);
    selfcheck('復元後の再照合',verify_local()['verdict']==='PASS');
    $record=['scope'=>'CLI照合ツールのローカル自己検証。実機SA-606とは別の記録','php'=>PHP_VERSION,'verdict'=>'PASS','checks'=>$checks];
}catch(Throwable $e){$record=['scope'=>'CLI照合ツールのローカル自己検証','php'=>PHP_VERSION,'verdict'=>'FAIL','checks'=>$checks,'error'=>$e->getMessage()];}
echo ftb_json($record);exit($record['verdict']==='PASS'?0:1);
