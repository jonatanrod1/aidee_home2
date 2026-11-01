<?php
declare(strict_types=1);
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});
function env(string $key, ?string $default = null): string {
    // .env ya cargado en config.php para CLI; en FPM viene del compose
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') return (string) $default;
    return (string) $v;
}
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cop(float $v): string { return '$' . number_format($v, 0, ',', '.'); }
