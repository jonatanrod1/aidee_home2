<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Propiedad {
    public static function all(?int $usuarioId=null, bool $esSuper=false): array {
        if ($esSuper || $usuarioId === null) {
            return Database::pdo()->query("SELECT p.*, (SELECT count(*) FROM unidades u WHERE u.propiedad_id=p.id) AS n_unidades_reales FROM propiedades p ORDER BY p.id DESC")->fetchAll();
        }
        $st = Database::pdo()->prepare("SELECT p.*, (SELECT count(*) FROM unidades u WHERE u.propiedad_id=p.id) AS n_unidades_reales FROM propiedades p JOIN roles_propiedad r ON r.propiedad_id=p.id WHERE r.usuario_id=? ORDER BY p.id DESC");
        $st->execute([$usuarioId]); return $st->fetchAll();
    }
    public static function create(array $d): int {
        $st = Database::pdo()->prepare("INSERT INTO propiedades (ubicacion, duenio_nombre, duenio_doc, duenio_whatsapp, n_unidades) VALUES (?,?,?,?,?) RETURNING id");
        $st->execute([$d['ubicacion'],$d['duenio_nombre'],$d['duenio_doc'],$d['duenio_whatsapp'],(int)($d['n_unidades']??0)]);
        return (int)$st->fetchColumn();
    }
}
