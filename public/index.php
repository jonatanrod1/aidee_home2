<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/../src/bootstrap.php';
$config = require __DIR__ . '/../src/config.php';
use App\Core\Auth;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/login') { include __DIR__ . '/login.php'; exit; }
if ($path === '/logout') { Auth::logout(); header('Location: /login'); exit; }
Auth::requireLogin();
$u = Auth::user();
$ctx = Auth::contexto();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aidee home</title><link rel="stylesheet" href="/style.css"></head><body>
<div class="layout">
<aside class="side">
<div class="brand"><span class="brand-ico">⌂</span><div><b>Aidee home</b><small>Inteligencia Artificial para Arriendos</small></div></div>
<div class="patrimonio" id="side-card"><small id="side-kicker">—</small><b id="side-prop">—</b><span id="side-n" class="muted">—</span></div>
<div id="prop-list"></div>
<nav id="nav">
<button class="active" data-m="tablero">▦ Dashboard</button>
<button data-m="liquidacion">◧ Liquidación de Servicios</button>
<button data-m="unidades">▦ Unidades &amp; Arrendatarios</button>
<button data-m="contratos">🗎 Contratos</button>
<button data-m="pagos">🗎 Pagos &amp; WhatsApp <em>Pronto</em></button>
<button data-m="reparto">◔ Copropietarios &amp; Reparto</button>
<button data-m="mantenimiento">👜 Mantenimiento &amp; Garantías</button>
<button data-m="config">⚙ Configuración &amp; Bot</button>
</nav>
<div class="gw-foot"><span class="dot"></span><div><b>Gateway Pendiente</b><small>Meta Cloud API (por configurar)</small></div></div>
</aside>
<div class="main">
<header class="top"><span class="pill" id="top-prop">—</span>
<span class="sp"></span><span class="user"><b><?=e($u['nombre'])?></b><small><?=e($u['whatsapp'] ?? $u['email'] ?? '')?> · <?=e($ctx['rol'])?></small></span>
<span class="avatar">A</span> <a href="/logout" title="Salir">⏻</a></header>
<main id="app"></main>
</div>
</div>
<script>const CTX = <?=json_encode($ctx)?>;</script>
<script src="/app.js"></script>
</body></html>
