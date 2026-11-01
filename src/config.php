<?php
// Lee .env local si existe (sin dependencias), luego env del contenedor
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
  foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k); $v = trim($v);
    if (getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
  }
}
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_DATABASE') ?: 'aidee_home2';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASSWORD') ?: 'postgres';
return [
  'dsn'  => "pgsql:host={$host};port={$port};dbname={$db}",
  'user' => $user,
  'pass' => $pass,
];
