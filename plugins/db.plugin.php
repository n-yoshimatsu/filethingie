<?php
/** DB plugin for File Thingie. Original: Andreas Haugstrup Pedersen, 2009. */
function ft_db_info() {
  return array('name' => 'Database (PDO SQLite)', 'settings' => array(
    'path' => array('default' => 'data/ft_db', 'description' => 'SQLite file outside the document root and managed files'),
    'flushtables' => array('default' => FALSE, 'description' => 'Allow the primary administrator to delete tables')));
}
function ft_db_init() {
  global $ft;
  $ft['db'] = array('tables' => array());
  $path = $ft['plugins']['db']['settings']['path'];
  $parent = is_string($path) ? realpath(dirname($path)) : FALSE;
  $public = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__.'/..');
  $root = realpath(ft_get_root());
  // Refuse a database downloadable through the web server or the file manager.
  $protected = $parent !== FALSE && !is_link($path) && is_writable($parent);
  $roots = array($public, $root, realpath($ft['default_settings']['DIR'] ?? ft_get_root()));
  foreach ($ft['groups'] as $group) { if (isset($group['DIR'])) { $roots[] = realpath($group['DIR']); } }
  foreach ($roots as $exposed) {
    if ($exposed !== FALSE && $parent !== FALSE && ($parent === $exposed || str_starts_with($parent, $exposed.DIRECTORY_SEPARATOR))) { $protected = FALSE; }
  }
  if ($protected && extension_loaded('pdo_sqlite')) {
    try {
      $ft['db']['link'] = new PDO('sqlite:'.$parent.'/'.basename($path), NULL, NULL,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
      if (!@chmod($path, 0600)) { throw new PDOException('Database permissions'); }
      $ft['db']['link']->exec('PRAGMA busy_timeout=5000');
      ft_db_refresh();
      return;
    } catch (PDOException $e) { /* Report configuration failure without disclosing paths or SQL. */ }
  }
  ft_plugin_unload('db');
  ft_plugin_unload('log');
  ft_set_message('DB/log disabled: require PDO SQLite and a writable database outside the document root and managed files. See docs/STAGE4_MIGRATION.md.', 'error');
}
function ft_db_refresh() {
  global $ft;
  $ft['db']['tables'] = $ft['db']['link']->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}
function ft_db_install_table($table, $sql) {
  global $ft;
  if (!in_array($table, $ft['db']['tables'], TRUE)) { $ft['db']['link']->exec($sql); ft_db_refresh(); }
  return TRUE;
}
function ft_db_allowed() {
  global $ft;
  return ft_is_admin() && $ft['plugins']['db']['settings']['flushtables'] === TRUE;
}
function ft_db_secondary_menu() { return ft_db_allowed() ? ft_make_link('[DB]', 'act=db') : ''; }
function ft_db_page($act) {
  global $ft;
  if (!in_array($act, array('db', 'db_delete'), TRUE)) { return ''; }
  if (!ft_db_allowed()) { http_response_code(403); return '<p>Forbidden</p>'; }
  $str = '<h2>Database tables</h2>';
  if ($act === 'db_delete') {
    $table = $_GET['value'] ?? '';
    if (!in_array($table, $ft['db']['tables'], TRUE)) { ft_bad_request(); }
    $str .= '<p>'.t('Delete?').' '.ft_escape($table).'</p><form method="post" action="'.ft_get_self().'"><div>'
      .'<input type="hidden" name="act" value="db_delete_submit" /><input type="hidden" name="value" value="'.ft_escape($table).'" />'
      .'<button type="submit">'.t('Delete?').'</button></div></form>';
  } else {
    foreach ($ft['db']['tables'] as $table) {
      $str .= '<p>'.ft_escape($table).' '.ft_make_link(t('Delete?'), 'act=db_delete&value='.rawurlencode($table)).'</p>';
    }
  }
  return $str;
}
function ft_db_action($act) {
  global $ft;
  if ($act !== 'db_delete_submit') { return; }
  if (!ft_db_allowed()) { http_response_code(403); exit('Forbidden'); }
  $table = $_POST['value'] ?? '';
  if (!in_array($table, $ft['db']['tables'], TRUE)) { ft_bad_request(); }
  $ft['db']['link']->exec('DROP TABLE "'.str_replace('"', '""', $table).'"');
  ft_redirect('act=db');
}
