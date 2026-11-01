<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Usuario {
    public static function findByEmail(string $email): ?array {
        $st = Database::pdo()->prepare("SELECT * FROM usuarios WHERE email=? LIMIT 1");
        $st->execute([$email]); $r = $st->fetch(); return $r ?: null;
    }
    public static function findByWhatsapp(string $wa): ?array {
        $wa = preg_replace('/\D+/', '', $wa);
        $st = Database::pdo()->prepare("SELECT * FROM usuarios WHERE regexp_replace(coalesce(whatsapp,''), '\\D', '', 'g')=? LIMIT 1");
        $st->execute([$wa]); $r = $st->fetch(); return $r ?: null;
    }
    /** Llave de entrada: número WhatsApp (recomendado) o email */
    public static function findByLogin(string $login): ?array {
        $login = trim($login);
        if (str_contains($login, '@')) return self::findByEmail($login);
        return self::findByWhatsapp($login);
    }
    public static function create(string $nombre, ?string $email, string $pass, ?string $whatsapp=null): int {
        $st = Database::pdo()->prepare("INSERT INTO usuarios (nombre, email, pass_hash, whatsapp) VALUES (?,?,?,?) RETURNING id");
        $st->execute([$nombre,$email,password_hash($pass,PASSWORD_DEFAULT),$whatsapp]);
        return (int)$st->fetchColumn();
    }
    public static function asignarRol(int $uid, int $pid, string $rol): void {
        $st = Database::pdo()->prepare("INSERT INTO roles_propiedad (usuario_id, propiedad_id, rol) VALUES (?,?,?) ON CONFLICT (usuario_id, propiedad_id, rol) DO NOTHING");
        $st->execute([$uid,$pid,$rol]);
    }
    public static function rolesDe(int $uid): array {
        $st = Database::pdo()->prepare("SELECT r.*, p.ubicacion FROM roles_propiedad r JOIN propiedades p ON p.id=r.propiedad_id WHERE r.usuario_id=? ORDER BY r.propiedad_id");
        $st->execute([$uid]); return $st->fetchAll();
    }
}
