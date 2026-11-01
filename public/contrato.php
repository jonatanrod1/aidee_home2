<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/config.php';
use App\Core\Auth; use App\Core\Database;
Auth::requireLogin();
$cid = (int)($_GET['id'] ?? 0);
$pdo = Database::pdo();
$st = $pdo->prepare("SELECT c.*, u.codigo, u.fecha_corte, u.cupo_max, p.ubicacion, p.duenio_nombre, p.duenio_doc, p.duenio_whatsapp, s.solicitante_nombre, s.solicitante_doc, s.solicitante_whatsapp FROM contratos c JOIN unidades u ON u.id=c.unidad_id JOIN propiedades p ON p.id=c.propiedad_id LEFT JOIN solicitudes s ON s.id=c.solicitud_id WHERE c.id=?");
$st->execute([$cid]); $c = $st->fetch();
if (!$c) { http_response_code(404); exit('Contrato no existe'); }
// Permiso: superadmin todo, admin solo su propiedad
$ctx = Auth::contexto();
if (!$ctx['es_superadmin']) { $ok=false; foreach($ctx['asignadas'] as $a) if((int)$a['propiedad_id']===(int)$c['propiedad_id']) $ok=true; if(!$ok){http_response_code(403); exit('Sin acceso');} }
$vars = json_decode($c['variables_json'] ?? '{}', true) ?: [];
$ciudad = $vars['ciudad'] ?? 'BOGOTÁ D.C.';
$vig = (int)($vars['vigencia_meses'] ?? 12);
$fin = $vars['fecha_fin'] ?? date('Y-m-d', strtotime($c['fecha_inicio'] . " +$vig months"));
$tpl = file_get_contents(__DIR__ . '/../templates/contrato_base.html');
$rep = [
  '{{ciudad}}' => e($ciudad),
  '{{fecha_inicio}}' => e($c['fecha_inicio']),
  '{{fecha_fin}}' => e($fin),
  '{{vigencia_meses}}' => e((string)$vig),
  '{{arrendador.nombre}}' => e($c['duenio_nombre']),
  '{{arrendador.doc}}' => e($c['duenio_doc']),
  '{{arrendador.contacto}}' => e($c['duenio_whatsapp']),
  '{{arrendatario.nombre}}' => e($c['solicitante_nombre'] ?? ''),
  '{{arrendatario.doc}}' => e($c['solicitante_doc'] ?? ''),
  '{{arrendatario.contacto}}' => e($c['solicitante_whatsapp'] ?? ''),
  '{{inmueble.ciudad}}' => e($ciudad),
  '{{inmueble.direccion}}' => e($c['ubicacion']),
  '{{unidad.codigo}}' => e($c['codigo']),
  '{{unidad.cupo}}' => e((string)$c['cupo_max']),
  '{{unidad.corte}}' => e((string)$c['fecha_corte']),
  '{{contrato.valor}}' => '$' . number_format((float)$c['valor'], 0, ',', '.'),
  '{{contrato.deposito}}' => '$' . number_format((float)$c['deposito'], 0, ',', '.'),
  '{{contrato.fecha_inicio_servicios}}' => e($c['fecha_inicio_servicios']),
];
echo strtr($tpl, $rep);
