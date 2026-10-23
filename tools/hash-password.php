<?php
// CLI only. Read the password from stdin, never from a command-line argument.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$password = stream_get_contents(STDIN, 4097);
if (strlen($password) > 4096 || str_contains($password, "\0")) { fwrite(STDERR, "Invalid password input.\n"); exit(1); }
$password = preg_replace('/\r?\n$/D', '', $password);
// PASSWORD_DEFAULT is bcrypt on the supported runtimes; reject truncation.
if ($password === '' || strlen($password) > 72) { fwrite(STDERR, "Use a password of 1–72 bytes.\n"); exit(1); }
echo password_hash($password, PASSWORD_DEFAULT), "\n";
