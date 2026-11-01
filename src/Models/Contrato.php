<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Contrato {
    public static function generarDesdeSolicitud(int $sid): int {
        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT s.*, u.valor_arriendo, u.fecha_corte, u.cupo_max, u.propiedad_id, p.ubicacion, p.duenio_nombre, p.duenio_doc, p.duenio_whatsapp FROM solicitudes s JOIN unidades u ON u.id=s.unidad_id JOIN propiedades p ON p.id=s.propiedad_id WHERE s.id=?");
        $st->execute([$sid]); $s = $st->fetch();
        if (!$s || !$s['unidad_id']) throw new \RuntimeException('Solicitud sin unidad');
        $vars = json_encode(['origen_solicitud'=>$sid,'n_personas'=>$s['n_personas'],'mascota'=>$s['tiene_mascota'],'corte'=>$s['fecha_corte'],'ciudad'=>'BOGOTÁ D.C.','vigencia_meses'=>12]);
        $st = $pdo->prepare("INSERT INTO contratos (propiedad_id, unidad_id, solicitud_id, fecha_inicio, fecha_inicio_servicios, valor, deposito, variables_json, estado) VALUES (?,?,?,?,?,?,?,?,'borrador') RETURNING id");
        $hoy = date('Y-m-d');
        $st->execute([$s['propiedad_id'],$s['unidad_id'],$sid,$hoy,$hoy,$s['valor_arriendo'],round($s['valor_arriendo']*1,0),$vars]);
        $cid = (int)$st->fetchColumn();
        $pdo->prepare("UPDATE solicitudes SET estado='contrato_borrador' WHERE id=?")->execute([$sid]);
        return $cid;
    }
    public static function cambiarEstado(int $id, string $estado): void {
        $v = ['borrador','aprobado_admin','enviado_firma','firmado','activo','terminado'];
        if (!in_array($estado,$v,true)) throw new \InvalidArgumentException('Estado');
        Database::pdo()->prepare("UPDATE contratos SET estado=? WHERE id=?")->execute([$estado,$id]);
    }
    public static function activar(int $cid): void {
        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT c.*, s.solicitante_nombre, s.solicitante_doc, s.solicitante_whatsapp FROM contratos c JOIN solicitudes s ON s.id=c.solicitud_id WHERE c.id=?");
        $st->execute([$cid]); $c = $st->fetch();
        if (!$c) throw new \RuntimeException('Contrato');
        // 1. Persona titular
        $pdo->prepare("INSERT INTO personas (propiedad_id, unidad_id, nombre, doc, whatsapp, tipo, fecha_entrada, activo) VALUES (?,?,?,?,?,'titular',CURRENT_DATE,true)")
            ->execute([$c['propiedad_id'],$c['unidad_id'],$c['solicitante_nombre'],$c['solicitante_doc'],$c['solicitante_whatsapp']]);
        // 2. Depósito
        $pdo->prepare("INSERT INTO depositos (propiedad_id, unidad_id, valor_inicial) VALUES (?,?,?) ON CONFLICT (unidad_id) DO UPDATE SET valor_inicial=EXCLUDED.valor_inicial")
            ->execute([$c['propiedad_id'],$c['unidad_id'],$c['deposito']]);
        // 3. Unidad ocupada + contrato/solicitud activos
        $pdo->prepare("UPDATE unidades SET estado='ocupada' WHERE id=?")->execute([$c['unidad_id']]);
        $pdo->prepare("UPDATE contratos SET estado='activo' WHERE id=?")->execute([$cid]);
        $pdo->prepare("UPDATE solicitudes SET estado='activa' WHERE id=?")->execute([$c['solicitud_id']]);
    }
    public static function porPropiedad(int $pid): array {
        $st = Database::pdo()->prepare("SELECT c.*, u.codigo FROM contratos c JOIN unidades u ON u.id=c.unidad_id WHERE c.propiedad_id=? ORDER BY c.id DESC");
        $st->execute([$pid]); return $st->fetchAll();
    }
}
