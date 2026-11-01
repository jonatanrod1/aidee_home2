<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar · aidee_home2</title><link rel="stylesheet" href="/style.css"></head><body class="login-body">
<div class="login-card">
<div class="brand"><span class="brand-ico">⌂</span><div><b>Aidee home</b><small>Inteligencia Artificial para Arriendos</small></div></div>
<h3>Entrar con WhatsApp</h3>
<form id="f">
<label>Número WhatsApp<input name="user" inputmode="tel" placeholder="3000000000" required></label>
<label>Contraseña<input name="password" type="password" value="admin123" required></label>
<button class="btn-green" type="submit">Entrar</button>
</form>
<p id="msg" class="muted"></p>
<p class="muted small">La llave de entrada es tu número de WhatsApp. Los usuarios nuevos se crean con clave <b>admin123</b>.</p>
</div>
<script>
document.getElementById('f').onsubmit = async e => {
  e.preventDefault();
  const fd = Object.fromEntries(new FormData(e.target));
  const r = await fetch('/api.php?action=login', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(fd)});
  const j = await r.json();
  document.getElementById('msg').textContent = j.ok ? 'OK, entrando…' : (j.error||'Error');
  if (j.ok) location.href = '/';
};
</script>
</body></html>
