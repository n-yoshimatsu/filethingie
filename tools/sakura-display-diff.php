<?php
// Read only two application source files from a completed private backup.
// Installed PHP is never executed. No network requests or production writes.
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ini_set('display_errors','0');ini_set('log_errors','0');umask(0077);
function ftd_need($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
function ftd_read($path) {
    ftd_need(is_file($path) && !is_link($path) && filesize($path)<=2097152,'Regular metadata/source file under 2 MiB required.');
    $value=@file_get_contents($path);ftd_need($value!==false,'Read failed.');return $value;
}
function ftd_write($path,$value) {
    $h=@fopen($path,'xb');ftd_need($h!==false,'Could not create private comparison file.');
    try { ftd_need(fwrite($h,$value)===strlen($value),'Comparison-file write failed.'); } finally { fclose($h); }
}
$temporary=null;$createdDirectory=false;$created=[];$exitCode=0;
try {
    ftd_need($argc===4,'Usage: php sakura-display-diff.php BACKUP HOME WEB_ROOT');
    [$backup,$home,$web]=array_map('realpath',array_slice($argv,1));
    ftd_need($backup && $home && $web && !is_link($argv[1]) && dirname($backup)===$home
        && str_starts_with($web,$home.'/') && !str_starts_with($backup,$web.'/')
        && preg_match('/^\.ft-stage6-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/D',basename($backup)), 'Completed private backup required.');
    $encoded=ftd_read($backup.'/manifest.json');$complete=json_decode(ftd_read($backup.'/COMPLETE.json'),true,512,JSON_THROW_ON_ERROR);
    ftd_need(hash('sha256',$encoded)===($complete['manifest_sha256']??null),'Backup manifest mismatch.');
    $manifest=json_decode($encoded,true,512,JSON_THROW_ON_ERROR);
    ftd_need(($manifest['format']??null)===1 && $manifest['home']===$home && $manifest['web_root']===$web,'Foreign or unsupported backup.');
    $baseline=json_decode(ftd_read(__DIR__.'/diff-baseline.json'),true,512,JSON_THROW_ON_ERROR);
    $targets=['index.php'=>'index.php','css/ft.css'=>'ft.css'];$sources=[];$before=[];
    foreach ($targets as $rel=>$name) {
        $path=$backup.'/app/'.$rel;
        ftd_need(realpath($path)===$path && ($manifest['entries'][$rel]['type']??null)==='file','Unexpected source path or link.');
        $installed=ftd_read($path);$upstream=ftd_read(__DIR__.'/upstream/'.$name);
        ftd_need(hash('sha256',$installed)===($manifest['entries'][$rel]['sha256']??null),'Backed-up source content mismatch.');
        ftd_need(hash('sha256',$upstream)===($baseline['files'][$name]??null),'Upstream source mismatch.');
        foreach ([$installed,$upstream] as $value) {
            ftd_need(preg_match('//u',$value)===1 && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)===0,'Source encoding or control characters require manual review.');
        }
        $before[$rel]=hash('sha256',$installed);$sources[$rel]=[str_replace("\r\n","\n",$upstream),str_replace("\r\n","\n",$installed)];
    }
    $temporary=$home.'/.ft-stage6-diff-'.bin2hex(random_bytes(8));ftd_need(mkdir($temporary,0700),'Could not create a fresh private comparison directory.');$createdDirectory=true;
    $diffs=[];
    foreach ($targets as $rel=>$name) {
        [$upstream,$installed]=$sources[$rel];$old='upstream-'.$name;$new='installed-'.$name;
        foreach ([$old=>$upstream,$new=>$installed] as $filename=>$value) { $created[]=$temporary.'/'.$filename;ftd_write($temporary.'/'.$filename,$value); }
        $process=@proc_open(['diff','-u',$old,$new],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$temporary.'/diff-error.log','a']],$pipes,$temporary);
        ftd_need(is_resource($process),'The CLI diff command and proc_open are required.');
        fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);$status=proc_close($process);
        ftd_need(in_array($status,[0,1],true) && strlen($output)<=262144,'Diff command failed or output limit exceeded.');
        // Relative labels above avoid disclosing private paths in diff headers.
        $output=str_replace($home,'<HOME>',$output);
        // Suppress entire reports containing common credential markers. This is
        // an aid, not a guarantee: the operator must review output before sharing.
        $withheld=preg_match('/password|passwd|secret|token|authorization|authuserfile|authgroupfile|private.key|api.key/i',$output)===1;
        $diffs[$rel]=['changed'=>$status===1,'withheld_sensitive_marker'=>$withheld,
            'diff_lines'=>$withheld?[]:explode("\n",rtrim($output,"\n"))];
    }
    foreach ($before as $rel=>$hash) { ftd_need(@hash_file('sha256',$backup.'/app/'.$rel)===$hash,'Source changed during comparison.'); }
    $result=['scope'=>'段階6 保全コピー内の表示関連2ファイルの読取り専用比較','checked_at_utc'=>gmdate('c'),
        'php'=>PHP_VERSION,'comparison_completed'=>true,'upstream_commit'=>$baseline['upstream_commit'],'files'=>$diffs,
        'backup_source_unchanged'=>true,'configuration_read'=>false,'installed_php_executed'=>false,'production_modified'=>false,
        'sharing_note'=>'差分には任意の表示文字列やコメントを含む可能性。共有前の目視確認と秘密情報・個人情報の非共有。検出語による非表示は完全な秘密情報検出ではない補助。'];
} catch (Throwable $error) {
    $result=['comparison_completed'=>false,'message'=>get_class($error)===RuntimeException::class?$error->getMessage():'Comparison failed; production was not changed.'];$exitCode=2;
} finally {
    foreach ($created as $path) { if (is_file($path)) { unlink($path); } }
    if ($createdDirectory && is_dir($temporary)) {
        if (is_file($temporary.'/diff-error.log')) { unlink($temporary.'/diff-error.log'); }
        rmdir($temporary);
    }
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit($exitCode);
