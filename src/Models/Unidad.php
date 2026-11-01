<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class Unidad {
    public static function byPropiedad(int $pid): array {
        $st = Database::pdo()->prepare("SELECT * FROM unidades WHERE propiedad_id=? ORDER BY codigo");
        $st->execute([$pid]); return $st->fetchAll();
    }
    public static function create(array $d): int {
        $st = Database::pdo()->prepare("INSERT INTO unidades (propiedad_id, codigo, tipo, valor_arriendo, fecha_corte, cupo_max, estado) VALUES (?,?,?,?,?,?,?) RETURNING id");
        $st->execute([$d['propiedad_id'],$d['codigo'],$d['tipo']??'apartamento',$d['valor_arriendo'],$d['fecha_corte'],$d['cupo_max']??4,$d['estado']??'libre']);
        return (int)$st->fetchColumn();
    }
    /** Conteo por tipo + texto "2 apartamentos · 1 local" (lo define el superadmin) */
    public static function tiposTxt(int $pid): string {
        $st = Database::pdo()->prepare("SELECT tipo, count(*) AS n FROM unidades WHERE propiedad_id=? GROUP BY tipo ORDER BY tipo");
        $st->execute([$pid]); $rows = $st->fetchAll();
        if (!$rows) return 'Sin unidades';
        $pl = ['apartamento'=>['apartamento','apartamentos'],'habitacion'=>['habitación','habitaciones'],'local'=>['local','locales']];
        $partes = [];
        foreach ($rows as $r) {
            $n = (int)$r['n'];
            $nom = $pl[$r['tipo']][$n === 1 ? 0 : 1] ?? $r['tipo'];
            $partes[] = "$n $nom";
        }
        return implode(' · ', $partes);
    }
}
