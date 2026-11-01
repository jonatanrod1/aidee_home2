<?php
declare(strict_types=1);
namespace App\Core;
final class Auth {
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return self::user() !== null; }
    public static function login(array $u): void {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$u['id'],'nombre'=>$u['nombre'],'email'=>$u['email'] ?? null,'whatsapp'=>$u['whatsapp'] ?? null];
    }
    public static function logout(): void { unset($_SESSION['user']); session_regenerate_id(true); }
    public static function requireLogin(): void {
        if (!self::check()) { header('Location: /login'); exit; }
    }
    public static function isSuperadmin(): bool {
        $u = self::user(); if (!$u) return false;
        $st = Database::pdo()->prepare("SELECT 1 FROM roles_propiedad WHERE usuario_id=? AND rol='superadmin' LIMIT 1");
        $st->execute([$u['id']]);
        return (bool)$st->fetchColumn();
    }
    public static function requireSuperadmin(): void {
        self::requireLogin();
        if (!self::isSuperadmin()) { http_response_code(403); exit('403 - Solo superadmin'); }
    }
    /** Rol primario + propiedades asignadas (SaaS escalable) */
    public static function contexto(): array {
        $u = self::user();
        $st = Database::pdo()->prepare("SELECT r.propiedad_id, r.rol, p.ubicacion FROM roles_propiedad r JOIN propiedades p ON p.id=r.propiedad_id WHERE r.usuario_id=? ORDER BY r.propiedad_id");
        $st->execute([$u['id']]); $roles = $st->fetchAll();
        $esSuper = false; foreach ($roles as $r) if ($r['rol'] === 'superadmin') $esSuper = true;
        $rol = $esSuper ? 'superadmin' : ($roles[0]['rol'] ?? 'sin_rol');
        return ['es_superadmin' => $esSuper, 'rol' => $rol, 'asignadas' => $roles];
    }
    public static function csrf(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}
