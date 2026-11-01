<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Liquidacion {
    /** Días de traslape persona en [desde, hasta] */
    private static function diasTraslape(?string $entrada, ?string $salida, string $desde, string $hasta): int {
        $e = $entrada ?: $desde; $s = $salida ?: $hasta;
        if ($e < $desde) $e = $desde; if ($s > $hasta) $s = $hasta;
        if ($s < $e) return 0;
        return (int)((strtotime($s) - strtotime($e)) / 86400) + 1;
    }
    /** Vista previa del reparto sin guardar */
    public static function preview(int $reciboId): array {
        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT * FROM recibos WHERE id=?"); $st->execute([$reciboId]); $r = $st->fetch();
        if (!$r) throw new \RuntimeException('Recibo no existe');
        $base = (float)$r['total'] - (float)$r['no_trasladable'];
        $desde = $r['periodo_desde']; $hasta = $r['periodo_hasta'];
        $filas = []; $totalPD = 0;
        if ($r['modo'] === 'independiente' && $r['unidad_destino']) {
            $st = $pdo->prepare("SELECT * FROM unidades WHERE id=?"); $st->execute([$r['unidad_destino']]);
            $u = $st->fetch();
            $filas[] = ['unidad_id'=>(int)$u['id'],'codigo'=>$u['codigo'],'persona_dia'=>0,'valor'=>round($base,0),'detalle'=>'100% medidor independiente'];
            return ['recibo'=>$r,'base'=>$base,'total_persona_dia'=>0,'filas'=>$filas];
        }
        // Compartido: persona-día por unidad en el periodo
        $st = $pdo->prepare("SELECT * FROM unidades WHERE propiedad_id=? ORDER BY codigo"); $st->execute([$r['propiedad_id']]);
        foreach ($st->fetchAll() as $u) {
            $pd = 0;
            $stP = $pdo->prepare("SELECT fecha_entrada, fecha_salida FROM personas WHERE unidad_id=? AND activo IS TRUE");
            $stP->execute([$u['id']]);
            foreach ($stP->fetchAll() as $p) $pd += self::diasTraslape($p['fecha_entrada'], $p['fecha_salida'], $desde, $hasta);
            $stV = $pdo->prepare("SELECT fecha_desde, n_personas, n_dias FROM visitas WHERE unidad_id=?");
            $stV->execute([$u['id']]);
            foreach ($stV->fetchAll() as $v) {
                $vd = $v['fecha_desde']; $vh = date('Y-m-d', strtotime("$vd +".((int)$v['n_dias']-1)." days"));
                $tras = self::diasTraslape($vd, $vh, $desde, $hasta);
                $prop = $tras > 0 ? $tras / (int)$v['n_dias'] : 0;
                $pd += round((int)$v['n_personas'] * (int)$v['n_dias'] * $prop, 2);
            }
            $filas[] = ['unidad_id'=>(int)$u['id'],'codigo'=>$u['codigo'],'persona_dia'=>$pd,'valor'=>0,'detalle'=>''];
            $totalPD += $pd;
        }
        foreach ($filas as &$f) {
            $f['valor'] = $totalPD > 0 ? round($base * $f['persona_dia'] / $totalPD, 0) : 0;
            $f['detalle'] = "{$f['persona_dia']} persona-día / {$totalPD} total";
        }
        return ['recibo'=>$r,'base'=>$base,'total_persona_dia'=>$totalPD,'filas'=>$filas];
    }
    /** Valida el admin: genera/mezcla liquidaciones + deja mensajes listos (sin enviar WhatsApp aún) */
    public static function liquidar(int $reciboId, int $adminId): array {
        $pv = self::preview($reciboId);
        $r = $pv['recibo'];
        $pdo = Database::pdo();
        $periodo = date('Y-m', strtotime($r['periodo_hasta'])); // bimensual se cobra el mes que llega
        $mensajes = [];
        foreach ($pv['filas'] as $f) {
            $st = $pdo->prepare("SELECT * FROM unidades WHERE id=?"); $st->execute([$f['unidad_id']]); $u = $st->fetch();
            $st = $pdo->prepare("SELECT * FROM liquidaciones WHERE unidad_id=? AND periodo=?");
            $st->execute([$f['unidad_id'], $periodo]); $liq = $st->fetch();
            if (!$liq) {
                $st = $pdo->prepare("INSERT INTO liquidaciones (propiedad_id, unidad_id, periodo, arriendo_valor, servicios_detalle, total, base_repartible, persona_dia_unidad, persona_dia_total, estado) VALUES (?,?,?,?,?,?,?,?,?,'pendiente') RETURNING id");
                $det = json_encode([['recibo_id'=>$reciboId,'tipo'=>$r['tipo'],'valor'=>$f['valor']]]);
                $total = (float)$u['valor_arriendo'] + $f['valor'];
                $st->execute([$u['propiedad_id'],$f['unidad_id'],$periodo,$u['valor_arriendo'],$det,$total,$pv['base'],$f['persona_dia'],$pv['total_persona_dia']]);
                $liqId = (int)$st->fetchColumn();
            } else {
                $det = json_decode($liq['servicios_detalle'] ?? '[]', true) ?: [];
                $det[] = ['recibo_id'=>$reciboId,'tipo'=>$r['tipo'],'valor'=>$f['valor']];
                $serv = array_sum(array_column($det, 'valor'));
                $total = (float)$liq['arriendo_valor'] + $serv;
                $pdo->prepare("UPDATE liquidaciones SET servicios_detalle=?, total=?, base_repartible=?, persona_dia_unidad=?, persona_dia_total=? WHERE id=?")
                    ->execute([json_encode($det),$total,$pv['base'],$f['persona_dia'],$pv['total_persona_dia'],$liq['id']]);
                $liqId = (int)$liq['id'];
            }
            // Fecha límite = día de corte de la unidad + 5 gracia, del mes del periodo
            [$Y,$M] = explode('-', $periodo);
            $corte = str_pad((string)$u['fecha_corte'], 2, '0', STR_PAD_LEFT);
            $limite = date('Y-m-d', strtotime("$Y-$M-$corte +5 days"));
            $msg = "Aidee home · {$r['tipo']} periodo {$r['periodo_desde']} a {$r['periodo_hasta']}: total \${$r['total']} - no trasladable \${$r['no_trasladable']} = a repartir \${$pv['base']}. Tu unidad {$u['codigo']}: {$f['detalle']} = \$" . number_format($f['valor'],0,',','.') . ". Límite de pago: $limite.";
            $pdo->prepare("INSERT INTO notificaciones (propiedad_id, unidad_id, tipo, contenido) VALUES (?,?,?,?)")
                ->execute([$u['propiedad_id'],$f['unidad_id'],"{$r['tipo']}_$periodo",$msg]);
            $mensajes[] = ['unidad'=>$u['codigo'],'valor'=>$f['valor'],'limite'=>$limite,'mensaje'=>$msg,'liquidacion_id'=>$liqId];
        }
        $pdo->prepare("UPDATE recibos SET clasificado='t' WHERE id=?")->execute([$reciboId]);
        return ['periodo'=>$periodo,'mensajes'=>$mensajes];
    }
}
