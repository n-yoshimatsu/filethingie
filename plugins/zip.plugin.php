<?php
/** ZIP plugin for File Thingie. Original plugin: Bonuxlee; PHP 8 implementation: ZipArchive. */
function ft_zip_info() { return array('name' => 'ZIP (ZipArchive)'); }
function ft_zip_init() {
  if (!class_exists('ZipArchive')) { ft_plugin_unload('zip'); ft_set_message('ZIP disabled: PHP zip extension required.', 'error'); }
}
function ft_zip_fileextras($file, $dir) { return 'zip'; }
function ft_zip_add_js_call() {
  return 'ft.fileactions.zip = {type:"sendoff",link:"ZIP",text:"Download ZIP?",button:"Download ZIP"};';
}
function ft_zip_collect($path, $name, &$entries, &$bytes) {
  if (is_link($path) || !ft_path_in_root($path)) { return; }
  if (is_dir($path)) {
    if (!ft_check_dir($path)) { return; }
    $entries[$name.'/'] = NULL;
    foreach (new DirectoryIterator($path) as $item) {
      if (!$item->isDot() && ft_validate_filename($item->getFilename()) && ft_check_file($item->getFilename()) && ft_check_filetype($item->getFilename())) {
        ft_zip_collect($item->getPathname(), $name.'/'.$item->getFilename(), $entries, $bytes);
      }
    }
  } elseif (is_file($path) && is_readable($path)) {
    $bytes += filesize($path);
    $entries[$name] = $path;
  } else { throw new RuntimeException('Unreadable ZIP input'); }
  if (count($entries) > 1000 || $bytes > (MAXSIZE > 0 ? MAXSIZE : 20000000)) { throw new RuntimeException('ZIP limit exceeded'); }
}
function ft_zip_page($act) {
  if ($act !== 'zip') { return ''; }
  $tmp = NULL;
  try {
    $entries = array(); $bytes = 0;
    ft_zip_collect(ft_get_dir().'/'.$_REQUEST['file'], $_REQUEST['file'], $entries, $bytes);
    if (!$entries) { throw new RuntimeException('Empty ZIP input'); }
    $tmp = tempnam(sys_get_temp_dir(), 'ft-zip-');
    if ($tmp === FALSE) { throw new RuntimeException('ZIP temporary file unavailable'); }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== TRUE) { throw new RuntimeException('ZIP open failed'); }
    foreach ($entries as $name => $path) {
      $ok = $path === NULL ? $zip->addEmptyDir($name) : $zip->addFile($path, $name);
      if (!$ok) { throw new RuntimeException('ZIP add failed'); }
    }
    if (!$zip->close()) { throw new RuntimeException('ZIP write failed'); }
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"download.zip\"; filename*=UTF-8''".rawurlencode($_REQUEST['file'].'.zip'));
    header('Content-Length: '.filesize($tmp));
    readfile($tmp);
  } catch (RuntimeException $e) {
    http_response_code(400);
    echo 'Could not create ZIP (unreadable file or size/entry limit).';
  } finally { if (is_string($tmp) && is_file($tmp)) { unlink($tmp); } }
  exit;
}
function ft_zip_extract($filename) {
  if (!CREATE || strtolower(ft_get_ext($filename)) !== 'zip') { return FALSE; }
  $folder = substr($filename, 0, -4);
  if (in_array($folder, explode(' ', FOLDERBLACKLIST), TRUE) || !ft_validate_filename($folder) || !ft_check_file($folder) || !ft_check_filetype($folder)) { return FALSE; }
  $destination = ft_get_dir().'/'.$folder;
  if (file_exists($destination) || is_link($destination)) { return FALSE; }
  $zip = new ZipArchive();
  if ($zip->open(ft_get_dir().'/'.$filename, ZipArchive::RDONLY) !== TRUE) { return FALSE; }
  $created = FALSE;
  try {
    $entries = array(); $total = 0; $limit = MAXSIZE > 0 ? MAXSIZE : 20000000;
    if ($zip->numFiles > 1000 || $zip->numFiles === 0) { throw new RuntimeException('ZIP entry limit'); }
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $stat = $zip->statIndex($i);
      $name = $stat['name']; $directory = str_ends_with($name, '/');
      $trimmed = $directory ? substr($name, 0, -1) : $name;
      foreach (explode('/', $trimmed) as $part) {
        if (!ft_validate_filename($part) || str_contains($part, ':') || !ft_check_file($part) || !ft_check_filetype($part)
            || in_array($part, explode(' ', FOLDERBLACKLIST), TRUE)) { throw new RuntimeException('Unsafe ZIP path'); }
      }
      $opsys = $attributes = 0;
      $zip->getExternalAttributesIndex($i, $opsys, $attributes);
      $kind = ($attributes >> 16) & 0170000;
      if ($opsys === ZipArchive::OPSYS_UNIX && $kind !== 0 && $kind !== 0100000 && $kind !== 0040000) { throw new RuntimeException('ZIP special file'); }
      if (isset($entries[$trimmed]) || ($stat['encryption_method'] ?? 0) !== 0) { throw new RuntimeException('Duplicate or encrypted ZIP entry'); }
      $total += $stat['size'];
      if ($total > $limit || (LIMIT > 0 && ROOTDIRSIZE + $total > LIMIT)) { throw new RuntimeException('ZIP size limit'); }
      $entries[$trimmed] = array($i, $directory, $stat['size']);
    }
    // Reject a file also used as a directory, before creating anything.
    foreach ($entries as $name => $entry) {
      for ($parent = dirname($name); $parent !== '.'; $parent = dirname($parent)) {
        if (isset($entries[$parent]) && !$entries[$parent][1]) { throw new RuntimeException('ZIP path collision'); }
      }
    }
    // Exclusive destination, never overwrite an existing directory or file.
    if (!mkdir($destination, DIRPERMISSION)) { throw new RuntimeException('ZIP destination unavailable'); }
    $created = TRUE;
    foreach ($entries as $name => [$i, $directory, $size]) {
      $path = $destination.'/'.$name;
      $parent = $directory ? $path : dirname($path);
      if (!is_dir($parent) && !mkdir($parent, DIRPERMISSION, TRUE)) { throw new RuntimeException('ZIP mkdir failed'); }
      if ($directory) { continue; }
      $in = $zip->getStreamIndex($i); $out = fopen($path, 'xb');
      if ($in === FALSE || $out === FALSE) { if (is_resource($in)) { fclose($in); } if (is_resource($out)) { fclose($out); } throw new RuntimeException('ZIP stream failed'); }
      $copied = stream_copy_to_stream($in, $out, $size + 1);
      fclose($in); fclose($out);
      if ($copied !== $size) { throw new RuntimeException('ZIP size mismatch'); }
      chmod($path, PERMISSION);
    }
    return TRUE;
  } catch (RuntimeException $e) {
    if ($created) {
      $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
      rmdir($destination);
    }
    return FALSE;
  } finally { $zip->close(); }
}
