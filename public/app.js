let PROPS = [], PID = 0, ES_SUPER = !!(CTX && CTX.es_superadmin);
const PERIODO = new Date().toISOString().slice(0,7);
const MESES = { '01':'Enero','02':'Febrero','03':'Marzo','04':'Abril','05':'Mayo','06':'Junio','07':'Julio','08':'Agosto','09':'Septiembre','10':'Octubre','11':'Noviembre','12':'Diciembre' };
const periodoTxt = () => { const [y,m] = PERIODO.split('-'); return `${MESES[m]} ${y}`; };
const fmt = n => '$' + Number(n||0).toLocaleString('es-CO', {maximumFractionDigits:0});
async function api(action, data={}, qs='') {
  const r = await fetch('/api.php?action='+action+qs, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
  return r.json();
}
async function boot() {
  const p = await api('propiedades.list');
  PROPS = p.ok ? p.data : [];
  CTX.es_superadmin = !!(p.ctx && p.ctx.es_superadmin);
  ES_SUPER = CTX.es_superadmin;
  document.querySelectorAll('#nav button').forEach(b => b.onclick = () => {
    document.querySelectorAll('#nav button').forEach(x=>x.classList.remove('active')); b.classList.add('active');
    render(b.dataset.m);
  });
  if (!PROPS.length) { document.getElementById('app').innerHTML = '<div class=card>Sin propiedades asignadas.</div>'; return; }
  PID = PROPS[0].id;
  pintarListaProps();
  render('tablero');
}
function pintarListaProps() {
  const el = document.getElementById('prop-list');
  if (PROPS.length < 2) { el.innerHTML = ''; return; }
  el.innerHTML = '<small class=muted>MIS PROPIEDADES</small>' + PROPS.map(x=>`<button class="${x.id===PID?'active':''}" onclick="PID=${x.id};pintarListaProps();render('tablero')">⌂ ${x.ubicacion}</button>`).join('');
}
async function render(m) {
  if (m === 'contratos') return renderContratos();
  if (m === 'liquidacion') return renderLiq();
  if (m !== 'tablero') { document.getElementById('app').innerHTML = `<div class=card><b>${m}</b><p class=muted>En construcción en este MVP.</p></div>`; return; }
  if (ES_SUPER) return renderSuper();
  return renderAdmin();
}
// SUPERADMIN: portafolio SaaS, sin "patrimonio familiar"
async function renderSuper() {
  const app = document.getElementById('app');
  document.getElementById('side-kicker').textContent = 'Vista SuperAdministrador';
  document.getElementById('side-prop').textContent = PROPS.length + ' propiedades';
  document.getElementById('side-n').textContent = 'Vista superadmin';
  document.getElementById('prop-list').innerHTML = '';
  document.getElementById('top-prop').textContent = `▦ Portafolio`;
  const g = await (await fetch('/api.php?action=dashboard.global&periodo='+PERIODO, {method:'POST'})).json();
  const t = g.totales;
  app.innerHTML = `<h1>Portafolio - Aidee home</h1><div class=muted>Período ${periodoTxt()} • ${t.propiedades} propiedades • ${t.unidades} unidades • ${t.habitantes} habitantes</div>
  <div class=kpis>
    <div class=kpi><small>RECAUDO TOTAL</small><b class=big>${fmt(t.recaudo)}</b></div>
    <div class=kpi><small>CONCILIADO</small><b class=big>${fmt(t.conciliado)}</b></div>
    <div class=kpi><small>PROPIEDADES</small><b class=big>${t.propiedades}</b></div>
    <div class=kpi><small>UNIDADES</small><b class=big>${t.unidades}</b></div>
  </div>
  <div class=card><b>Propiedades (clientes SaaS)</b>
  <div class=row-end><button class=btn-green onclick="crearP()">+ Nueva propiedad</button></div>
  <table><tr><th>ID</th><th>UBICACIÓN / DUEÑO</th><th>UNIDADES</th><th>RECAUDO</th><th>CONCILIADO</th><th>REV.</th><th></th></tr>
  ${g.data.map(x=>`<tr><td>${x.id}</td><td><b>${x.ubicacion}</b><br><span class=muted>${x.duenio}</span></td><td>${x.tipos_txt}</td><td>${fmt(x.recaudo)}</td><td>${fmt(x.conciliado)}</td><td>${x.en_revision}</td><td><button onclick="PID=${x.id};renderAdmin()">Abrir</button></td></tr>`).join('')}
  </table></div><div id=sub></div>`;
}
async function crearP() {
  const ubicacion = prompt('Ubicación:'); if (!ubicacion) return;
  const duenio_nombre = prompt('Dueño:')||''; const duenio_doc = prompt('Doc:')||'';
  const duenio_whatsapp = prompt('WhatsApp dueño:')||'';
  const j = await api('propiedades.create', {ubicacion, duenio_nombre, duenio_doc, duenio_whatsapp});
  if (j.ok) boot(); else alert(j.error);
}
// ADMIN: vista por propiedad estilo ficha (aquí sí aplica "patrimonio")
async function renderAdmin() {
  const app = document.getElementById('app');
  pintarListaProps();
  app.innerHTML = `<div class=head-row><span class=sp></span>
    ${ES_SUPER ? '<button class=btn-light onclick="editarProp()">✎ Editar propiedad</button>' : ''}
    <button class=btn-green onclick="alert('Nuevo contrato: fase admin')">🗎 + Nuevo contrato</button>
    ${ES_SUPER ? '<button class=btn-light onclick="nuevaUnidad()">▦ + Nueva unidad</button>' : ''}</div><div id=dash>Cargando…</div>`;
  const r = await fetch('/api.php?action=dashboard.stats&propiedad_id='+PID+'&periodo='+PERIODO, {method:'POST'});
  const j = await r.json();
  if (!j.ok) { document.getElementById('dash').textContent = 'Error'; return; }
  const d = j.data;
  document.getElementById('side-kicker').textContent = ES_SUPER ? 'Vista SuperAdministrador' : 'Vista Administrador';
  document.getElementById('side-prop').textContent = d.propiedad.ubicacion;
  document.getElementById('side-n').textContent = d.tipos_txt;
  document.getElementById('top-prop').textContent = '⌂ Vista Administrador';
  const pct = d.recaudo ? Math.round(100*d.conciliado/d.recaudo) : 0;
  document.getElementById('dash').innerHTML = `
  <h1>Tablero de Control - ${d.propiedad.ubicacion}</h1>
  <div class=muted>Período: ${periodoTxt()} • ${d.contratos_vigentes}/${d.n_unidades} Unidades con contrato (${d.habitantes} Habitantes)</div>
  <div class=kpis>
    <div class=kpi><small>RECAUDO ESTIMADO DEL MES</small><b class=big>${fmt(d.recaudo)}</b>
      <div class=row><span>Canon:<br>${fmt(d.canon)}</span><span>Servicios:<br>${fmt(d.servicios)}</span></div><div class=bar><i style="width:90%"></i></div></div>
    <div class=kpi><small>PAGOS CONCILIADOS</small><b class=big>${fmt(d.conciliado)}</b>
      <div class=row><span>${d.en_revision} en revisión</span><b>${pct}%</b></div><div class=bar><i style="width:${pct}%"></i></div></div>
    <div class=kpi><small>FONDO RESERVA &amp; CAJA</small><b class=big>${fmt(0)}</b>
      <div class=row><span>Reserva (10%):<br>${fmt(d.reserva)}</span><span>Neto:<br>${fmt(d.neto)}</span></div><div class=bar><i style="width:12%"></i></div></div>
    <div class=kpi><small>HABITANTES ACTIVOS</small><b class=big>${d.habitantes} <small>Personas</small></b><div class=bar><i style="width:70%"></i></div></div>
  </div>
  <div class=grid2><div><div class=card><b>Monitor de Unidades y Estado de Pagos</b>
    <table><tr><th>UNIDAD / INQUILINO</th><th>HAB.</th><th>CANON</th><th>SERVICIOS</th><th>TOTAL</th><th>VENCE</th><th>ESTADO</th></tr>
    ${d.monitor.map(m=>`<tr><td><span class=code>${m.codigo}</span> <b>${m.inquilino}</b>${m.tipo?`<br><span class=muted>${m.tipo}</span>`:''}</td><td>${m.hab}</td><td>${fmt(m.canon)}</td><td>${fmt(m.servicios)}</td><td><b>${fmt(m.total)}</b></td><td>${m.vence}</td><td><span class=tag>${m.estado==='sin_liquidar'?'Sin liquidar':m.estado}</span>${ES_SUPER?` <button onclick="editarUnidad(${m.unidad_id},'${m.codigo}')">✎</button>`:''}</td></tr>`).join('')}
    </table></div></div><div>
    <div class=card><b>Conciliación</b> <span class=tag>${d.cola.length} En Cola</span>
      <p class=muted>${d.cola.length ? d.cola.map(c=>`#${c.id} ${c.codigo} ${fmt(c.valor)}`).join('<br>') : 'Sin soportes pendientes.'}</p></div>
    <div class=card><b>Depósitos</b><p class=muted>${d.depositos.length ? d.depositos.map(x=>`${x.codigo}: ${fmt(x.valor_inicial)}`).join('<br>') : 'Sin depósitos.'}</p></div>
    ${ES_SUPER ? '<button onclick="renderSuper()">← Volver al portafolio</button>' : ''}
  </div></div>`;
}
async function nuevaUnidad() {
  const codigo = prompt('Código (ej 101):'); if (!codigo) return;
  const valor = +prompt('Valor arriendo:', '900000'); const corte = +prompt('Día corte 1-28:', '5');
  const data = {propiedad_id: PID, codigo, valor_arriendo: valor, fecha_corte: corte};
  if (ES_SUPER) {
    const tipo = prompt('Tipo (apartamento/habitacion/local):', 'apartamento');
    if (tipo) data.tipo = tipo;
  }
  const j = await api('unidades.create', data);
  if (j.ok) renderAdmin(); else alert(j.error);
}
async function editarUnidad(id, codigo) {
  // Solo superadmin: el admin no edita configuración de la unidad
  const tipo = prompt(`Tipo de ${codigo} (apartamento/habitacion/local):`);
  if (!tipo) return;
  const j = await api('unidades.update', {id, tipo});
  if (j.ok) renderAdmin(); else alert(j.error);
}
async function editarProp() {
  const p = PROPS.find(x => x.id === PID);
  if (!p) return;
  const app = document.getElementById('app');
  const card = document.createElement('div');
  card.className = 'card';
  card.innerHTML = `<b>✎ Editar propiedad (esto es lo que ves como “PATRIMONIO FAMILIAR”)</b><br>
  Nombre visible <input id=e_ubi value="${p.ubicacion||''}"> Dueño <input id=e_nom value="${p.duenio_nombre||''}">
  Doc dueño <input id=e_doc value="${p.duenio_doc||''}"> WhatsApp dueño <input id=e_wa value="${p.duenio_whatsapp||''}">
  <button class=btn-green onclick="guardarProp()">Guardar</button> <button onclick="renderAdmin()">Cancelar</button>`;
  app.prepend(card);
}
async function guardarProp() {
  const j = await api('propiedades.update', {propiedad_id: PID, ubicacion: v('e_ubi'), duenio_nombre: v('e_nom'), duenio_doc: v('e_doc'), duenio_whatsapp: v('e_wa')});
  if (!j.ok) { alert(j.error); return; }
  const p = await api('propiedades.list'); PROPS = p.data; renderAdmin();
}
async function renderContratos() {
  const app = document.getElementById('app');
  const s = await (await fetch('/api.php?action=solicitudes.list&propiedad_id='+PID, {method:'POST'})).json();
  const c = await (await fetch('/api.php?action=contratos.list&propiedad_id='+PID, {method:'POST'})).json();
  const unis = await api('unidades.list', {propiedad_id: PID});
  const uopts = (unis.data||[]).map(u=>`<option value="${u.id}">${u.codigo}</option>`).join('');
  app.innerHTML = `<h1>Solicitudes → Contrato (prop ${PID})</h1>
  <div class=card><b>Nueva solicitud</b><br>
  Nombre <input id=s_nom> Doc <input id=s_doc> WhatsApp <input id=s_wa> Personas <input id=s_nper type=number value=1>
  Unidad <select id=s_uni>${uopts}</select> Fiador <input id=s_fiador placeholder="nombre fiador (opcional)">
  <button class=btn-green onclick="crearS()">Crear</button></div>
  <div class=grid2><div class=card><b>Solicitudes (${(s.data||[]).length})</b>
  <table><tr><th>ID</th><th>SOLICITANTE</th><th>UNID</th><th>ESTADO</th><th>ACCIONES</th></tr>
  ${(s.data||[]).map(x=>`<tr><td>${x.id}</td><td>${x.solicitante_nombre}<br><span class=muted>${x.solicitante_whatsapp}</span></td><td>${x.codigo||x.unidad_id||'—'}</td><td><span class=tag>${x.estado}</span></td>
  <td><button onclick="setSol(${x.id},'en_validacion')">Validar</button> <button onclick="setSol(${x.id},'aprobada')">Aprobar</button> <button onclick="genC(${x.id})">Contrato</button> <button onclick="docsS(${x.id})">Docs</button></td></tr>`).join('')}
  </table><div id=docs></div></div>
  <div class=card><b>Contratos (${(c.data||[]).length})</b>
  <table><tr><th>ID</th><th>UNID</th><th>VALOR</th><th>ESTADO</th><th>ACCIONES</th></tr>
  ${(c.data||[]).map(x=>`<tr><td>${x.id}</td><td>${x.codigo}</td><td>${fmt(x.valor)}</td><td><span class=tag>${x.estado}</span></td>
  <td><button onclick="window.open('/contrato.php?id=${x.id}','_blank')">Ver PDF</button> <button onclick="setC(${x.id},'aprobado_admin')">Aprobar</button> <button onclick="setC(${x.id},'enviado_firma')">Enviado</button> <button onclick="setC(${x.id},'firmado')">Firmado</button> <button onclick="activarC(${x.id})">Activar</button></td></tr>`).join('')}
  </table>
  <p class=muted>Flujo: documentos → validación → aprobada → generar contrato (borrador) → aprobar admin (marca solicitud enviada) → enviado firma → firmado → <b>Activar</b> (crea titular + depósito + ocupa unidad). Plantilla: /templates/contrato_base.html.</p></div></div>`;
}
async function crearS() {
  const j = await api('solicitudes.create', {propiedad_id: PID, unidad_id: +document.getElementById('s_uni').value || null,
    solicitante_nombre: v('s_nom'), solicitante_doc: v('s_doc'), solicitante_whatsapp: v('s_wa'),
    n_personas: +v('s_nper')||1, fiador_nombre: v('s_fiador')});
  if (j.ok) renderContratos(); else alert(j.error);
}
async function setSol(id, estado) { await api('solicitudes.estado', {id, estado}); renderContratos(); }
async function genC(sid) { const j = await api('contratos.generar', {solicitud_id: sid}); if (j.ok) renderContratos(); else alert(j.error); }
async function setC(id, estado) { await api('contratos.estado', {id, estado}); renderContratos(); }
async function activarC(id) { const j = await api('contratos.activar', {id}); if (j.ok) { alert('Activada: titular + depósito + unidad ocupada'); renderContratos(); } else alert(j.error); }
async function docsS(sid) {  const d = await (await fetch('/api.php?action=solicitudes.docs&solicitud_id='+sid, {method:'POST'})).json();
  document.getElementById('docs').innerHTML = `<b>Docs sol ${sid} (${(d.data||[]).length})</b>
  <form id=upf><select name=tipo><option value=cedula>Cédula</option><option value=referencia>Referencia</option><option value=fiador_doc>Fiador</option><option value=otro>Otro</option></select>
  <input type=file name=archivo required><button>Subir</button></form><ul>${(d.data||[]).map(x=>`<li>${x.tipo} · ${x.ruta}</li>`).join('')}</ul>`;
  document.getElementById('upf').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('propiedad_id', PID); fd.append('solicitud_id', sid);
    const r = await fetch('/api.php?action=archivos.upload', {method:'POST', body: fd});
    const j = await r.json(); if (j.ok) docsS(sid); else alert(j.error);
  };
}
async function renderLiq() {
  const app = document.getElementById('app');
  const r = await (await fetch('/api.php?action=recibos.list&propiedad_id='+PID, {method:'POST'})).json();
  const unis = await api('unidades.list', {propiedad_id: PID});
  const uopts = (unis.data||[]).map(u=>`<option value="${u.id}">${u.codigo}</option>`).join('');
  app.innerHTML = `<h1>Liquidación de Servicios (prop ${PID})</h1>
  <div class=card><b>Cargar recibo</b> <span class=muted>(luego llegará por WhatsApp; hoy se carga aquí igual)</span><br>
  <form id=rf>Tipo <select name=tipo><option>luz</option><option>agua</option><option>gas</option></select>
  Desde <input type=date name=periodo_desde required> Hasta <input type=date name=periodo_hasta required>
  Total <input name=total type=number required> No trasladable <input name=no_trasladable type=number value=0>
  Motivo no <input name=motivo_no placeholder="crédito/seguro/revisión">
  Modo <select name=modo><option>compartido</option><option value=independiente>independiente</option></select>
  Unidad destino (solo independiente) <select name=unidad_destino><option value="">—</option>${uopts}</select>
  Foto <input type=file name=archivo><button class=btn-green>Cargar</button></form></div>
  <div class=card><b>Recibos (${(r.data||[]).length})</b>
  <table><tr><th>ID</th><th>TIPO</th><th>PERIODO</th><th>TOTAL</th><th>NO TRASL.</th><th>MODO</th><th>ACCIONES</th></tr>
  ${(r.data||[]).map(x=>`<tr><td>${x.id}</td><td>${x.tipo}</td><td>${x.periodo_desde}/${x.periodo_hasta}</td><td>${fmt(x.total)}</td><td>${fmt(x.no_trasladable)}</td><td>${x.modo}</td>
  <td><button onclick="prevR(${x.id})">Ver reparto</button> <button onclick="liqR(${x.id})">Validar y liquidar</button></td></tr>`).join('')}
  </table><div id=pv></div></div>`;
  document.getElementById('rf').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('propiedad_id', PID);
    const j = await (await fetch('/api.php?action=recibos.create', {method:'POST', body: fd})).json();
    if (j.ok) renderLiq(); else alert(j.error);
  };
}
async function prevR(id) {
  const j = await (await fetch('/api.php?action=recibos.preview&id='+id, {method:'POST'})).json();
  if (!j.ok) { alert(j.error); return; }
  const d = j.data;
  document.getElementById('pv').innerHTML = `<h4>Reparto recibo ${d.recibo.tipo} ${d.recibo.periodo_desde}/${d.recibo.periodo_hasta}: total ${fmt(d.recibo.total)} − no trasladable ${fmt(d.recibo.no_trasladable)} = <b>${fmt(d.base)}</b></h4>
  <table><tr><th>UNIDAD</th><th>JUSTIFICACIÓN</th><th>VALOR</th></tr>
  ${d.filas.map(f=>`<tr><td><span class=code>${f.codigo}</span></td><td>${f.detalle}</td><td><b>${fmt(f.valor)}</b></td></tr>`).join('')}
  </table><p class=muted>Revisa y luego pulsa “Validar y liquidar”. Al validar se genera la liquidación y el mensaje WhatsApp con fecha límite (envío real en fase final).</p>`;
}
async function liqR(id) {
  const j = await api('recibos.liquidar', {id});
  if (!j.ok) { alert(j.error); return; }
  document.getElementById('pv').innerHTML = `<h4>Liquidado periodo ${j.data.periodo}</h4>
  ${j.data.mensajes.map(m=>`<div class=card><b>${m.unidad} → ${fmt(m.valor)}</b> (límite ${m.limite})<br><span class=muted>${m.mensaje}</span></div>`).join('')}`;
}
boot();
