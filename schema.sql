-- =====================================================================
-- UIP-MINGOB - Esquema de base de datos (Fase 2)
-- Generado a partir de gen_schema.py - validado contra PostgreSQL 16
-- =====================================================================
-- Tabla users (stub equivalente a la migracion por defecto de Laravel;
-- en el proyecto real esta tabla ya la crea Laravel, aqui se agrega solo
-- para poder validar las llaves foraneas contra Postgres en esta sesion)
DROP TABLE IF EXISTS "users" CASCADE;
CREATE TABLE "users" (
    "id" BIGSERIAL PRIMARY KEY,
    "name" VARCHAR(255) NOT NULL,
    "email" VARCHAR(255) NOT NULL UNIQUE,
    "email_verified_at" TIMESTAMP NULL,
    "password" VARCHAR(255) NOT NULL,
    "remember_token" VARCHAR(100) NULL,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Catalogo de roles (spec seccion 27 / tabla 10)
DROP TABLE IF EXISTS "roles" CASCADE;
CREATE TABLE "roles" (
    "id" BIGSERIAL PRIMARY KEY,
    "nombre" VARCHAR(255) NOT NULL,
    "descripcion" TEXT,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Catalogo de permisos granulares
DROP TABLE IF EXISTS "permissions" CASCADE;
CREATE TABLE "permissions" (
    "id" BIGSERIAL PRIMARY KEY,
    "clave" VARCHAR(255) NOT NULL,
    "nombre" VARCHAR(255) NOT NULL,
    "descripcion" TEXT
);
-- Pivote roles<->permisos
DROP TABLE IF EXISTS "role_permission" CASCADE;
CREATE TABLE "role_permission" (
    "id" BIGSERIAL PRIMARY KEY,
    "role_id" BIGINT NOT NULL,
    "permission_id" BIGINT NOT NULL,
    CONSTRAINT "fk_role_permission_role_id" FOREIGN KEY ("role_id") REFERENCES "roles"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_role_permission_permission_id" FOREIGN KEY ("permission_id") REFERENCES "permissions"("id") ON DELETE CASCADE
);
-- Dependencias/unidades a las que se asignan solicitudes (spec tabla 10)
DROP TABLE IF EXISTS "dependencias" CASCADE;
CREATE TABLE "dependencias" (
    "id" BIGSERIAL PRIMARY KEY,
    "codigo" VARCHAR(255),
    "nombre" VARCHAR(255) NOT NULL,
    "descripcion" TEXT,
    "activa" BOOLEAN NOT NULL DEFAULT TRUE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Persona de contacto/enlace de una dependencia
DROP TABLE IF EXISTS "enlaces" CASCADE;
CREATE TABLE "enlaces" (
    "id" BIGSERIAL PRIMARY KEY,
    "dependencia_id" BIGINT NOT NULL,
    "user_id" BIGINT,
    "nombre" VARCHAR(255) NOT NULL,
    "correo" VARCHAR(255),
    "telefono" VARCHAR(255),
    "activo" BOOLEAN NOT NULL DEFAULT TRUE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_enlaces_dependencia_id" FOREIGN KEY ("dependencia_id") REFERENCES "dependencias"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_enlaces_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Ciudadanos/solicitantes (datos demograficos del formulario real)
DROP TABLE IF EXISTS "solicitantes" CASCADE;
CREATE TABLE "solicitantes" (
    "id" BIGSERIAL PRIMARY KEY,
    "nombre" VARCHAR(255) NOT NULL,
    "correo" VARCHAR(255),
    "telefono" VARCHAR(255),
    "genero" VARCHAR(255),
    "rango_edad" VARCHAR(255),
    "pais" VARCHAR(255),
    "departamento" VARCHAR(255),
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Catalogo configurable de estados (spec seccion 25)
DROP TABLE IF EXISTS "solicitud_estados" CASCADE;
CREATE TABLE "solicitud_estados" (
    "id" BIGSERIAL PRIMARY KEY,
    "clave" VARCHAR(255) NOT NULL,
    "etiqueta" VARCHAR(255) NOT NULL,
    "color" VARCHAR(255),
    "orden" INTEGER NOT NULL DEFAULT 0,
    "es_final" BOOLEAN NOT NULL DEFAULT FALSE
);
-- Expedientes / solicitudes de informacion publica
DROP TABLE IF EXISTS "solicitudes" CASCADE;
CREATE TABLE "solicitudes" (
    "id" BIGSERIAL PRIMARY KEY,
    "codigo_ns" VARCHAR(255) NOT NULL,
    "contrasena" VARCHAR(255),
    "codigo_acceso" VARCHAR(255) NOT NULL,
    "solicitante_id" BIGINT NOT NULL,
    "asunto" TEXT NOT NULL,
    "medio_recepcion" VARCHAR(40) NOT NULL DEFAULT 'electronica' CHECK ("medio_recepcion" IN ('fisica', 'electronica', 'correo')),
    "es_informacion_publica" VARCHAR(40) NOT NULL DEFAULT 'pendiente' CHECK ("es_informacion_publica" IN ('si', 'no', 'pendiente')),
    "es_competencia" VARCHAR(40) NOT NULL DEFAULT 'pendiente' CHECK ("es_competencia" IN ('si', 'no', 'pendiente')),
    "requiere_aclaracion" BOOLEAN NOT NULL DEFAULT FALSE,
    "observaciones" TEXT,
    "estado_id" BIGINT NOT NULL,
    "dependencia_id" BIGINT,
    "enlace_id" BIGINT,
    "fecha_ingreso" DATE NOT NULL,
    "fecha_vencimiento" DATE,
    "fecha_respuesta" DATE,
    "fecha_finalizacion" DATE,
    "creado_por_user_id" BIGINT,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    "deleted_at" TIMESTAMP NULL,
    CONSTRAINT "fk_solicitudes_solicitante_id" FOREIGN KEY ("solicitante_id") REFERENCES "solicitantes"("id") ON DELETE RESTRICT,
    CONSTRAINT "fk_solicitudes_estado_id" FOREIGN KEY ("estado_id") REFERENCES "solicitud_estados"("id") ON DELETE RESTRICT,
    CONSTRAINT "fk_solicitudes_dependencia_id" FOREIGN KEY ("dependencia_id") REFERENCES "dependencias"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_solicitudes_enlace_id" FOREIGN KEY ("enlace_id") REFERENCES "enlaces"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_solicitudes_creado_por_user_id" FOREIGN KEY ("creado_por_user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Linea de tiempo / bitacora de cada expediente (solo lectura)
DROP TABLE IF EXISTS "solicitud_historial" CASCADE;
CREATE TABLE "solicitud_historial" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "user_id" BIGINT,
    "tipo_actor" VARCHAR(40) NOT NULL DEFAULT 'sistema' CHECK ("tipo_actor" IN ('sistema', 'administrador', 'ciudadano')),
    "descripcion" TEXT NOT NULL,
    "estado_anterior_id" BIGINT,
    "estado_nuevo_id" BIGINT,
    "metadata" JSONB,
    "created_at" TIMESTAMP NULL,
    CONSTRAINT "fk_solicitud_historial_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_solicitud_historial_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_solicitud_historial_estado_anterior_id" FOREIGN KEY ("estado_anterior_id") REFERENCES "solicitud_estados"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_solicitud_historial_estado_nuevo_id" FOREIGN KEY ("estado_nuevo_id") REFERENCES "solicitud_estados"("id") ON DELETE SET NULL
);
-- Registro formal de cada actuacion (spec tabla 7)
DROP TABLE IF EXISTS "actuaciones" CASCADE;
CREATE TABLE "actuaciones" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "tipo" VARCHAR(40) NOT NULL CHECK ("tipo" IN ('aclaracion', 'respuesta_aclaracion', 'prorroga', 'ampliacion', 'recurso_revision', 'notificacion_resolucion', 'finalizacion', 'otra')),
    "iniciado_por" VARCHAR(40) NOT NULL DEFAULT 'uip' CHECK ("iniciado_por" IN ('ciudadano', 'uip')),
    "user_id" BIGINT,
    "fecha" DATE NOT NULL,
    "descripcion" TEXT,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_actuaciones_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_actuaciones_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Plantillas de documentos generables (spec tabla 3)
DROP TABLE IF EXISTS "plantillas_documentos" CASCADE;
CREATE TABLE "plantillas_documentos" (
    "id" BIGSERIAL PRIMARY KEY,
    "clave" VARCHAR(255) NOT NULL,
    "nombre" VARCHAR(255) NOT NULL,
    "tipo" VARCHAR(40) NOT NULL DEFAULT 'docx' CHECK ("tipo" IN ('docx', 'pdf')),
    "contenido" TEXT NOT NULL,
    "visible_ciudadano_default" BOOLEAN NOT NULL DEFAULT FALSE,
    "activa" BOOLEAN NOT NULL DEFAULT TRUE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Documentos internos y publicados al ciudadano
DROP TABLE IF EXISTS "documentos" CASCADE;
CREATE TABLE "documentos" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "actuacion_id" BIGINT,
    "plantilla_id" BIGINT,
    "nombre" VARCHAR(255) NOT NULL,
    "ruta_archivo" VARCHAR(255) NOT NULL,
    "tipo" VARCHAR(40) NOT NULL DEFAULT 'pdf' CHECK ("tipo" IN ('docx', 'pdf', 'otro')),
    "visible_ciudadano" BOOLEAN NOT NULL DEFAULT FALSE,
    "subido_por_user_id" BIGINT,
    "subido_por_ciudadano" BOOLEAN NOT NULL DEFAULT FALSE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_documentos_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_documentos_actuacion_id" FOREIGN KEY ("actuacion_id") REFERENCES "actuaciones"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_documentos_plantilla_id" FOREIGN KEY ("plantilla_id") REFERENCES "plantillas_documentos"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_documentos_subido_por_user_id" FOREIGN KEY ("subido_por_user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Prorrogas del plazo de 10 dias habiles
DROP TABLE IF EXISTS "prorrogas" CASCADE;
CREATE TABLE "prorrogas" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "actuacion_id" BIGINT,
    "documento_id" BIGINT,
    "user_id" BIGINT NOT NULL,
    "fecha_anterior" DATE NOT NULL,
    "fecha_nueva" DATE NOT NULL,
    "solicitada_en" DATE NOT NULL,
    "motivo" TEXT,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_prorrogas_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_prorrogas_actuacion_id" FOREIGN KEY ("actuacion_id") REFERENCES "actuaciones"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_prorrogas_documento_id" FOREIGN KEY ("documento_id") REFERENCES "documentos"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_prorrogas_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE RESTRICT
);
-- Aclaraciones solicitadas al ciudadano (plazo real: 2 dias habiles)
DROP TABLE IF EXISTS "aclaraciones" CASCADE;
CREATE TABLE "aclaraciones" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "actuacion_id" BIGINT,
    "documento_id" BIGINT,
    "user_id" BIGINT NOT NULL,
    "fecha_solicitud" DATE NOT NULL,
    "plazo_dias_habiles" INTEGER NOT NULL DEFAULT 2,
    "fecha_limite_respuesta" DATE NOT NULL,
    "fecha_respuesta" DATE,
    "respuesta" TEXT,
    "estado" VARCHAR(40) NOT NULL DEFAULT 'pendiente' CHECK ("estado" IN ('pendiente', 'respondida', 'vencida')),
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_aclaraciones_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_aclaraciones_actuacion_id" FOREIGN KEY ("actuacion_id") REFERENCES "actuaciones"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_aclaraciones_documento_id" FOREIGN KEY ("documento_id") REFERENCES "documentos"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_aclaraciones_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE RESTRICT
);
-- Solicitudes de ampliacion (se registran aunque no sean procedentes, para auditoria)
DROP TABLE IF EXISTS "ampliaciones" CASCADE;
CREATE TABLE "ampliaciones" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "fecha_solicitud" DATE NOT NULL,
    "descripcion" TEXT NOT NULL,
    "estado" VARCHAR(40) NOT NULL DEFAULT 'recibida' CHECK ("estado" IN ('recibida', 'rechazada_no_regulada')),
    "respuesta_enviada" BOOLEAN NOT NULL DEFAULT FALSE,
    "fecha_respuesta" DATE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_ampliaciones_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE
);
-- Recursos de revision (correlativo independiente de la solicitud)
DROP TABLE IF EXISTS "recursos_revision" CASCADE;
CREATE TABLE "recursos_revision" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "documento_id" BIGINT,
    "correlativo" VARCHAR(255) NOT NULL,
    "fecha_presentacion" DATE NOT NULL,
    "fecha_vencimiento" DATE,
    "motivo" TEXT NOT NULL,
    "estado" VARCHAR(40) NOT NULL DEFAULT 'recibido' CHECK ("estado" IN ('recibido', 'en_tramite', 'resuelto')),
    "fecha_resolucion" DATE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_recursos_revision_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_recursos_revision_documento_id" FOREIGN KEY ("documento_id") REFERENCES "documentos"("id") ON DELETE SET NULL
);
-- Historial de asignaciones a dependencias/enlaces
DROP TABLE IF EXISTS "asignaciones" CASCADE;
CREATE TABLE "asignaciones" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT NOT NULL,
    "dependencia_id" BIGINT NOT NULL,
    "enlace_id" BIGINT,
    "user_id" BIGINT NOT NULL,
    "fecha_asignacion" DATE NOT NULL,
    "notas" TEXT,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_asignaciones_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_asignaciones_dependencia_id" FOREIGN KEY ("dependencia_id") REFERENCES "dependencias"("id") ON DELETE RESTRICT,
    CONSTRAINT "fk_asignaciones_enlace_id" FOREIGN KEY ("enlace_id") REFERENCES "enlaces"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_asignaciones_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE RESTRICT
);
-- Plantillas de correo con el tono/formato real de la UIP
DROP TABLE IF EXISTS "plantillas_correo" CASCADE;
CREATE TABLE "plantillas_correo" (
    "id" BIGSERIAL PRIMARY KEY,
    "clave" VARCHAR(255) NOT NULL,
    "evento" VARCHAR(255) NOT NULL,
    "asunto_template" VARCHAR(255) NOT NULL,
    "cuerpo_template" TEXT NOT NULL,
    "activa" BOOLEAN NOT NULL DEFAULT TRUE,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Bandeja de enviados (SMTP)
DROP TABLE IF EXISTS "correos_enviados" CASCADE;
CREATE TABLE "correos_enviados" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT,
    "plantilla_id" BIGINT,
    "enviado_por_user_id" BIGINT,
    "destinatario" VARCHAR(255) NOT NULL,
    "asunto" VARCHAR(255) NOT NULL,
    "cuerpo" TEXT NOT NULL,
    "estado_entrega" VARCHAR(40) NOT NULL DEFAULT 'pendiente' CHECK ("estado_entrega" IN ('pendiente', 'enviado', 'fallido')),
    "enviado_en" TIMESTAMP,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_correos_enviados_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_correos_enviados_plantilla_id" FOREIGN KEY ("plantilla_id") REFERENCES "plantillas_correo"("id") ON DELETE SET NULL,
    CONSTRAINT "fk_correos_enviados_enviado_por_user_id" FOREIGN KEY ("enviado_por_user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Bandeja de recibidos (IMAP)
DROP TABLE IF EXISTS "correos_recibidos" CASCADE;
CREATE TABLE "correos_recibidos" (
    "id" BIGSERIAL PRIMARY KEY,
    "solicitud_id" BIGINT,
    "remitente" VARCHAR(255) NOT NULL,
    "asunto" VARCHAR(255) NOT NULL,
    "cuerpo" TEXT NOT NULL,
    "recibido_en" TIMESTAMP NOT NULL,
    "estado" VARCHAR(40) NOT NULL DEFAULT 'pendiente' CHECK ("estado" IN ('asociado', 'pendiente')),
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    CONSTRAINT "fk_correos_recibidos_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE SET NULL
);
-- Adjuntos de correos enviados/recibidos
DROP TABLE IF EXISTS "correo_adjuntos" CASCADE;
CREATE TABLE "correo_adjuntos" (
    "id" BIGSERIAL PRIMARY KEY,
    "correo_enviado_id" BIGINT,
    "correo_recibido_id" BIGINT,
    "nombre_archivo" VARCHAR(255) NOT NULL,
    "ruta_archivo" VARCHAR(255) NOT NULL,
    "tamano_bytes" INTEGER,
    "created_at" TIMESTAMP NULL,
    CONSTRAINT "fk_correo_adjuntos_correo_enviado_id" FOREIGN KEY ("correo_enviado_id") REFERENCES "correos_enviados"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_correo_adjuntos_correo_recibido_id" FOREIGN KEY ("correo_recibido_id") REFERENCES "correos_recibidos"("id") ON DELETE CASCADE
);
-- Notificaciones internas para el personal UIP
DROP TABLE IF EXISTS "notificaciones" CASCADE;
CREATE TABLE "notificaciones" (
    "id" BIGSERIAL PRIMARY KEY,
    "user_id" BIGINT,
    "solicitud_id" BIGINT,
    "tipo" VARCHAR(40) NOT NULL CHECK ("tipo" IN ('vencimiento_proximo', 'vencida', 'aclaracion_pendiente', 'recurso_pendiente', 'otra')),
    "mensaje" VARCHAR(255) NOT NULL,
    "leida" BOOLEAN NOT NULL DEFAULT FALSE,
    "leida_en" TIMESTAMP,
    "created_at" TIMESTAMP NULL,
    CONSTRAINT "fk_notificaciones_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_notificaciones_solicitud_id" FOREIGN KEY ("solicitud_id") REFERENCES "solicitudes"("id") ON DELETE CASCADE
);
-- Dias no habiles, para el calculo de plazos (10 y 2 dias habiles)
DROP TABLE IF EXISTS "feriados" CASCADE;
CREATE TABLE "feriados" (
    "id" BIGSERIAL PRIMARY KEY,
    "fecha" DATE NOT NULL,
    "descripcion" VARCHAR(255),
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL
);
-- Parametros generales del sistema
DROP TABLE IF EXISTS "configuracion" CASCADE;
CREATE TABLE "configuracion" (
    "id" BIGSERIAL PRIMARY KEY,
    "clave" VARCHAR(255) NOT NULL,
    "valor" TEXT,
    "descripcion" TEXT,
    "updated_at" TIMESTAMP NULL
);
-- Auditoria tecnica (quien hizo que, cuando)
DROP TABLE IF EXISTS "logs" CASCADE;
CREATE TABLE "logs" (
    "id" BIGSERIAL PRIMARY KEY,
    "user_id" BIGINT,
    "accion" VARCHAR(255) NOT NULL,
    "entidad" VARCHAR(255),
    "entidad_id" INTEGER,
    "ip" VARCHAR(45),
    "detalle" JSONB,
    "created_at" TIMESTAMP NULL,
    CONSTRAINT "fk_logs_user_id" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE SET NULL
);
-- Restricciones UNIQUE adicionales
ALTER TABLE "roles" ADD CONSTRAINT "uq_roles_nombre" UNIQUE ("nombre");
ALTER TABLE "permissions" ADD CONSTRAINT "uq_permissions_clave" UNIQUE ("clave");
ALTER TABLE "dependencias" ADD CONSTRAINT "uq_dependencias_codigo" UNIQUE ("codigo");
ALTER TABLE "solicitud_estados" ADD CONSTRAINT "uq_solicitud_estados_clave" UNIQUE ("clave");
ALTER TABLE "solicitudes" ADD CONSTRAINT "uq_solicitudes_codigo_ns" UNIQUE ("codigo_ns");
ALTER TABLE "solicitudes" ADD CONSTRAINT "uq_solicitudes_contrasena" UNIQUE ("contrasena");
ALTER TABLE "solicitudes" ADD CONSTRAINT "uq_solicitudes_codigo_acceso" UNIQUE ("codigo_acceso");
ALTER TABLE "plantillas_documentos" ADD CONSTRAINT "uq_plantillas_documentos_clave" UNIQUE ("clave");
ALTER TABLE "recursos_revision" ADD CONSTRAINT "uq_recursos_revision_correlativo" UNIQUE ("correlativo");
ALTER TABLE "plantillas_correo" ADD CONSTRAINT "uq_plantillas_correo_clave" UNIQUE ("clave");
ALTER TABLE "feriados" ADD CONSTRAINT "uq_feriados_fecha" UNIQUE ("fecha");
ALTER TABLE "configuracion" ADD CONSTRAINT "uq_configuracion_clave" UNIQUE ("clave");
-- Indices sobre llaves foraneas mas consultadas
CREATE INDEX "idx_role_permission_role_id" ON "role_permission" ("role_id");
CREATE INDEX "idx_role_permission_permission_id" ON "role_permission" ("permission_id");
CREATE INDEX "idx_enlaces_dependencia_id" ON "enlaces" ("dependencia_id");
CREATE INDEX "idx_enlaces_user_id" ON "enlaces" ("user_id");
CREATE INDEX "idx_solicitudes_solicitante_id" ON "solicitudes" ("solicitante_id");
CREATE INDEX "idx_solicitudes_estado_id" ON "solicitudes" ("estado_id");
CREATE INDEX "idx_solicitudes_dependencia_id" ON "solicitudes" ("dependencia_id");
CREATE INDEX "idx_solicitudes_enlace_id" ON "solicitudes" ("enlace_id");
CREATE INDEX "idx_solicitudes_creado_por_user_id" ON "solicitudes" ("creado_por_user_id");
CREATE INDEX "idx_solicitud_historial_solicitud_id" ON "solicitud_historial" ("solicitud_id");
CREATE INDEX "idx_solicitud_historial_user_id" ON "solicitud_historial" ("user_id");
CREATE INDEX "idx_solicitud_historial_estado_anterior_id" ON "solicitud_historial" ("estado_anterior_id");
CREATE INDEX "idx_solicitud_historial_estado_nuevo_id" ON "solicitud_historial" ("estado_nuevo_id");
CREATE INDEX "idx_actuaciones_solicitud_id" ON "actuaciones" ("solicitud_id");
CREATE INDEX "idx_actuaciones_user_id" ON "actuaciones" ("user_id");
CREATE INDEX "idx_documentos_solicitud_id" ON "documentos" ("solicitud_id");
CREATE INDEX "idx_documentos_actuacion_id" ON "documentos" ("actuacion_id");
CREATE INDEX "idx_documentos_plantilla_id" ON "documentos" ("plantilla_id");
CREATE INDEX "idx_documentos_subido_por_user_id" ON "documentos" ("subido_por_user_id");
CREATE INDEX "idx_prorrogas_solicitud_id" ON "prorrogas" ("solicitud_id");
CREATE INDEX "idx_prorrogas_actuacion_id" ON "prorrogas" ("actuacion_id");
CREATE INDEX "idx_prorrogas_documento_id" ON "prorrogas" ("documento_id");
CREATE INDEX "idx_prorrogas_user_id" ON "prorrogas" ("user_id");
CREATE INDEX "idx_aclaraciones_solicitud_id" ON "aclaraciones" ("solicitud_id");
CREATE INDEX "idx_aclaraciones_actuacion_id" ON "aclaraciones" ("actuacion_id");
CREATE INDEX "idx_aclaraciones_documento_id" ON "aclaraciones" ("documento_id");
CREATE INDEX "idx_aclaraciones_user_id" ON "aclaraciones" ("user_id");
CREATE INDEX "idx_ampliaciones_solicitud_id" ON "ampliaciones" ("solicitud_id");
CREATE INDEX "idx_recursos_revision_solicitud_id" ON "recursos_revision" ("solicitud_id");
CREATE INDEX "idx_recursos_revision_documento_id" ON "recursos_revision" ("documento_id");
CREATE INDEX "idx_asignaciones_solicitud_id" ON "asignaciones" ("solicitud_id");
CREATE INDEX "idx_asignaciones_dependencia_id" ON "asignaciones" ("dependencia_id");
CREATE INDEX "idx_asignaciones_enlace_id" ON "asignaciones" ("enlace_id");
CREATE INDEX "idx_asignaciones_user_id" ON "asignaciones" ("user_id");
CREATE INDEX "idx_correos_enviados_solicitud_id" ON "correos_enviados" ("solicitud_id");
CREATE INDEX "idx_correos_enviados_plantilla_id" ON "correos_enviados" ("plantilla_id");
CREATE INDEX "idx_correos_enviados_enviado_por_user_id" ON "correos_enviados" ("enviado_por_user_id");
CREATE INDEX "idx_correos_recibidos_solicitud_id" ON "correos_recibidos" ("solicitud_id");
CREATE INDEX "idx_correo_adjuntos_correo_enviado_id" ON "correo_adjuntos" ("correo_enviado_id");
CREATE INDEX "idx_correo_adjuntos_correo_recibido_id" ON "correo_adjuntos" ("correo_recibido_id");
CREATE INDEX "idx_notificaciones_user_id" ON "notificaciones" ("user_id");
CREATE INDEX "idx_notificaciones_solicitud_id" ON "notificaciones" ("solicitud_id");
CREATE INDEX "idx_logs_user_id" ON "logs" ("user_id");
-- Columnas de negocio agregadas a la tabla users (Fase 3)

ALTER TABLE "users" ADD COLUMN "role_id" BIGINT;
ALTER TABLE "users" ADD CONSTRAINT "fk_users_role_id" FOREIGN KEY ("role_id") REFERENCES "roles"("id") ON DELETE SET NULL;
ALTER TABLE "users" ADD COLUMN "dependencia_id" BIGINT;
ALTER TABLE "users" ADD CONSTRAINT "fk_users_dependencia_id" FOREIGN KEY ("dependencia_id") REFERENCES "dependencias"("id") ON DELETE SET NULL;
ALTER TABLE "users" ADD COLUMN "activo" BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE "users" ADD COLUMN "last_login_at" TIMESTAMP;
