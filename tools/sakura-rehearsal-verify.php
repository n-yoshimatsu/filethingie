<?php
// Read-only, CLI-only verification of an existing stage6 rehearsal. Never executes its config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/sakura-rehearsal.php';
function ftrv_check($name,$actual,$expected,&$checks) {
    $checks[]=['name'=>$name,'actual'=>$actual,'expected'=>$expected,'verdict'=>$actual===$expected?'PASS':'FAIL'];
}
function ftrv_run($publicArg,$homeArg,$webArg) {
    [$home,$web]=ftb_context($homeArg,$webArg);$public=realpath($publicArg);
    ftb_need($public!==false && $public===$publicArg && !is_link($publicArg) && ftb_inside($public,$web),'Canonical rehearsal directory required.');
    $id=basename($public);ftb_need(preg_match('/^ft-stage6-rehearsal-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/D',$id)===1,'Stage6 rehearsal directory required.');
    $private=$home.'/.'.$id;ftb_need(realpath($private)===$private && !ftb_inside($private,$web),'Private rehearsal directory required.');
    $m=json_decode(ftb_read($private.'/deployment.json'),true,512,JSON_THROW_ON_ERROR);
    ftb_need(($m['public']??null)===$public && ($m['private']??null)===$private,'Deployment location mismatch.');
    ftb_need(preg_match('/^\.ft-stage6-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/D',$m['backup']??'')===1,'Recorded preservation copy required.');
    [$backup,$bm]=ftb_load_backup($home.'/'.$m['backup'],$home,$web);
    ftb_need(dirname($public)===$bm['application'] && $bm['managed_relative']==='files','Original parent or managed root mismatch.');
    $checks=[];
    $manifest=json_decode(ftb_read(__DIR__.'/rehearsal-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    ftrv_check('配布manifestの一致',$m['application']===$manifest,true,$checks);
    $bad=0;foreach($manifest['files'] as $rel=>$sha){ftb_path($rel);$p=$public.'/'.$rel;if(is_link($p)||!is_file($p)||hash_file('sha256',$p)!==$sha){$bad++;}}
    ftrv_check('配備アプリファイルの不一致数',$bad,0,$checks);
    $entries=[];foreach($bm['entries'] as $rel=>$entry){if($rel==='files'||str_starts_with($rel,'files/')){$entries[$rel==='files'?'.':substr($rel,6)]=$entry;}}
    ftrv_check('保存データmanifestと保全コピーの一致',$m['managed_entries']===$entries,true,$checks);
    $actual=ftb_tree($public.'/files');$bad=0;
    foreach($entries as $rel=>$entry){
        $observed=$actual[$rel]??null;
        // Only the managed root mtime changes when adding policy/test folders. File mtimes remain mandatory.
        if($rel==='.' && is_array($observed)){$observed['mtime']=$entry['mtime'];}
        if($observed!==$entry){$bad++;}
    }
    ftrv_check('複製データの内容・権限・更新時刻の不一致数',$bad,0,$checks);
    $extra=array_diff(array_keys($actual),array_keys($entries),['.htaccess']);
    ftrv_check('試験データ等の残存エントリー数',count($extra),0,$checks);
    $presentation=ftr_presentation(str_replace("\r\n","\n",ftb_read($backup.'/app/index.php')),str_replace("\r\n","\n",ftb_read($backup.'/app/css/ft.css')));
    ftrv_check('表示設定とレビュー済み抽出値の一致',$m['presentation']===$presentation,true,$checks);
    $expectedConfig=ftr_config(ftb_read($backup.'/app/config.php'),$presentation,$private);
    ftrv_check('非公開設定の生成時内容との一致',ftb_read($private.'/config.php')===$expectedConfig,true,$checks);
    ftrv_check('公開設定の非公開設定参照一致',ftb_read($public.'/config.php')==="<?php\nrequire ".var_export($private.'/config.php',true).";\n",true,$checks);
    ftrv_check('Basic認証と公開設定保護の一致',ftb_read($public.'/.htaccess')===ftr_auth(ftb_read($backup.'/app/.htaccess'),$home),true,$checks);
    $policy="Options -Indexes -ExecCGI\n<FilesMatch \"(?i:\\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml)(?:\\.|$))|^\\.\">\n Require all denied\n</FilesMatch>\n";
    ftrv_check('直接ファイル保護設定の一致',ftb_read($public.'/files/.htaccess')===$policy,true,$checks);
    ftrv_check('faviconと保全コピーの一致',hash('sha256',ftb_read($public.'/favicon.ico'))===$bm['entries']['favicon.ico']['sha256'],true,$checks);
    ftrv_check('非公開親ディレクトリ0700',fileperms($private)&0777,0700,$checks);
    foreach(['config.php','php-error.log','deployment.json','summary.json'] as $rel){
        $e=ftb_entry($private.'/'.$rel);ftrv_check('非公開 '.$rel.' の通常ファイル・0600',$e['type']==='file' && $e['mode']===0600,true,$checks);
    }
    ftrv_check('公開設定参照ファイル0600',fileperms($public.'/config.php')&0777,0600,$checks);
    ftrv_check('PHPログのバイト数',filesize($private.'/php-error.log'),0,$checks);
    $forbidden=0;foreach(['phpinfo.php','php.ini','php.ini~','users.sh','users.txt','.htpasswd','deployment.json','php-error.log'] as $rel){if(file_exists($public.'/'.$rel)||is_link($public.'/'.$rel)){$forbidden++;}}
    ftrv_check('公開先への管理・旧診断ファイルの混入数',$forbidden,0,$checks);
    return ['case_id'=>'SA-606','scope'=>'段階6 Web受入後の配備・複製データ・設定・権限・PHPログの読取り専用照合',
        'checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'source_files'=>count($manifest['files']),
        'managed_files'=>count(array_filter($entries,fn($e)=>$e['type']==='file')),'checks'=>$checks,
        'verdict'=>count(array_filter($checks,fn($c)=>$c['verdict']!=='PASS'))?'FAIL':'PASS',
        'configuration_executed'=>false,'writes_performed'=>false,
        'scope_note'=>'管理ルート自身の更新時刻のみ比較対象外。試験フォルダーと保護設定の追加・削除による変更。ファイル内容・通常権限・ファイル更新時刻・既存子フォルダー更新時刻の比較',
        'privacy'=>'認証情報・設定本文・ログ本文・表示文字列・保存ファイル名・フルパスの非出力'];
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__) { return; }
try {
    ftb_need($argc===4,'Usage: php sakura-rehearsal-verify.php REHEARSAL_DIRECTORY HOME WEB_ROOT');
    $result=ftrv_run($argv[1],$argv[2],$argv[3]);echo ftb_json($result);exit($result['verdict']==='PASS'?0:1);
} catch(Throwable $e) {
    echo ftb_json(['case_id'=>'SA-606','verdict'=>'INCOMPLETE','message'=>get_class($e)===RuntimeException::class?$e->getMessage():'Metadata or runtime error.',
        'configuration_executed'=>false,'writes_performed'=>false]);exit(2);
}
