<?php
// CLI-only preservation copy and restoration into a NEW private directory.
// No installed PHP/configuration/scripts are executed. No production writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
umask(0077);
require __DIR__.'/sakura-inventory.php';
ini_set('display_errors', '0');

function ftb_need($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function ftb_inside($path, $parent) { return str_starts_with($path, $parent.'/'); }
function ftb_path($path) {
    ftb_need(is_string($path) && $path !== '' && $path[0] !== '/' && !str_contains($path, "\0")
        && !array_intersect(explode('/', $path), ['', '.', '..']), 'Invalid relative entry in manifest.');
    return $path;
}
function ftb_read($path, $limit=16777216) {
    ftb_need(!is_link($path) && is_file($path) && is_readable($path) && filesize($path)<=$limit, 'Unreadable or unsupported metadata file.');
    $value=@file_get_contents($path);ftb_need($value!==false, 'Metadata read failed.');return $value;
}
function ftb_entry($path) {
    clearstatcache(true, $path);
    $s=@lstat($path);
    ftb_need($s!==false && in_array($s['mode']&0170000, [0040000,0100000], true), 'Symlink, special entry or missing source; no supported snapshot.');
    $file=($s['mode']&0170000)===0100000;
    $r=['type'=>$file?'file':'directory', 'mode'=>$s['mode']&0777, 'mtime'=>$s['mtime'], 'bytes'=>$file?$s['size']:0];
    // Special permission bits require manual review rather than silently losing them.
    ftb_need(($s['mode']&07000)===0, 'Special permission bits require manual review.');
    if ($file) {
        $hash=@hash_file('sha256',$path);ftb_need($hash!==false,'Source hash failed.');$r['sha256']=$hash;
        clearstatcache(true,$path);$after=@lstat($path);
        ftb_need($after!==false && $after['ino']===$s['ino'] && $after['dev']===$s['dev']
            && $after['size']===$s['size'] && $after['mtime']===$s['mtime'] && $after['ctime']===$s['ctime'], 'Source changed during hashing; retry when writes are stopped.');
    }
    return $r;
}
function ftb_tree($root) {
    $entries=['.'=>ftb_entry($root)];$stack=[''];
    while ($stack) {
        $rel=array_pop($stack);$dir=$root.($rel===''?'':'/'.$rel);$names=@scandir($dir);
        ftb_need($names!==false,'Directory scan failed.');
        foreach ($names as $name) {
            if ($name==='.'||$name==='..') { continue; }
            $key=$rel===''?$name:$rel.'/'.$name;ftb_path($key);
            $entries[$key]=ftb_entry($root.'/'.$key);
            ftb_need(count($entries)<=100000,'Snapshot entry limit exceeded.');
            if ($entries[$key]['type']==='directory') { $stack[]=$key; }
        }
    }
    ksort($entries);return $entries;
}
function ftb_mkdir($path) { ftb_need(@mkdir($path,0700),'Could not create a new private directory.'); }
function ftb_write($path,$bytes) {
    $h=@fopen($path,'xb');ftb_need($h!==false,'Could not exclusively create metadata.');
    try { ftb_need(fwrite($h,$bytes)===strlen($bytes) && fflush($h),'Metadata write failed (check account quota).'); }
    finally { fclose($h); }
    ftb_need(chmod($path,0600),'Metadata permission failed.');
}
function ftb_copy($source,$dest,$entry,$restore=false) {
    ftb_need(!is_link($source) && is_file($source),'Source is no longer a regular file.');
    $in=@fopen($source,'rb');$out=@fopen($dest,'xb');
    ftb_need($in!==false && $out!==false,'Could not open source or create a fresh destination.');
    try { ftb_need(@stream_copy_to_stream($in,$out)===$entry['bytes'] && fflush($out),'Copy failed (check account quota).'); }
    finally { fclose($in);fclose($out); }
    ftb_need(@hash_file('sha256',$dest)===$entry['sha256'],'Copied content hash mismatch.');
    ftb_need(chmod($dest,$restore?$entry['mode']:0600) && touch($dest,$entry['mtime']),'Copy metadata update failed.');
}
function ftb_copy_tree($source,$dest,$entries,$restore=false) {
    ftb_mkdir($dest);
    foreach ($entries as $rel=>$entry) {
        if ($rel==='.') { continue; }
        ftb_path($rel);
        if ($entry['type']==='directory') { ftb_mkdir($dest.'/'.$rel); }
        else { ftb_copy($source.'/'.$rel,$dest.'/'.$rel,$entry,$restore); }
    }
    // Apply directory modes only after writing their children.
    foreach (array_reverse($entries,true) as $rel=>$entry) {
        if ($entry['type']!=='directory') { continue; }
        $path=$rel==='.'?$dest:$dest.'/'.$rel;
        ftb_need(chmod($path,$restore?$entry['mode']:0700) && touch($path,$entry['mtime']),'Directory metadata update failed.');
    }
}
function ftb_json($value) { return json_encode($value,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"; }
function ftb_context($homeArg,$webArg) {
    $home=realpath($homeArg);$web=realpath($webArg);
    ftb_need($home && $web && is_dir($home) && is_dir($web) && ftb_inside($web,$home),'Existing home and descendant web root required.');
    return [$home,$web];
}
function ftb_new($home,$web,$kind) {
    $path=$home.'/.ft-stage6-'.$kind.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));
    ftb_need(!ftb_inside($path,$web),'Private destination must be outside web root.');ftb_mkdir($path);return $path;
}
function ftb_auth($app,$home,$web,$entries) {
    $files=[];$missing=0;$direct=0;$directories=[];
    for ($dir=$app;;$dir=dirname($dir)) {
        $directories[$dir]=true;if ($dir===$web) { break; }
    }
    foreach ($entries as $rel=>$entry) {
        if (basename($rel)==='.htaccess' && $entry['type']==='file') { $directories[dirname($app.'/'.$rel)]=true; }
    }
    foreach (array_keys($directories) as $dir) {
        $path=$dir.'/.htaccess';
        if (file_exists($path)||is_link($path)) {
            $content=ftb_read($path);$files[$path]=ftb_entry($path);
            if (preg_match_all('/^\s*(AuthUserFile|AuthGroupFile)\s+(?:"([^"\r\n]+)"|([^\s#]+))/mi',$content,$matches,PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $ref=$m[2]!==''?$m[2]:$m[3];
                    ftb_need(str_starts_with($ref,'/') && !str_contains($ref,'${'),'Non-literal authentication path requires review.');
                    if (!file_exists($ref) && !is_link($ref)) { $missing++;continue; }
                    $resolved=realpath($ref);
                    ftb_need($resolved && !is_link($ref) && ftb_inside($resolved,$home),'Authentication path outside home or symlink requires review.');
                    $files[$resolved]=ftb_entry($resolved);
                    ftb_need($files[$resolved]['type']==='file','Authentication reference must be a regular file.');
                    if ($dir===$app && strtolower($m[1])==='authuserfile') { $direct++; }
                }
            }
        }
    }
    ftb_need($direct>0,'No readable application-level AuthUserFile; preserve first, review scope manually.');
    ksort($files);return ['files'=>$files,'missing_references'=>$missing];
}
function ftb_compare_upstream($app) {
    $baseline=json_decode(ftb_read(__DIR__.'/upstream-manifest.json'),true,512,JSON_THROW_ON_ERROR);$result=[];
    foreach ($baseline['files'] as $rel=>$expected) {
        ftb_path($rel);$path=$app.'/'.$rel;
        if (!file_exists($path)) { $result[$rel]='MISSING';continue; }
        $value=ftb_read($path);
        $result[$rel]=hash('sha256',$value)===$expected['sha256']?'EXACT':
            (hash('sha256',str_replace("\r\n","\n",$value))===$expected['lf_sha256']?'LINE_ENDINGS_ONLY':'MODIFIED');
    }
    return ['upstream_commit'=>$baseline['upstream_commit'],'files'=>$result];
}
function ftb_backup($appArg,$homeArg,$webArg,&$created) {
    [$home,$web]=ftb_context($homeArg,$webArg);$app=realpath($appArg);
    ftb_need($app && is_dir($app) && !is_link($appArg) && ftb_inside($app,$web)
        && !preg_match('/^ft-(?:stage5|php-check)-/',basename($app)), 'Existing production application directory required.');
    $config=ft_inv_config(ftb_read($app.'/config.php',2097152));$dir=$config['settings']['DIR']??null;
    ftb_need($config['unknown']===0 && !$config['dynamic'] && $config['otherCalls']===0 && !$config['users'] && !$config['groups']
        && $dir && $dir['literal'] && is_string($dir['value']), 'Configuration requires review; no installed PHP was executed.');
    $managed=realpath($app.'/'.$dir['value']);
    ftb_need($dir['value']!=='' && $dir['value'][0]!=='/' && $managed && ftb_inside($managed,$app) && is_dir($managed),
        'Managed data must resolve inside this application for a complete copy.');
    $before=ftb_tree($app);$auth=ftb_auth($app,$home,$web,$before);$compared=ftb_compare_upstream($app);
    $bytes=array_sum(array_column($before,'bytes'))+array_sum(array_column($auth['files'],'bytes'));
    $free=disk_free_space($home);ftb_need($free!==false && $free>$bytes+16777216,'Insufficient filesystem free space (account quota is separate).');
    // No production writes before or after this point; only new private copies.
    $created=ftb_new($home,$web,'backup');ftb_copy_tree($app,$created.'/app',$before);ftb_mkdir($created.'/extra');
    $extras=[];
    foreach ($auth['files'] as $path=>$entry) {
        if (ftb_inside($path,$app)) { continue; }
        $id=sprintf('%04d',count($extras));ftb_copy($path,$created.'/extra/'.$id,$entry);
        $extras[$id]=['original_path'=>$path,'entry'=>$entry];
    }
    ftb_need(ftb_tree($app)===$before && ftb_auth($app,$home,$web,$before)===$auth,'Source changed while copying; incomplete copy, retry when writes are stopped.');
    $manifest=['format'=>1,'created_at_utc'=>gmdate('c'),'application'=>$app,'home'=>$home,'web_root'=>$web,
        'managed_relative'=>substr($managed,strlen($app)+1),'entries'=>$before,'extra'=>$extras,
        'auth_missing_references'=>$auth['missing_references'],'upstream_comparison'=>$compared,
        'consistency'=>'Before/after content, modes and mtime match; no atomic snapshot or writer lock.'];
    $encoded=ftb_json($manifest);ftb_write($created.'/manifest.json',$encoded);
    $summary=['scope'=>'段階6 公開領域外への保全コピー。切替用の書込み停止バックアップとは別の記録',
        'checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'backup_id'=>basename($created),'verdict'=>'PASS',
        'application_files'=>count(array_filter($before,fn($v)=>$v['type']==='file')),
        'application_bytes'=>array_sum(array_column($before,'bytes')),'extra_files'=>count($extras),
        'managed_data_inside_application'=>true,'managed_data_direct_child'=>!str_contains($manifest['managed_relative'],'/'),
        'managed_data_is_files_directory'=>$manifest['managed_relative']==='files',
        'basic_auth_missing_references'=>$auth['missing_references'],'upstream_comparison'=>$compared,
        'source_unchanged_during_copy'=>true,'copy_hashes_match'=>true,'backup_directory_mode'=>'0700',
        'source_modified'=>false,'configuration_executed'=>false,'account_quota_checked'=>false,
        'privacy'=>'バックアップ本文・manifestはサーバー内だけで保管。設定・認証情報・保存ファイル名・フルパス・保存データハッシュの非出力'];
    ftb_write($created.'/summary.json',ftb_json($summary));
    ftb_write($created.'/COMPLETE.json',ftb_json(['manifest_sha256'=>hash('sha256',$encoded)]));return $summary;
}
function ftb_restore($backupArg,$homeArg,$webArg,&$created) {
    [$home,$web]=ftb_context($homeArg,$webArg);$backup=realpath($backupArg);
    ftb_need($backup && !is_link($backupArg) && dirname($backup)===$home && !ftb_inside($backup,$web)
        && preg_match('/^\.ft-stage6-backup-\d{8}-\d{6}-[a-f0-9]{10}$/D',basename($backup)), 'A completed private backup is required.');
    $encoded=ftb_read($backup.'/manifest.json');$complete=json_decode(ftb_read($backup.'/COMPLETE.json'),true,512,JSON_THROW_ON_ERROR);
    ftb_need(hash('sha256',$encoded)===($complete['manifest_sha256']??null),'Backup manifest checksum mismatch.');
    $m=json_decode($encoded,true,512,JSON_THROW_ON_ERROR);
    ftb_need(($m['format']??null)===1 && $m['home']===$home && $m['web_root']===$web,'Unsupported or foreign backup.');
    $entries=$m['entries'];ftb_need(isset($entries['.']) && $entries['.']['type']==='directory','Missing root metadata.');
    foreach ($entries as $rel=>$entry) {
        if ($rel!=='.') { ftb_path($rel); }
        ftb_need(in_array($entry['type'],['file','directory'],true) && is_int($entry['mode']) && $entry['mode']>=0 && $entry['mode']<=0777,'Invalid backup entry metadata.');
    }
    // Validate the complete backup tree before creating a restoration destination.
    $stored=ftb_tree($backup.'/app');$expected=$entries;
    foreach ($expected as &$entry) { $entry['mode']=$entry['type']==='file'?0600:0700; }unset($entry);
    ftb_need($stored===$expected,'Backup contents, permissions or timestamps do not match.');
    foreach ($m['extra'] as $id=>$item) {
        ftb_need(preg_match('/^[0-9]{4}$/D',(string)$id)===1,'Invalid extra-file identifier.');
        $expectedExtra=$item['entry'];$expectedExtra['mode']=0600;
        ftb_need(ftb_entry($backup.'/extra/'.$id)===$expectedExtra,'Authentication backup mismatch.');
    }
    $created=ftb_new($home,$web,'restore');ftb_copy_tree($backup.'/app',$created.'/app',$entries,true);ftb_mkdir($created.'/extra');
    foreach ($m['extra'] as $id=>$item) { ftb_copy($backup.'/extra/'.$id,$created.'/extra/'.$id,$item['entry'],true); }
    ftb_need(ftb_tree($created.'/app')===$entries,'Restored content, modes or timestamps mismatch.');
    foreach ($m['extra'] as $id=>$item) { ftb_need(ftb_entry($created.'/extra/'.$id)===$item['entry'],'Restored authentication file mismatch.'); }
    $summary=['scope'=>'段階6 新規の非公開ディレクトリへの隔離復元。公開・本番上書き・アプリ実行なし',
        'checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'verdict'=>'PASS','backup_id'=>basename($backup),'restore_id'=>basename($created),
        'application_files'=>count(array_filter($entries,fn($v)=>$v['type']==='file')),'extra_files'=>count($m['extra']),
        'content_modes_mtime_match'=>true,'production_modified'=>false];
    ftb_write($created.'/summary.json',ftb_json($summary));return $summary;
}
$created=null;
try {
    ftb_need($argc===5 && in_array($argv[1],['backup','restore'],true),'Usage: php sakura-backup.php backup|restore APPLICATION_OR_BACKUP HOME WEB_ROOT');
    $result=$argv[1]==='backup'?ftb_backup($argv[2],$argv[3],$argv[4],$created):ftb_restore($argv[2],$argv[3],$argv[4],$created);
    echo ftb_json($result);
} catch (Throwable $error) {
    // Known validation messages contain no server paths; engine exceptions are redacted.
    $message=get_class($error)===RuntimeException::class?$error->getMessage():'Metadata or runtime error; no production file was written.';
    echo ftb_json(['verdict'=>'INCOMPLETE','message'=>$message,'private_directory_id'=>$created?basename($created):null,'production_modified'=>false]);exit(2);
}
