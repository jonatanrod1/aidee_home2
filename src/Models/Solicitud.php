<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Solicitud {
    public static function porPropiedad(int $pid): array {
        $st = Database::pdo()->prepare("SELECT s.*, u.codigo FROM solicitudes s LEFT JOIN unidades u ON u.id=s.unidad_id WHERE s.propiedad_id=? ORDER BY s.id DESC");
        $st->execute([$pid]); return $st->fetchAll();
    }
    public static function crear(array $d): int {
        $st = Database::pdo()->prepare("INSERT INTO solicitudes (propiedad_id, unidad_id, solicitante_nombre, solicitante_doc, solicitante_whatsapp, n_personas, tiene_mascota, fiador_nombre, fiador_doc, fiador_whatsapp, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
        $st->execute([$d['propiedad_id'],$d['unidad_id'] ?? null,$d['solicitante_nombre'],$d['solicitante_doc'],$d['solicitante_whatsapp'],(int)($d['n_personas']??1),!empty($d['tiene_mascota'])?'t':'f',$d['fiador_nombre']??null,$d['fiador_doc']??null,$d['fiador_whatsapp']??null,'documentos_pendientes']);
        return (int)$st->fetchColumn();
    }
    public static function cambiarEstado(int $id, string $estado): void {
        $validos = ['documentos_pendientes','en_validacion','aprobada','rechazada','contrato_borrador','contrato_enviado','firmado','activa'];
        if (!in_array($estado, $validos, true)) throw new \InvalidArgumentException('Estado');
        Database::pdo()->prepare("UPDATE solicitudes SET estado=? WHERE id=?")->execute([$estado,$id]);
    }
    public static function docs(int $sid): array {
        $st = Database::pdo()->prepare("SELECT * FROM archivos WHERE solicitud_id=? ORDER BY id");
        $st->execute([$sid]); return $st->fetchAll();
    }
}
