<?php
declare(strict_types=1);
// API Ajax superadmin (WhatsApp queda para fase final: aquí solo se registra log, no se envía)
session_start();
require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
use App\Core\Database; use App\Core\Auth;
use App\Models\Propiedad; use App\Models\Unidad; use App\Models\Usuario; use App\Models\Dashboard; use App\Models\Solicitud; use App\Models\Contrato; use App\Models\Liquidacion;
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
try {
  match ($action) {
    'login' => (function() use ($in) {
        $login = trim($in['user'] ?? $in['whatsapp'] ?? $in['email'] ?? '');
        $u = Usuario::findByLogin($login);
        if (!$u || !password_verify($in['password'] ?? '', $u['pass_hash'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'WhatsApp o clave inválidos']); return; }
        Auth::login($u); echo json_encode(['ok'=>true,'nombre'=>$u['nombre'],'whatsapp'=>$u['whatsapp']]);
    })(),
    'logout' => (function() { Auth::logout(); echo json_encode(['ok'=>true]); })(),
    'me' => (function() {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'user'=>Auth::user(),'ctx'=>Auth::contexto()]);
    })(),
    'propiedades.list' => (function() {
        Auth::requireLogin();
        $ctx = Auth::contexto();
        echo json_encode(['ok'=>true,'data'=>Propiedad::all((int)$_SESSION['user']['id'], $ctx['es_superadmin']),'ctx'=>$ctx]);
    })(),
    'propiedades.create' => (function() use ($in) {
        Auth::requireSuperadmin();
        foreach (['ubicacion','duenio_nombre','duenio_doc','duenio_whatsapp'] as $k) if (empty($in[$k])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>"Falta $k"]); return; }
        $id = Propiedad::create($in);
        // El superadmin creador queda asignado a la nueva propiedad
        Usuario::asignarRol((int)$_SESSION['user']['id'], $id, 'superadmin');
        echo json_encode(['ok'=>true,'id'=>$id]);
    })(),
    'propiedades.update' => (function() use ($in) {
        // Solo superadmin: la parametrización es lo que se cobra en el SaaS
        Auth::requireSuperadmin();
        $pid = (int)($in['propiedad_id'] ?? $in['id'] ?? 0);
        if (!$pid) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'propiedad_id']); return; }
        $pdo = \App\Core\Database::pdo();
        $sets = []; $vals = [];
        foreach (['ubicacion','duenio_nombre','duenio_doc','duenio_whatsapp'] as $k) {
            if (array_key_exists($k, $in) && $in[$k] !== '') { $sets[] = "$k=?"; $vals[] = $in[$k]; }
        }
        if (!$sets) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Nada para actualizar']); return; }
        $vals[] = $pid;
        $pdo->prepare("UPDATE propiedades SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    })(),
    'unidades.list' => (function() use ($in) {
        Auth::requireLogin();
        $pid = (int)($in['propiedad_id'] ?? $_GET['propiedad_id'] ?? 0);
        $ctx = Auth::contexto();
        if (!$ctx['es_superadmin']) {
            $ok = false; foreach ($ctx['asignadas'] as $a) if ((int)$a['propiedad_id'] === $pid) $ok = true;
            if (!$ok) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Sin acceso']); return; }
        }
        echo json_encode(['ok'=>true,'data'=>Unidad::byPropiedad($pid)]);
    })(),
    'unidades.create' => (function() use ($in) {
        // Solo superadmin: la parametrización (unidades y tipos) es lo que se cobra en el SaaS
        Auth::requireSuperadmin();
        foreach (['propiedad_id','codigo','valor_arriendo','fecha_corte'] as $k) if (empty($in[$k])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>"Falta $k"]); return; }
        echo json_encode(['ok'=>true,'id'=>Unidad::create($in)]);
    })(),
    'unidades.update' => (function() use ($in) {
        // Solo superadmin edita configuración de la unidad (tipo, valor, corte, cupo…)
        Auth::requireSuperadmin();
        $id = (int)($in['id'] ?? 0);
        if (!$id) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'id']); return; }
        $sets = []; $vals = [];
        foreach (['codigo','tipo','valor_arriendo','fecha_corte','cupo_max','estado'] as $k) {
            if (array_key_exists($k, $in) && $in[$k] !== '') { $sets[] = "$k=?"; $vals[] = $in[$k]; }
        }
        if (!$sets) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Nada para actualizar']); return; }
        $vals[] = $id;
        \App\Core\Database::pdo()->prepare("UPDATE unidades SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        echo json_encode(['ok'=>true]);
    })(),
    'usuarios.asignar' => (function() use ($in) {
        Auth::requireSuperadmin();
        // Crea o reutiliza por WhatsApp (llave) o email y asigna rol admin/auditor en propiedad
        $login = trim($in['whatsapp'] ?? $in['email'] ?? '');
        if (!$login || empty($in['propiedad_id']) || empty($in['rol'])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'whatsapp/propiedad_id/rol']); return; }
        $u = Usuario::findByLogin($login);
        if ($u) { $uid = (int)$u['id']; }
        else {
            $email = $in['email'] ?? null;
            $uid = Usuario::create($in['nombre'] ?? $login, $email, $in['password'] ?? 'admin123', $in['whatsapp'] ?? $login);
        }
        Usuario::asignarRol($uid, (int)$in['propiedad_id'], $in['rol']);
        echo json_encode(['ok'=>true,'usuario_id'=>$uid]);
    })(),
    'dashboard.stats' => (function() {
        Auth::requireLogin();
        $pid = (int)($_GET['propiedad_id'] ?? 0);
        // Admin solo ve sus asignadas; superadmin todo
        $ctx = Auth::contexto();
        if (!$ctx['es_superadmin']) {
            $ok = false; foreach ($ctx['asignadas'] as $a) if ((int)$a['propiedad_id'] === $pid) $ok = true;
            if (!$ok) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Sin acceso']); return; }
        }
        $periodo = $_GET['periodo'] ?? date('Y-m');
        echo json_encode(['ok'=>true,'data'=>Dashboard::stats($pid, $periodo),'ctx'=>$ctx]);
    })(),
    'dashboard.global' => (function() {
        Auth::requireSuperadmin();
        $props = Propiedad::all((int)$_SESSION['user']['id'], true);
        $periodo = $_GET['periodo'] ?? date('Y-m');
        $out = []; $tRec = 0; $tCon = 0; $tHab = 0; $tUni = 0;
        foreach ($props as $p) {
            $s = Dashboard::stats((int)$p['id'], $periodo);
            $out[] = ['id'=>(int)$p['id'],'ubicacion'=>$p['ubicacion'],'duenio'=>$p['duenio_nombre'],'n_unidades'=>$s['n_unidades'],'tipos_txt'=>$s['tipos_txt'],'recaudo'=>$s['recaudo'],'conciliado'=>$s['conciliado'],'en_revision'=>$s['en_revision'],'habitantes'=>$s['habitantes']];
            $tRec += $s['recaudo']; $tCon += $s['conciliado']; $tHab += $s['habitantes']; $tUni += $s['n_unidades'];
        }
        echo json_encode(['ok'=>true,'periodo'=>$periodo,'totales'=>['propiedades'=>count($props),'unidades'=>$tUni,'recaudo'=>$tRec,'conciliado'=>$tCon,'habitantes'=>$tHab],'data'=>$out]);
    })(),
    'solicitudes.list' => (function() {
        Auth::requireLogin();
        $pid = (int)($_GET['propiedad_id'] ?? 0);
        $ctx = Auth::contexto();
        if (!$ctx['es_superadmin']) { $ok=false; foreach($ctx['asignadas'] as $a) if((int)$a['propiedad_id']===$pid) $ok=true; if(!$ok){http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Sin acceso']); return;} }
        echo json_encode(['ok'=>true,'data'=>Solicitud::porPropiedad($pid)]);
    })(),
    'solicitudes.create' => (function() use ($in) {
        Auth::requireLogin();
        foreach (['propiedad_id','solicitante_nombre','solicitante_doc','solicitante_whatsapp'] as $k) if (empty($in[$k])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>"Falta $k"]); return; }
        echo json_encode(['ok'=>true,'id'=>Solicitud::crear($in)]);
    })(),
    'solicitudes.estado' => (function() use ($in) {
        Auth::requireLogin();
        Solicitud::cambiarEstado((int)$in['id'], $in['estado']);
        echo json_encode(['ok'=>true]);
    })(),
    'contratos.list' => (function() {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'data'=>Contrato::porPropiedad((int)($_GET['propiedad_id'] ?? 0))]);
    })(),
    'contratos.generar' => (function() use ($in) {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'id'=>Contrato::generarDesdeSolicitud((int)$in['solicitud_id'])]);
    })(),
    'contratos.estado' => (function() use ($in) {
        Auth::requireLogin();
        Contrato::cambiarEstado((int)$in['id'], $in['estado']);
        if (($in['estado'] ?? '') === 'aprobado_admin') {
            $pdo = \App\Core\Database::pdo();
            $sid = $pdo->query("SELECT solicitud_id FROM contratos WHERE id=".(int)$in['id'])->fetchColumn();
            if ($sid) Solicitud::cambiarEstado((int)$sid, 'contrato_enviado');
        }
        if (($in['estado'] ?? '') === 'firmado') {
            $pdo = \App\Core\Database::pdo();
            $sid = $pdo->query("SELECT solicitud_id FROM contratos WHERE id=".(int)$in['id'])->fetchColumn();
            if ($sid) Solicitud::cambiarEstado((int)$sid, 'firmado');
        }
        echo json_encode(['ok'=>true]);
    })(),
    'contratos.activar' => (function() use ($in) {
        Auth::requireLogin();
        Contrato::activar((int)$in['id']);
        echo json_encode(['ok'=>true]);
    })(),
    'contratos.variables' => (function() use ($in) {
        Auth::requireLogin();
        $id = (int)($in['id'] ?? 0);
        $pdo = \App\Core\Database::pdo();
        $cur = $pdo->query("SELECT variables_json FROM contratos WHERE id=$id")->fetchColumn();
        $v = json_decode($cur ?: '{}', true) ?: [];
        foreach (['ciudad','vigencia_meses','fecha_fin'] as $k) if (isset($in[$k])) $v[$k] = $in[$k];
        $pdo->prepare("UPDATE contratos SET variables_json=? WHERE id=?")->execute([json_encode($v), $id]);
        echo json_encode(['ok'=>true,'variables'=>$v]);
    })(),
    'archivos.upload' => (function() {
        Auth::requireLogin();
        // multipart: propiedad_id, solicitud_id, tipo, archivo
        $pid = (int)($_POST['propiedad_id'] ?? 0); $sid = (int)($_POST['solicitud_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'otro';
        if (!$pid || !$sid || empty($_FILES['archivo'])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Datos/archivo']); return; }
        $dir = '/var/www/html/storage/sol_'.$sid;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['archivo']['name']);
        $dest = $dir . '/' . time() . '_' . $name;
        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Subida']); return; }
        $st = \App\Core\Database::pdo()->prepare("INSERT INTO archivos (propiedad_id, solicitud_id, tipo, ruta, legible) VALUES (?,?,?,?,'t') RETURNING id");
        $st->execute([$pid, $sid, $tipo, $dest]);
        echo json_encode(['ok'=>true,'id'=>(int)$st->fetchColumn(),'ruta'=>$dest]);
    })(),
    'solicitudes.docs' => (function() {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'data'=>Solicitud::docs((int)($_GET['solicitud_id'] ?? 0))]);
    })(),
    'recibos.list' => (function() {
        Auth::requireLogin();
        $pid = (int)($_GET['propiedad_id'] ?? 0);
        $st = \App\Core\Database::pdo()->prepare("SELECT * FROM recibos WHERE propiedad_id=? ORDER BY periodo_hasta DESC, id DESC");
        $st->execute([$pid]); echo json_encode(['ok'=>true,'data'=>$st->fetchAll()]);
    })(),
    'recibos.create' => (function() use ($in) {
        Auth::requireLogin();
        $d = array_merge($in, $_POST);
        foreach (['propiedad_id','tipo','periodo_desde','periodo_hasta','total'] as $k) if (empty($d[$k])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>"Falta $k"]); return; }
        $pdo = \App\Core\Database::pdo();
        $ruta = null;
        if (!empty($_FILES['archivo']['tmp_name'] ?? null)) {
            $dir = '/var/www/html/storage/recibos';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = preg_replace('/[^A-Za-z0-9._-]/','_', $_FILES['archivo']['name']);
            $ruta = $dir . '/' . time() . '_' . $name;
            move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta);
        }
        $archId = null;
        if ($ruta) {
            $st = $pdo->prepare("INSERT INTO archivos (propiedad_id, tipo, ruta, legible, periodo_ref) VALUES (?, 'recibo_servicio', ?, 't', ?) RETURNING id");
            $st->execute([(int)$d['propiedad_id'], $ruta, $d['periodo_desde'] . '/' . $d['periodo_hasta']]);
            $archId = (int)$st->fetchColumn();
        }
        $st = $pdo->prepare("INSERT INTO recibos (propiedad_id, tipo, periodo_desde, periodo_hasta, total, no_trasladable, motivo_no, modo, unidad_destino, tipo_lectura, archivo_id) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
        $st->execute([(int)$d['propiedad_id'],$d['tipo'],$d['periodo_desde'],$d['periodo_hasta'],$d['total'],(float)($d['no_trasladable']??0),$d['motivo_no']??null,$d['modo']??'compartido',!empty($d['unidad_destino'])?(int)$d['unidad_destino']:null,$d['tipo_lectura']??'real',$archId]);
        echo json_encode(['ok'=>true,'id'=>(int)$st->fetchColumn()]);
    })(),
    'recibos.preview' => (function() {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'data'=>Liquidacion::preview((int)($_GET['id'] ?? 0))]);
    })(),
    'recibos.liquidar' => (function() use ($in) {
        Auth::requireLogin();
        echo json_encode(['ok'=>true,'data'=>Liquidacion::liquidar((int)($in['id'] ?? 0), (int)$_SESSION['user']['id'])]);
    })(),
    default => (function() { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Acción']); })(),
  };
} catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
