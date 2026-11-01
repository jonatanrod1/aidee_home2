-- aidee_home2 - esquema base Postgres (v1 congelado + ajustes 100 casos + onboarding contrato)
-- Ejecutar con: psql -d aidee_home2 -f schema.sql

CREATE TABLE IF NOT EXISTS propiedades (
  id SERIAL PRIMARY KEY,
  ubicacion TEXT NOT NULL,
  duenio_nombre TEXT NOT NULL,
  duenio_doc TEXT NOT NULL,
  duenio_whatsapp TEXT NOT NULL,
  n_unidades INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS usuarios (
  id SERIAL PRIMARY KEY,
  nombre TEXT NOT NULL,
  whatsapp TEXT,
  email TEXT UNIQUE,
  pass_hash TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS roles_propiedad (
  id SERIAL PRIMARY KEY,
  usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  rol TEXT NOT NULL CHECK (rol IN ('superadmin','admin','auditor')),
  UNIQUE (usuario_id, propiedad_id, rol)
);

CREATE TABLE IF NOT EXISTS unidades (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  codigo TEXT NOT NULL,
  tipo TEXT NOT NULL DEFAULT 'apartamento' CHECK (tipo IN ('apartamento','habitacion')),
  valor_arriendo NUMERIC(12,2) NOT NULL,
  fecha_corte INT NOT NULL CHECK (fecha_corte BETWEEN 1 AND 28),
  cupo_max INT NOT NULL DEFAULT 4,
  estado TEXT NOT NULL DEFAULT 'libre' CHECK (estado IN ('libre','ocupada','mora','mantenimiento')),
  reglamento_json JSONB DEFAULT '{}',
  UNIQUE (propiedad_id, codigo)
);

CREATE TABLE IF NOT EXISTS personas (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT REFERENCES unidades(id) ON DELETE SET NULL,
  nombre TEXT NOT NULL,
  doc TEXT,
  whatsapp TEXT,
  tipo TEXT NOT NULL CHECK (tipo IN ('titular','ocupante','codeudor','familiar_apoyo')),
  fecha_entrada DATE,
  fecha_salida DATE,
  activo BOOLEAN DEFAULT true
);

-- Onboarding: solicitud antes de contrato
CREATE TABLE IF NOT EXISTS solicitudes (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT REFERENCES unidades(id) ON DELETE SET NULL,
  solicitante_nombre TEXT NOT NULL,
  solicitante_doc TEXT NOT NULL,
  solicitante_whatsapp TEXT NOT NULL,
  n_personas INT NOT NULL DEFAULT 1,
  tiene_mascota BOOLEAN DEFAULT false,
  fiador_nombre TEXT, fiador_doc TEXT, fiador_whatsapp TEXT,
  estado TEXT NOT NULL DEFAULT 'documentos_pendientes'
    CHECK (estado IN ('documentos_pendientes','en_validacion','aprobada','rechazada','contrato_borrador','contrato_enviado','firmado','activa')),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS archivos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT REFERENCES unidades(id) ON DELETE SET NULL,
  solicitud_id INT REFERENCES solicitudes(id) ON DELETE SET NULL,
  tipo TEXT NOT NULL CHECK (tipo IN ('cedula','referencia','fiador_doc','recibo_servicio','soporte_pago','foto_inventario','contrato_pdf','contrato_firmado','otro')),
  hash TEXT,
  ruta TEXT NOT NULL,
  legible BOOLEAN DEFAULT true,
  periodo_ref TEXT,
  subido_por INT REFERENCES usuarios(id),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS contratos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE RESTRICT,
  solicitud_id INT REFERENCES solicitudes(id) ON DELETE SET NULL,
  fecha_inicio DATE NOT NULL,
  fecha_inicio_servicios DATE NOT NULL,
  valor NUMERIC(12,2) NOT NULL,
  incremento_tipo TEXT DEFAULT 'IPC' CHECK (incremento_tipo IN ('IPC','FIJO','NINGUNO')),
  incremento_valor NUMERIC(6,2),
  deposito NUMERIC(12,2) NOT NULL DEFAULT 0,
  variables_json JSONB DEFAULT '{}',
  pdf_archivo_id INT REFERENCES archivos(id),
  firmado_archivo_id INT REFERENCES archivos(id),
  estado TEXT NOT NULL DEFAULT 'borrador'
    CHECK (estado IN ('borrador','aprobado_admin','enviado_firma','firmado','activo','terminado')),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS recibos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('luz','agua','gas')),
  periodo_desde DATE NOT NULL,
  periodo_hasta DATE NOT NULL,
  total NUMERIC(12,2) NOT NULL,
  no_trasladable NUMERIC(12,2) NOT NULL DEFAULT 0,
  motivo_no TEXT,
  modo TEXT NOT NULL DEFAULT 'compartido' CHECK (modo IN ('compartido','independiente')),
  unidad_destino INT REFERENCES unidades(id),
  tipo_lectura TEXT DEFAULT 'real' CHECK (tipo_lectura IN ('real','estimada')),
  radicado_operador TEXT,
  archivo_id INT REFERENCES archivos(id),
  clasificado BOOLEAN DEFAULT false
);

CREATE TABLE IF NOT EXISTS liquidaciones (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  periodo TEXT NOT NULL,
  arriendo_valor NUMERIC(12,2) NOT NULL,
  servicios_detalle JSONB NOT NULL DEFAULT '[]',
  total NUMERIC(12,2) NOT NULL,
  base_repartible NUMERIC(12,2) DEFAULT 0,
  persona_dia_unidad NUMERIC(10,2) DEFAULT 0,
  persona_dia_total NUMERIC(10,2) DEFAULT 0,
  estado TEXT NOT NULL DEFAULT 'pendiente'
    CHECK (estado IN ('pendiente','gracia','mora','pagado','acuerdo')),
  created_at TIMESTAMPTZ DEFAULT now(),
  UNIQUE (unidad_id, periodo)
);

CREATE TABLE IF NOT EXISTS pagos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  liquidacion_id INT NOT NULL REFERENCES liquidaciones(id) ON DELETE CASCADE,
  valor NUMERIC(12,2) NOT NULL,
  fecha_alegada DATE,
  transaccion_n TEXT,
  soporte_archivo_id INT REFERENCES archivos(id),
  estado TEXT NOT NULL DEFAULT 'por_validar'
    CHECK (estado IN ('por_validar','validado','rechazado')),
  aportante_persona_id INT REFERENCES personas(id),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS acuerdos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  saldo NUMERIC(12,2) NOT NULL,
  cuotas JSONB NOT NULL DEFAULT '[]',
  estado TEXT NOT NULL DEFAULT 'vigente' CHECK (estado IN ('vigente','roto','cumplido')),
  creador_admin_id INT REFERENCES usuarios(id),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS depositos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL UNIQUE REFERENCES unidades(id) ON DELETE CASCADE,
  valor_inicial NUMERIC(12,2) NOT NULL DEFAULT 0,
  retenido_30d NUMERIC(12,2) DEFAULT 0,
  estado TEXT DEFAULT 'activo'
);

CREATE TABLE IF NOT EXISTS depositos_mov (
  id SERIAL PRIMARY KEY,
  deposito_id INT NOT NULL REFERENCES depositos(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('cargo','abono','retencion','devolucion')),
  motivo TEXT NOT NULL,
  valor NUMERIC(12,2) NOT NULL,
  evidencia_archivo_id INT REFERENCES archivos(id),
  creado_por INT REFERENCES usuarios(id),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS cargos_extra (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  motivo TEXT NOT NULL,
  valor NUMERIC(12,2) NOT NULL,
  evidencia_archivo_id INT REFERENCES archivos(id),
  estado TEXT DEFAULT 'pendiente' CHECK (estado IN ('pendiente','pagado','cruzado_deposito'))
);

CREATE TABLE IF NOT EXISTS notas_ajuste (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  liquidacion_id INT REFERENCES liquidaciones(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('credito','debito')),
  valor NUMERIC(12,2) NOT NULL,
  motivo TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS autorizaciones (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('mascota','negocio','subarriendo','descuento','cambio_chapa','independizar','visita_permanente','otro')),
  detalle JSONB DEFAULT '{}',
  vigencia_desde DATE, vigencia_hasta DATE,
  estado TEXT DEFAULT 'pendiente' CHECK (estado IN ('pendiente','aprobada','negada')),
  autorizador_id INT REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS visitas (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  fecha_desde DATE NOT NULL,
  n_personas INT NOT NULL,
  n_dias INT NOT NULL,
  reportada BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS actas (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('entrada','salida','toma','inspeccion')),
  fotos_ids INT[] DEFAULT '{}',
  firma BOOLEAN DEFAULT false,
  testigo TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS preavisos (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT NOT NULL REFERENCES unidades(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL CHECK (tipo IN ('renovar','anticipada','fin_30d','abandono','desalojo')),
  fecha_aviso TIMESTAMPTZ DEFAULT now(),
  fecha_salida_min DATE,
  estado TEXT DEFAULT 'vigente'
);

CREATE TABLE IF NOT EXISTS notificaciones (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT REFERENCES unidades(id) ON DELETE CASCADE,
  persona_id INT REFERENCES personas(id) ON DELETE SET NULL,
  tipo TEXT NOT NULL,
  contenido TEXT NOT NULL,
  hora_envio TIMESTAMPTZ DEFAULT now(),
  acuse_lectura TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS tickets (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  unidad_id INT REFERENCES unidades(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL,
  prioridad TEXT DEFAULT 'media' CHECK (prioridad IN ('alta','media','baja')),
  sla_vence TIMESTAMPTZ,
  reincidencia INT DEFAULT 0,
  estado TEXT DEFAULT 'abierto' CHECK (estado IN ('abierto','en_proceso','cerrado')),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS cierres_mes (
  id SERIAL PRIMARY KEY,
  propiedad_id INT NOT NULL REFERENCES propiedades(id) ON DELETE CASCADE,
  mes TEXT NOT NULL,
  recaudado NUMERIC(12,2) DEFAULT 0,
  mora NUMERIC(12,2) DEFAULT 0,
  servicios NUMERIC(12,2) DEFAULT 0,
  observacion_auditor TEXT,
  inmutable BOOLEAN DEFAULT false,
  UNIQUE (propiedad_id, mes)
);

CREATE INDEX IF NOT EXISTS idx_liq_prop ON liquidaciones(propiedad_id);
CREATE INDEX IF NOT EXISTS idx_pagos_liq ON pagos(liquidacion_id);
CREATE INDEX IF NOT EXISTS idx_notif_unidad ON notificaciones(unidad_id);
CREATE INDEX IF NOT EXISTS idx_arch_sol ON archivos(solicitud_id);
