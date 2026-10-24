<?php
// CLI-only, journaled cutover. Managed user files and the Basic credential file are never written.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/sakura-cutover-prepare.php';
function ftc_maintenance(){return "<?php\nhttp_response_code(503);\nheader('Content-Type: text/plain; charset=UTF-8');\nheader('Cache-Control: no-store');\nheader('Retry-After: 300');\necho 'File Thingie is temporarily unavailable for maintenance.', PHP_EOL;\n";}
function ftc_private($path){$e=ftb_entry($path);ftb_need($e['type']==='file'&&$e['mode']===0600,'Private metadata must be a regular 0600 file.');return ftb_read($path);}
function ftc_load($homeArg,$id){
    [$home,$web]=ftb_context($homeArg,rtrim($homeArg,'/').'/www');
    ftb_need(preg_match('/^\.ft-stage6-cutover-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/D',$id)===1,'Prepared cutover identifier required.');
    $private=$home.'/'.$id;ftb_need(realpath($private)===$private&&(fileperms($private)&0777)===0700&&!ftb_inside($private,$web),'Private canonical 0700 directory required.');
    $raw=ftc_private($private.'/plan.json');$complete=json_decode(ftc_private($private.'/COMPLETE.json'),true,512,JSON_THROW_ON_ERROR);
    ftb_need(hash('sha256',$raw)===($complete['plan_sha256']??null),'Preparation plan checksum mismatch.');$p=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    ftb_need(($p['format']??null)===1&&$p['private']===$private&&$p['home']===$home&&$p['web_root']===$web,'Foreign or unsupported preparation.');
    $app=$p['application'];ftb_need(realpath($app)===$app&&ftb_inside($app,$web)&&dirname($p['rehearsal'])===$app,'Canonical production parent required.');
    $fixed=json_decode(ftb_read(__DIR__.'/rehearsal-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    ftb_need($fixed===$p['application_manifest']&&count($fixed['files'])===46,'Accepted application manifest required.');
    $keys=array_merge(array_keys($fixed['files']),['config.php','.htaccess','files/.htaccess']);sort($keys);
    foreach(['before','new'] as $key){$actual=array_keys($p[$key]);sort($actual);ftb_need($actual===$keys,'Unexpected deployment targets.');}
    ftb_need($p['maintenance_index_sha256']===hash('sha256',ftc_maintenance()),'Known maintenance payload required.');
    ftb_need(stat($app)['dev']===stat($private)['dev'],'Same filesystem required for atomic single-file replacement.');
    foreach($keys as $rel){ftcp_target($app,$rel);}
    return $p;
}
function ftc_state($p){
    $file=$p['private'].'/cutover-state.json';
    if(!file_exists($file)&&!is_link($file)){return ['phase'=>'PREPARED','events'=>0,'created_directories'=>[],'backup_id'=>null];}
    $s=json_decode(ftc_private($file),true,512,JSON_THROW_ON_ERROR);
    ftb_need(in_array($s['phase']??'', ['PREPARED','STOPPING','STOPPED','BACKING_UP','BACKED_UP','APPLYING','APPLIED','RESUMING','LIVE','HOLDING','HELD','RESTORING','RESTORED_STOPPED'],true),'Invalid cutover state.');return $s;
}
function ftc_write_private($p,$name,$bytes){
    $target=$p['private'].'/'.$name;if(file_exists($target)||is_link($target)){ftc_private($target);}
    $tmp=$p['private'].'/.state-'.bin2hex(random_bytes(6));ftb_write($tmp,$bytes);
    ftb_need(rename($tmp,$target),'Private state update failed.');
}
function ftc_save($p,&$s,$event){
    $s['updated_at_utc']=gmdate('c');$s['events']=($s['events']??0)+1;
    ftc_write_private($p,'cutover-state.json',ftb_json($s));
    $log=$p['private'].'/cutover-journal.jsonl';if(file_exists($log)||is_link($log)){ftc_private($log);}else{ftb_write($log,'');}
    $h=fopen($log,'ab');ftb_need($h!==false,'Journal open failed.');
    try{$line=json_encode(['at'=>$s['updated_at_utc'],'event'=>$event,'phase'=>$s['phase']],JSON_THROW_ON_ERROR)."\n";ftb_need(fwrite($h,$line)===strlen($line)&&fflush($h),'Journal write failed.');if(function_exists('fsync')){ftb_need(fsync($h),'Journal sync failed.');}}finally{fclose($h);}
}
function ftc_payload($p){
    foreach($p['before'] as $rel=>$e){if($e!==null){ftb_need($e['type']==='file'&&is_int($e['mode'])&&$e['mode']>=0&&$e['mode']<=0777,'Invalid prior-file metadata.');ftb_need(hash('sha256',ftc_private($p['private'].'/before/'.$rel))===$e['sha256'],'Prior-file backup checksum mismatch.');}}
    $expected=$p['application_manifest']['files'];
    $config=ftr_config(ftc_private($p['private'].'/before/config.php'),$p['presentation'],$p['private']);
    ftb_need(hash('sha256',$config)===$p['private_config_sha256']&&ftc_private($p['private'].'/config.php')===$config,'Private configuration mismatch.');
    $expected['config.php']=hash('sha256',"<?php\nrequire ".var_export($p['private'].'/config.php',true).";\n");
    $auth=ftr_auth(ftc_private($p['private'].'/before/.htaccess'),$p['home'])."<FilesMatch \"^(?:phpinfo\\.php|users\\.sh|users\\.txt)$\">\n Require all denied\n</FilesMatch>\n";
    $expected['.htaccess']=hash('sha256',$auth);
    $expected['files/.htaccess']=hash('sha256',"Options -Indexes -ExecCGI\n<FilesMatch \"(?i:\\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml)(?:\\.|$))|^\\.\">\n Require all denied\n</FilesMatch>\n");
    foreach($expected as $rel=>$sha){$bytes=ftc_private($p['private'].'/new/'.$rel);$entry=$p['new'][$rel];ftb_need(hash('sha256',$bytes)===$sha&&$entry['sha256']===$sha&&$entry['bytes']===strlen($bytes)&&$entry['mode']===($rel==='config.php'?0600:0644),'Prepared target content or mode mismatch.');}
    ftb_need(ftc_private($p['private'].'/maintenance-index.php')===ftc_maintenance(),'Prepared maintenance payload mismatch.');
}
function ftc_new_match($p,$rel,$e){$want=$p['new'][$rel];return $e!==null&&$e['type']==='file'&&$e['sha256']===$want['sha256']&&$e['bytes']===$want['bytes']&&$e['mode']===$want['mode'];}
function ftc_is_maintenance($p){$e=ftcp_target($p['application'],'index.php');return $e!==null&&$e['sha256']===$p['maintenance_index_sha256']&&$e['mode']===0644;}
function ftc_identity($p){ftb_need(ftb_entry($p['auth_file'])===$p['auth_entry'],'Basic credential file changed; review required.');ftb_need(ftcp_target($p['application'],'favicon.ico')===$p['retained_favicon'],'Favicon changed; review required.');}
function ftc_original($p,$allowMaintenance=false){foreach($p['before'] as $rel=>$e){if($rel==='index.php'&&$allowMaintenance&&ftc_is_maintenance($p)){continue;}ftb_need(ftcp_target($p['application'],$rel)===$e,'Production source changed; no unreviewed overwrite.');}ftc_identity($p);}
function ftc_known_targets($p){foreach($p['before'] as $rel=>$e){$current=ftcp_target($p['application'],$rel);if($rel==='index.php'&&ftc_is_maintenance($p)){continue;}ftb_need($current===$e||ftc_new_match($p,$rel,$current),'Unknown target change; operation stopped.');}}
function ftc_dirs($p,&$s,$rel){
    $parts=explode('/',dirname($rel));if($parts===['.']){return;}$sub='';
    foreach($parts as $part){$sub=$sub===''?$part:$sub.'/'.$part;$dir=$p['application'].'/'.$sub;
        if(file_exists($dir)||is_link($dir)){ftb_need(!is_link($dir)&&is_dir($dir),'Unsupported target parent.');continue;}
        if(!in_array($sub,$s['created_directories'],true)){$s['created_directories'][]=$sub;ftc_save($p,$s,'directory-intent:'.$sub);}
        ftb_need(mkdir($dir,0755),'Target directory creation failed.');ftb_need(chmod($dir,0755),'Target directory mode failed.');
    }
}
function ftc_replace($p,&$s,$rel,$bytes,$mode,$expected,$mtime=null){
    ftb_need(ftcp_target($p['application'],$rel)===$expected,'Target changed before replacement.');ftc_dirs($p,$s,$rel);
    $dest=$p['application'].'/'.$rel;ftb_need(stat(dirname($dest))['dev']===stat($p['private'])['dev'],'Target filesystem differs.');
    $tmp=$p['private'].'/.write-'.bin2hex(random_bytes(6));ftb_write($tmp,$bytes);
    try{
        ftb_need(chmod($tmp,$mode),'Replacement permission failed.');
        $stamp=$mtime??max(time(),($expected['mtime']??0)+1);ftb_need(touch($tmp,$stamp),'Replacement timestamp failed.');
        $h=fopen($tmp,'r+b');ftb_need($h!==false,'Replacement sync open failed.');try{if(function_exists('fsync')){ftb_need(fsync($h),'Replacement sync failed.');}}finally{fclose($h);}
        ftb_need(ftcp_target($p['application'],$rel)===$expected,'Target changed before atomic rename.');
        ftc_save($p,$s,'replace-intent:'.$rel);ftb_need(rename($tmp,$dest),'Atomic replacement failed.');ftc_save($p,$s,'replaced:'.$rel);
    }finally{if(file_exists($tmp)&&!is_link($tmp)){unlink($tmp);}}
}
function ftc_data($p){$e=ftb_tree($p['application'].'/files');if(isset($e['.htaccess'])){ftb_need(ftc_new_match($p,'files/.htaccess',$e['.htaccess']),'Unexpected managed protection file.');unset($e['.htaccess']);}unset($e['.']['mtime']);return $e;}
function ftc_backup_data($m){$e=[];foreach($m['entries'] as $rel=>$v){if($rel==='files'||str_starts_with($rel,'files/')){$e[$rel==='files'?'.':substr($rel,6)]=$v;}}unset($e['.']['mtime']);ksort($e);return $e;}
function ftc_final_backup($p,$s){ftb_need(is_string($s['backup_id']??null),'Verified stopped backup required.');[$dir,$m]=ftb_load_backup($p['home'].'/'.$s['backup_id'],$p['home'],$p['web_root']);ftb_need($m['application']===$p['application']&&$m['entries']['index.php']['sha256']===$p['maintenance_index_sha256'],'Stopped backup application mismatch.');return $m;}
function ftc_ready($p,$s,$withData=true){
    ftc_payload($p);ftc_identity($p);ftb_need(ftc_is_maintenance($p),'Maintenance entry required.');
    foreach($p['new'] as $rel=>$e){if($rel!=='index.php'){ftb_need(ftc_new_match($p,$rel,ftcp_target($p['application'],$rel)),'Application is not completely staged.');}}
    if($withData){$m=ftc_final_backup($p,$s);ftb_need(ftc_data($p)===ftc_backup_data($m),'Managed data changed since the stopped backup.');}
}
function ftc_stop($p,&$s,$idle){
    ftb_need($idle,'Explicit writers-idle confirmation required.');ftb_need(in_array($s['phase'],['PREPARED','STOPPING','STOPPED'],true),'Stop requires prepared or stopped state.');
    ftc_payload($p);ftc_original($p,true);$s['phase']='STOPPING';$s['writers_idle_confirmed']=true;ftc_save($p,$s,'stop-intent');
    if(!ftc_is_maintenance($p)){ftc_replace($p,$s,'index.php',ftc_maintenance(),0644,ftcp_target($p['application'],'index.php'));}
    ftb_need(ftc_is_maintenance($p),'Maintenance entry installation failed.');$s['phase']='STOPPED';$s['stopped_at_utc']=gmdate('c');ftc_save($p,$s,'stopped');
}
function ftc_backup($p,&$s,$web503){
    ftb_need($web503,'Explicit observed Web 503 confirmation required.');ftb_need(in_array($s['phase'],['STOPPED','BACKING_UP','BACKED_UP'],true),'Backup requires stopped state.');
    ftc_payload($p);ftc_original($p,true);ftb_need(ftc_is_maintenance($p),'Maintenance entry required.');
    $s['phase']='BACKING_UP';$s['web_503_confirmed']=true;ftc_save($p,$s,'backup-start');$created=null;
    try{$r=ftb_backup($p['application'],$p['home'],$p['web_root'],$created);$s['backup_id']=$r['backup_id'];$m=ftc_final_backup($p,$s);ftb_need(ftc_data($p)===ftc_backup_data($m),'Data changed after the backup.');}
    catch(Throwable $e){$s['partial_backup_id']=$created?basename($created):null;ftc_save($p,$s,'backup-incomplete');throw $e;}
    $s['phase']='BACKED_UP';$s['backup_completed_at_utc']=gmdate('c');ftc_save($p,$s,'backup-verified');
}
function ftc_apply($p,&$s,$afterWrite=null){
    ftb_need(in_array($s['phase'],['BACKED_UP','APPLYING','APPLIED'],true),'Apply requires a stopped backup.');ftc_payload($p);ftc_identity($p);
    ftb_need(ftc_is_maintenance($p),'Maintenance entry required.');ftc_known_targets($p);
    $m=ftc_final_backup($p,$s);ftb_need(ftc_data($p)===ftc_backup_data($m),'Managed data changed since the stopped backup.');
    $s['phase']='APPLYING';ftc_save($p,$s,'apply-start');
    $order=array_unique(array_merge(['.htaccess','files/.htaccess'],array_keys($p['new'])));
    foreach($order as $rel){if($rel==='index.php'){continue;}$current=ftcp_target($p['application'],$rel);if(ftc_new_match($p,$rel,$current)){continue;}
        ftc_replace($p,$s,$rel,ftc_private($p['private'].'/new/'.$rel),$p['new'][$rel]['mode'],$current);
        // Test-only fault injection through the library interface; never accepted by the CLI.
        if($afterWrite!==null){$afterWrite($rel);}
    }
    ftc_ready($p,$s);$s['phase']='APPLIED';ftc_save($p,$s,'applied-still-stopped');
}
function ftc_resume($p,&$s){
    ftb_need(in_array($s['phase'],['APPLIED','RESUMING','HELD'],true),'Resume requires a fully verified application.');
    if($s['phase']==='RESUMING'&&ftc_new_match($p,'index.php',ftcp_target($p['application'],'index.php'))){
        ftc_payload($p);ftc_identity($p);foreach($p['new'] as $rel=>$e){ftb_need(ftc_new_match($p,$rel,ftcp_target($p['application'],$rel)),'Interrupted activation is not complete.');}
        $s['phase']='LIVE';ftc_save($p,$s,'activation-journal-recovered');return;
    }
    ftc_ready($p,$s);ftb_need(ftc_private($p['private'].'/php-error.log')==='','PHP log must be reviewed before activation.');
    $s['phase']='RESUMING';ftc_save($p,$s,'resume-intent');
    ftc_replace($p,$s,'index.php',ftc_private($p['private'].'/new/index.php'),0644,ftcp_target($p['application'],'index.php'));
    $s['phase']='LIVE';$s['resumed_at_utc']=gmdate('c');ftc_save($p,$s,'live-awaiting-web-acceptance');
}
function ftc_hold($p,&$s){
    ftb_need($s['phase']!=='PREPARED','Use stop for initial maintenance.');
    $current=ftcp_target($p['application'],'index.php');ftb_need($current===$p['before']['index.php']||ftc_new_match($p,'index.php',$current)||ftc_is_maintenance($p),'Unknown entry script; no overwrite.');
    $s['phase']='HOLDING';ftc_save($p,$s,'hold-intent');if(!ftc_is_maintenance($p)){ftc_replace($p,$s,'index.php',ftc_maintenance(),0644,$current);}
    $s['phase']='HELD';ftc_save($p,$s,'held');
}
function ftc_rollback($p,&$s){
    ftb_need(in_array($s['phase'],['STOPPED','BACKING_UP','BACKED_UP','APPLYING','APPLIED','RESUMING','LIVE','HOLDING','HELD','RESTORING','RESTORED_STOPPED'],true),'No stopped or changed deployment to restore.');
    ftc_hold($p,$s);ftc_payload($p);ftc_known_targets($p);ftc_identity($p);$beforeData=ftc_data($p);
    $s['phase']='RESTORING';ftc_save($p,$s,'restore-start');
    foreach(array_reverse(array_keys($p['before'])) as $rel){if($rel==='index.php'){continue;}$current=ftcp_target($p['application'],$rel);$old=$p['before'][$rel];if($current===$old){continue;}
        ftb_need(ftc_new_match($p,$rel,$current),'Unknown target change; restore stopped.');
        if($old===null){ftc_save($p,$s,'remove-created-intent:'.$rel);ftb_need(unlink($p['application'].'/'.$rel),'Could not remove created target.');ftc_save($p,$s,'removed-created:'.$rel);}
        else{ftc_replace($p,$s,$rel,ftc_private($p['private'].'/before/'.$rel),$old['mode'],$current,$old['mtime']);}
    }
    foreach(array_reverse($s['created_directories']) as $rel){ftb_path($rel);$dir=$p['application'].'/'.$rel;if(!file_exists($dir)&&!is_link($dir)){continue;}ftb_need(!is_link($dir)&&is_dir($dir),'Unsupported created directory.');$names=array_diff(scandir($dir),['.','..']);if(!$names){ftb_need(rmdir($dir),'Empty created directory removal failed.');}}
    ftc_original($p,true);ftb_need(ftc_data($p)===$beforeData,'Managed data changed during source restoration.');ftb_need(ftc_is_maintenance($p),'Restoration must remain stopped.');
    $s['phase']='RESTORED_STOPPED';ftc_save($p,$s,'sources-restored-data-preserved-still-stopped');
}
function ftc_verify($p,$s){
    ftb_need($s['phase']==='LIVE','Live state required for post-cutover verification.');ftc_payload($p);$checks=[];
    $add=function($name,$actual,$expected)use(&$checks){$checks[]=['name'=>$name,'actual'=>$actual,'expected'=>$expected,'verdict'=>$actual===$expected?'PASS':'FAIL'];};
    $bad=0;foreach($p['new'] as $rel=>$e){if(!ftc_new_match($p,$rel,ftcp_target($p['application'],$rel))){$bad++;}}$add('配置49ファイルの不一致数',$bad,0);
    $add('Basic認証ファイルの不変',ftb_entry($p['auth_file'])===$p['auth_entry'],true);$add('faviconの不変',ftcp_target($p['application'],'favicon.ico')===$p['retained_favicon'],true);
    $m=ftc_final_backup($p,$s);$add('保存データと停止時バックアップの一致',ftc_data($p)===ftc_backup_data($m),true);
    $add('PHPログのバイト数',strlen(ftc_private($p['private'].'/php-error.log')),0);
    $add('非公開設定0600',(fileperms($p['private'].'/config.php')&0777)===0600,true);$add('メンテナンス入口の解除',ftc_is_maintenance($p),false);
    return ['scope'=>'本番切替後のCLI照合。Web受入は別の記録','checks'=>$checks,'verdict'=>count(array_filter($checks,fn($c)=>$c['verdict']!=='PASS'))?'FAIL':'PASS','managed_files'=>count(array_filter(ftc_backup_data($m),fn($e)=>$e['type']==='file'))];
}
function ftc_execute($action,$home,$id,$flag=''){
    ftb_need(in_array($action,['status','stop','backup','apply','resume','hold','rollback','verify'],true),'Unsupported cutover action.');
    ftb_need(in_array($flag,['','--writers-idle','--web-503-confirmed'],true),'Unsupported confirmation.');
    ftb_need($flag===''||($action==='stop'&&$flag==='--writers-idle')||($action==='backup'&&$flag==='--web-503-confirmed'),'Confirmation does not match action.');
    $p=ftc_load($home,$id);$lockPath=$p['private'].'/operations.lock';if(file_exists($lockPath)||is_link($lockPath)){ftc_private($lockPath);}else{ftb_write($lockPath,'');}
    $lock=fopen($lockPath,'r+b');ftb_need($lock!==false&&flock($lock,LOCK_EX|LOCK_NB),'Another cutover operation is running.');
    try{
        $s=ftc_state($p);$verification=null;
        if($action==='stop'){ftc_stop($p,$s,$flag==='--writers-idle');}
        elseif($action==='backup'){ftc_backup($p,$s,$flag==='--web-503-confirmed');}
        elseif($action==='apply'){ftc_apply($p,$s);}
        elseif($action==='resume'){ftc_resume($p,$s);}
        elseif($action==='hold'){ftc_hold($p,$s);}
        elseif($action==='rollback'){ftc_rollback($p,$s);}
        elseif($action==='verify'){$verification=ftc_verify($p,$s);}
        return ['scope'=>'段階6 本番切替の状態管理','checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'action'=>$action,'preparation_id'=>$id,'phase'=>$s['phase'],
            'maintenance_index_on_disk'=>ftc_is_maintenance($p),'web_status'=>'別途HTTP確認が必要','final_backup_id'=>$s['backup_id']??null,
            'managed_user_files_written_by_tool'=>false,'basic_credential_file_written_by_tool'=>false,'verification'=>$verification,
            'verdict'=>$verification['verdict']??'COMPLETED'];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__){return;}
try{ftb_need(in_array($argc,[4,5],true),'Usage: php sakura-cutover.php ACTION HOME PREPARATION_ID [--writers-idle|--web-503-confirmed]');$r=ftc_execute($argv[1],$argv[2],$argv[3],$argv[4]??'');echo ftb_json($r);exit($r['verdict']==='FAIL'?1:0);}
catch(Throwable $e){echo ftb_json(['verdict'=>'INCOMPLETE','message'=>get_class($e)===RuntimeException::class?$e->getMessage():'Metadata or runtime error. Inspect status before the next operation.',
    'action'=>$argv[1]??null,'managed_user_files_written_by_tool'=>false,'basic_credential_file_written_by_tool'=>false,'instruction'=>'状態を確認し、停止中の入口を維持。未確認の再開なし']);exit(2);}
