<?php
/** Log plugin for File Thingie. Original: Andreas Haugstrup Pedersen, 2009. */
define('FT_LOG_NOTICE', 5);
define('FT_LOG_INFO', 6);
function ft_log_info() {
  return array('name' => 'Log', 'settings' => array('viewlogs' => array('default' => FALSE, 'description' => 'Allow the primary administrator to view and prune logs')));
}
function ft_log_init() {
  if (!ft_plugin_exists('db')) { ft_plugin_unload('log'); ft_set_message('Log disabled: DB plugin unavailable.', 'error'); return; }
  ft_db_install_table('log', 'CREATE TABLE log (id INTEGER PRIMARY KEY, type TEXT NOT NULL, message TEXT NOT NULL, location TEXT NOT NULL, timestamp TEXT NOT NULL, user TEXT NOT NULL, hostname TEXT NOT NULL, severity INTEGER NOT NULL)');
}
function ft_log_allowed() {
  global $ft;
  return ft_is_admin() && $ft['plugins']['log']['settings']['viewlogs'] === TRUE;
}
function ft_log_secondary_menu() { return ft_log_allowed() ? ft_make_link(t('[logs]'), 'act=log', t('View logs')) : ''; }
function ft_log_download($dir, $file) { ft_log_insert('download', 'File downloaded.', FT_LOG_INFO, $dir.'/'.$file); }
function ft_log_upload($dir, $file) { ft_log_insert('upload', 'File uploaded.', FT_LOG_INFO, $dir.'/'.$file); }
function ft_log_loginfail($user) { ft_log_insert('user', 'Failed login attempt.', FT_LOG_NOTICE, '', $user); }
function ft_log_loginsuccess($user) { ft_log_insert('user', 'Logged in.', FT_LOG_INFO, '', $user); }
function ft_log_logout($user) { ft_log_insert('user', 'Logged out.', FT_LOG_INFO, '', $user); }
function ft_log_insert($type, $message, $severity = FT_LOG_NOTICE, $location = '', $user = '') {
  global $ft;
  $query = $ft['db']['link']->prepare('INSERT INTO log (type,message,location,timestamp,user,hostname,severity) VALUES (?,?,?,?,?,?,?)');
  $query->execute(array($type, $message, $location, date('Y-m-d H:i:s'), $user ?: ($_SESSION['ft_user_'.MUTEX] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '', $severity));
}
function ft_log_date($month, $day, $end = FALSE) {
  if ($month === '') { return NULL; }
  if (!preg_match('/^(\d{4})-(\d{1,2})$/D', $month, $m) || !checkdate((int)$m[2], 1, (int)$m[1])) { ft_bad_request(); }
  $d = $day !== '' ? $day : ($end ? date('t', strtotime($month.'-01')) : '1');
  if (!ctype_digit((string)$d) || !checkdate((int)$m[2], (int)$d, (int)$m[1])) { ft_bad_request(); }
  return sprintf('%04d-%02d-%02d', $m[1], $m[2], $d).($end ? ' 23:59:59' : ' 00:00:00');
}
function ft_log_do_query() {
  global $ft;
  $where = $values = array();
  $type = $_GET['type'] ?? '';
  if ($type !== '') { $where[] = 'type = ?'; $values[] = $type; }
  foreach (array('from' => FALSE, 'to' => TRUE) as $key => $end) {
    $date = ft_log_date($_GET[$key] ?? '', $_GET[$key.'_day'] ?? '', $end);
    if ($date !== NULL) { $where[] = 'timestamp '.($end ? '<=' : '>=').' ?'; $values[] = $date; }
  }
  $query = $ft['db']['link']->prepare('SELECT type,message,location,timestamp,user FROM log'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY timestamp DESC,id DESC');
  $query->execute($values);
  return $query;
}
function ft_log_page($act) {
  if ($act !== 'log') { return ''; }
  if (!ft_log_allowed()) { http_response_code(403); return '<p>Forbidden</p>'; }
  $str = '<h2>'.t('Log').'</h2><form method="get" action="'.ft_get_self().'"><div><input type="hidden" name="act" value="log" />';
  foreach (array('type' => 'Type', 'from' => 'From (YYYY-MM)', 'from_day' => 'Day', 'to' => 'To (YYYY-MM)', 'to_day' => 'Day') as $name => $label) {
    $str .= '<label>'.ft_escape(t($label)).' <input name="'.$name.'" value="'.ft_escape($_GET[$name] ?? '').'" size="10" /></label> ';
  }
  $str .= '<button type="submit">'.t('Show').'</button></div></form><table id="log_table"><thead><tr>';
  foreach (array('Type', 'Message', 'Location', 'Timestamp', 'User') as $label) { $str .= '<th><button type="button">'.t($label).'</button></th>'; }
  $str .= '</tr></thead><tbody>';
  foreach (ft_log_do_query() as $row) {
    $str .= '<tr>';
    foreach ($row as $value) { $str .= '<td>'.ft_escape($value).'</td>'; }
    $str .= '</tr>';
  }
  $str .= '</tbody></table><form method="post" action="'.ft_get_self().'"><div><input type="hidden" name="act" value="log_prune" /><button type="submit">'.t('Delete records older than 7 months').'</button></div></form>';
  $filter = array_intersect_key($_GET, array_flip(array('type', 'from', 'from_day', 'to', 'to_day')));
  return $str.ft_make_link(t('Download CSV report'), http_build_query(array('act' => 'log_csv') + $filter));
}
function ft_log_action($act) {
  global $ft;
  if (!in_array($act, array('log_prune', 'log_csv'), TRUE)) { return; }
  if (!ft_log_allowed()) { http_response_code(403); exit('Forbidden'); }
  if ($act === 'log_prune') {
    $query = $ft['db']['link']->prepare('DELETE FROM log WHERE timestamp < ?');
    $query->execute(array(date('Y-m-d H:i:s', strtotime('-7 months'))));
    ft_set_message(t('Log pruned'));
    ft_redirect('act=log');
  }
  $rows = ft_log_do_query();
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="filethingie-log.csv"');
  $out = fopen('php://output', 'wb');
  fputcsv($out, array('Type', 'Message', 'Location', 'Timestamp', 'User'), ';', '"', '');
  foreach ($rows as $row) {
    // Prevent spreadsheet formulas, including formulas preceded by whitespace.
    $row = array_map(function($value) { return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value; }, $row);
    fputcsv($out, $row, ';', '"', '');
  }
  fclose($out);
  exit;
}
function ft_log_add_js_call() {
  return '$("#log_table th button").on("click", function() {
    var index = $(this).closest("th").index(), body = document.querySelector("#log_table tbody");
    var direction = this.dataset.direction === "asc" ? -1 : 1;
    this.dataset.direction = direction === 1 ? "asc" : "desc";
    Array.from(body.rows).sort(function(a,b) { return direction * a.cells[index].textContent.localeCompare(b.cells[index].textContent); }).forEach(function(row) { body.appendChild(row); });
  });';
}
