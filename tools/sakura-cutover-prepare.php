<?php
// CLI-only preparation. All writes are beneath a NEW owner-only HOME directory.
// No stop/apply/resume operation is implemented in this tool.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/sakura-rehearsal-verify.php';
function ftcp_target($app,$rel) {
    ftb_path($rel);$parts=explode('/',$rel);$p=$app;
    foreach($parts as $i=>$part){
        $p.='/'.$part;clearstatcache(true,$p);$s=@lstat($p);
        if($s===false){return null;}
        ftb_need(($s['mode']&0170000)===($i===count($parts)-1?0100000:0040000),'Unsupported production target or parent.');
    }
    return ftb_entry($p);
}
function ftcp_stage($root,$rel,$bytes) {
    ftb_path($rel);$dir=dirname($root.'/'.$rel);
    if(!is_dir($dir)){ftb_need(mkdir($dir,0700,true),'Private staging directory creation failed.');}
    ftb_write($root.'/'.$rel,$bytes);
}
function ftcp_prepare($homeArg,$id,&$created) {
    [$home,$web]=ftb_context($homeArg,rtrim($homeArg,'/').'/www');
    ftb_need(preg_match('/^ft-stage6-rehearsal-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/D',$id)===1,'Successful rehearsal identifier required.');
    $rehearsalPrivate=$home.'/.'.$id;
    ftb_need(realpath($rehearsalPrivate)===$rehearsalPrivate,'Canonical private rehearsal directory required.');
    $deployed=json_decode(ftb_read($rehearsalPrivate.'/deployment.json'),true,512,JSON_THROW_ON_ERROR);
    $rehearsal=$deployed['public']??'';
    ftb_need(is_string($rehearsal) && basename($rehearsal)===$id,'Recorded rehearsal location required.');
    $verified=ftrv_run($rehearsal,$home,$web);
    ftb_need($verified['verdict']==='PASS','Rehearsal verification is not PASS; preparation stopped.');
    $app=dirname($rehearsal);
    ftb_need(realpath($app)===$app && ftb_inside($app,$web) && !preg_match('/^ft-(?:stage[0-9]|php-check)-/',basename($app)),'Original application parent required.');
    $manifest=json_decode(ftb_read(__DIR__.'/rehearsal-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    ftb_need(count($manifest['files'])===46 && $manifest===$deployed['application'],'Accepted 46-file application manifest required.');
    $next=[];
    foreach($manifest['files'] as $rel=>$sha){
        ftb_path($rel);$data=ftb_read(__DIR__.'/app/'.$rel);ftb_need(hash('sha256',$data)===$sha,'Candidate application checksum mismatch.');$next[$rel]=$data;
    }
    $config=ftb_read($app.'/config.php');$index=ftb_read($app.'/index.php');$css=ftb_read($app.'/css/ft.css');$auth=ftb_read($app.'/.htaccess');
    $presentation=ftr_presentation(str_replace("\r\n","\n",$index),str_replace("\r\n","\n",$css));
    ftb_need($presentation===$deployed['presentation'],'Production display changed since the accepted rehearsal; review required.');
    // Validate the existing path/authentication configuration before allocating private output.
    ftr_config($config,$presentation,$home.'/.unused-validation-path');$publicAuth=ftr_auth($auth,$home);
    // Deny legacy diagnostic/administration files over HTTP while retaining their CLI locations.
    $publicAuth.="<FilesMatch \"^(?:phpinfo\\.php|users\\.sh|users\\.txt)$\">\n Require all denied\n</FilesMatch>\n";
    ftb_need(realpath($app.'/files')===$app.'/files','Canonical production files directory required.');
    $managed=ftb_tree($app.'/files');
    foreach($managed as $rel=>$entry){if($rel!=='.'){ftb_need(!in_array(strtolower(basename($rel)),['.htaccess','.user.ini','php.ini'],true),'Managed server-control files require review.');}}
    $targets=array_merge(array_keys($next),['config.php','.htaccess','files/.htaccess']);$before=[];
    foreach($targets as $rel){$before[$rel]=ftcp_target($app,$rel);}
    $original=json_decode(ftb_read($home.'/'.$deployed['backup'].'/manifest.json'),true,512,JSON_THROW_ON_ERROR)['entries'];
    foreach($before as $rel=>$entry){ftb_need($entry===($original[$rel]??null),'Production source changed since the accepted rehearsal; review required.');}
    foreach(['config.php'=>$config,'index.php'=>$index,'css/ft.css'=>$css,'.htaccess'=>$auth] as $rel=>$bytes){
        ftb_need(hash('sha256',$bytes)===($before[$rel]['sha256']??null),'Production source changed while reading.');
    }
    // The unchanged favicon remains at its existing public URL.
    $favicon=ftcp_target($app,'favicon.ico');ftb_need($favicon!==null,'Existing favicon required.');
    ftb_need($favicon===($original['favicon.ico']??null),'Production favicon changed since the accepted rehearsal; review required.');
    ftb_need(preg_match('/^\s*AuthUserFile\s+(.+)$/mi',$auth,$authMatch)===1,'Existing Basic credential reference required.');
    $authFile=trim(trim($authMatch[1]),'"');$authEntry=ftb_entry($authFile);
    ftb_need($authEntry['type']==='file','Regular Basic credential file required.');
    ftb_need(disk_free_space($home)>array_sum(array_map('strlen',$next))*3+16777216,'Insufficient preparation space; account quota is separate.');
    $created=ftb_new($home,$web,'cutover');ftb_mkdir($created.'/new');ftb_mkdir($created.'/before');
    ftb_write($created.'/config.php',ftr_config($config,$presentation,$created));ftb_write($created.'/php-error.log','');
    $next['config.php']="<?php\nrequire ".var_export($created.'/config.php',true).";\n";
    $next['.htaccess']=$publicAuth;
    $next['files/.htaccess']="Options -Indexes -ExecCGI\n<FilesMatch \"(?i:\\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml)(?:\\.|$))|^\\.\">\n Require all denied\n</FilesMatch>\n";
    $newEntries=[];
    foreach($next as $rel=>$bytes){
        ftcp_stage($created.'/new',$rel,$bytes);
        $newEntries[$rel]=['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes),'mode'=>$rel==='config.php'?0600:0644];
        if($before[$rel]!==null){
            $old=ftb_read($app.'/'.$rel);ftb_need(hash('sha256',$old)===$before[$rel]['sha256'],'Production target changed during preparation.');
            ftcp_stage($created.'/before',$rel,$old);
        }
    }
    // Future stop operation payload only; it is NOT installed by this preparation tool.
    $maintenance="<?php\nhttp_response_code(503);\nheader('Content-Type: text/plain; charset=UTF-8');\nheader('Cache-Control: no-store');\nheader('Retry-After: 300');\necho 'File Thingie is temporarily unavailable for maintenance.', PHP_EOL;\n";
    ftb_write($created.'/maintenance-index.php',$maintenance);
    foreach($before as $rel=>$entry){ftb_need(ftcp_target($app,$rel)===$entry,'Production target changed during preparation.');}
    ftb_need(ftb_tree($app.'/files')===$managed && ftcp_target($app,'favicon.ico')===$favicon && ftb_entry($authFile)===$authEntry,'Production data or authentication changed during preparation.');
    $plan=['format'=>1,'phase'=>'PREPARED_ONLY','created_at_utc'=>gmdate('c'),'application'=>$app,'home'=>$home,'web_root'=>$web,
        'private'=>$created,'rehearsal'=>$rehearsal,'application_manifest'=>$manifest,'before'=>$before,'new'=>$newEntries,
        'managed_before'=>$managed,'retained_favicon'=>$favicon,'auth_file'=>$authFile,'auth_entry'=>$authEntry,'presentation'=>$presentation,
        'private_config_sha256'=>hash_file('sha256',$created.'/config.php'),'maintenance_index_sha256'=>hash('sha256',$maintenance),
        'final_backup'=>null,'writer_scope'=>'UI only per operator; in-flight activity must be checked before stop',
        'cutover_operations_implemented'=>false];
    $encoded=ftb_json($plan);ftb_write($created.'/plan.json',$encoded);
    ftb_write($created.'/COMPLETE.json',ftb_json(['plan_sha256'=>hash('sha256',$encoded)]));
    $summary=['scope'=>'段階6 本番切替前の非公開資材準備。本番書込み・停止・切替なし','checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,
        'verdict'=>'PREPARED','preparation_id'=>basename($created),'rehearsal_internal_checks'=>$verified['verdict'],
        'application_files'=>count($manifest['files']),'planned_target_files'=>count($next),'planned_existing_targets'=>count(array_filter($before,fn($e)=>$e!==null)),
        'planned_new_targets'=>count(array_filter($before,fn($e)=>$e===null)),'current_managed_files'=>count(array_filter($managed,fn($e)=>$e['type']==='file')),
        'current_managed_bytes'=>array_sum(array_column($managed,'bytes')),'source_unchanged_during_preparation'=>true,
        'configuration_executed'=>false,'production_written'=>false,'maintenance_started'=>false,'cutover_performed'=>false,
        'final_stopped_backup'=>'NOT RUN','basic_credential_file_changed'=>false,
        'privacy'=>'設定・認証情報・保存ファイル名・フルパスの非出力'];
    ftb_write($created.'/summary.json',ftb_json($summary));return $summary;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__){return;}
$created=null;
try{
    ftb_need($argc===3,'Usage: php sakura-cutover-prepare.php HOME REHEARSAL_ID');
    echo ftb_json(ftcp_prepare($argv[1],$argv[2],$created));
}catch(Throwable $e){
    echo ftb_json(['verdict'=>'INCOMPLETE','message'=>get_class($e)===RuntimeException::class?$e->getMessage():'Metadata or runtime error.',
        'preparation_id'=>$created?basename($created):null,'production_written'=>false,'configuration_executed'=>false,'maintenance_started'=>false,'cutover_performed'=>false]);exit(2);
}
