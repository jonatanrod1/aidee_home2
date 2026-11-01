<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Dashboard {
    public static function stats(int $propiedadId, string $periodo): array {
        $pdo = Database::pdo();
        $prop = $pdo->prepare("SELECT * FROM propiedades WHERE id=?");
        $prop->execute([$propiedadId]); $p = $prop->fetch();
        if (!$p) return ['error' => 'Propiedad'];
        $unidades = Unidad::byPropiedad($propiedadId);
        $nUni = count($unidades);
        // Habitantes activos
        $hab = (int)$pdo->query("SELECT count(*) FROM personas WHERE propiedad_id=$propiedadId AND activo IS TRUE")->fetchColumn();
        // Contratos vigentes (activo/aprobado) en la propiedad
        $conVig = (int)$pdo->query("SELECT count(*) FROM contratos c JOIN unidades u ON u.id=c.unidad_id WHERE u.propiedad_id=$propiedadId AND c.estado IN ('activo','aprobado_admin','firmado')")->fetchColumn();
        // Liquidaciones del periodo
        $st = $pdo->prepare("SELECT * FROM liquidaciones WHERE propiedad_id=? AND periodo=?");
        $st->execute([$propiedadId, $periodo]);
        $liqs = $st->fetchAll();
        $canon = 0; $serv = 0; $total = 0;
        foreach ($liqs as $l) { $canon += (float)$l['arriendo_valor']; $total += (float)$l['total']; }
        $serv = $total - $canon;
        if ($total <= 0) {
            // Estimado desde unidades cuando aún no hay liquidación
            foreach ($unidades as $u) { $canon += (float)$u['valor_arriendo']; }
            $total = $canon + $serv;
        }
        // Pagos conciliados (validados) del periodo
        $st = $pdo->prepare("SELECT coalesce(sum(p.valor),0) FROM pagos p JOIN liquidaciones l ON l.id=p.liquidacion_id WHERE l.propiedad_id=? AND l.periodo=? AND p.estado='validado'");
        $st->execute([$propiedadId, $periodo]); $conciliado = (float)$st->fetchColumn();
        $st = $pdo->prepare("SELECT count(*) FROM pagos p JOIN liquidaciones l ON l.id=p.liquidacion_id WHERE l.propiedad_id=? AND l.periodo=? AND p.estado='por_validar'");
        $st->execute([$propiedadId, $periodo]); $enRevision = (int)$st->fetchColumn();
        // Monitor por unidad
        $monitor = [];
        foreach ($unidades as $u) {
            $stP = $pdo->prepare("SELECT nombre FROM personas WHERE unidad_id=? AND activo IS TRUE ORDER BY CASE tipo WHEN 'titular' THEN 0 ELSE 1 END LIMIT 1");
            $stP->execute([$u['id']]); $inq = $stP->fetchColumn() ?: '—';
            $stH = $pdo->prepare("SELECT count(*) FROM personas WHERE unidad_id=? AND activo IS TRUE");
            $stH->execute([$u['id']]); $h = (int)$stH->fetchColumn();
            $liq = null; foreach ($liqs as $l) if ((int)$l['unidad_id'] === (int)$u['id']) $liq = $l;
            $c = $liq ? (float)$liq['arriendo_valor'] : (float)$u['valor_arriendo'];
            $t = $liq ? (float)$liq['total'] : $c;
            $s = $t - $c;
            $monitor[] = ['unidad_id'=>(int)$u['id'],'codigo'=>$u['codigo'],'tipo'=>$u['tipo'],'inquilino'=>$inq,'hab'=>$h,'canon'=>$c,'servicios'=>$s,'total'=>$t,'vence'=>(int)$u['fecha_corte'],'estado'=>$liq ? $liq['estado'] : 'sin_liquidar'];
        }
        // Cola conciliación
        $st = $pdo->prepare("SELECT p.id, p.valor, p.created_at, u.codigo FROM pagos p JOIN liquidaciones l ON l.id=p.liquidacion_id JOIN unidades u ON u.id=l.unidad_id WHERE l.propiedad_id=? AND p.estado='por_validar' ORDER BY p.created_at DESC LIMIT 20");
        $st->execute([$propiedadId]); $cola = $st->fetchAll();
        // Depósitos
        $st = $pdo->prepare("SELECT d.*, u.codigo FROM depositos d JOIN unidades u ON u.id=d.unidad_id WHERE d.propiedad_id=? ORDER BY u.codigo");
        $st->execute([$propiedadId]); $deps = $st->fetchAll();
        $reserva = round($total * 0.10, 0);
        return [
            'propiedad' => $p, 'periodo' => $periodo,
            'n_unidades' => $nUni, 'tipos_txt' => Unidad::tiposTxt($propiedadId), 'contratos_vigentes' => $conVig, 'habitantes' => $hab,
            'canon' => $canon, 'servicios' => $serv, 'recaudo' => $total,
            'conciliado' => $conciliado, 'en_revision' => $enRevision,
            'reserva' => $reserva, 'neto' => $total - $reserva,
            'monitor' => $monitor, 'cola' => $cola, 'depositos' => $deps,
        ];
    }
}
