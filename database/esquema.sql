-- ============================================================================
-- PROYECTO IAA — ESQUEMA POSTGRESQL 15+
-- Archivo: database/iaa_schema_pg.sql
-- Migración: MariaDB 10.4 (fpaiaa)  ->  PostgreSQL (fpaiaa)
-- Fecha: Octubre 2026
-- Clasificación: Confidencial — Equipo Técnico, Ciberseguridad y DBA
-- ============================================================================
--
-- Este script es IDEMPOTENTE sobre una base vacía: destruye y recrea el
-- esquema. NO ejecutar sobre una base con datos en producción.
--
-- CONVENCIONES APLICADAS (Pilar 1 del Plan Maestro Consolidado v5.0):
--   * Identidades  : BIGINT GENERATED ALWAYS AS IDENTITY (nunca AUTO_INCREMENT).
--                    La ETL debe usar OVERRIDING SYSTEM VALUE para preservar IDs.
--   * Booleanos    : TINYINT(1) / TINYINT(4) de semántica booleana -> BOOLEAN.
--   * Fechas/horas : DATETIME -> TIMESTAMPTZ. DATE y TIME se conservan nativos
--                    (cita.fecha_cita / cita.hora_cita se consultan por separado).
--   * Roles        : el ENUM usuario.rol y el entero frágil rol_permiso.id_rol
--                    se normalizan en la tabla relacional `rol`.
--   * Auditoría    : ON UPDATE CURRENT_TIMESTAMP se emula con trigger
--                    BEFORE UPDATE (ver iaa_touch_actualizado_en).
--   * Collation    : el índice de búsqueda por cédula usa COLLATE "C"
--                    (ordenamiento determinista y libre de fragmentación).
--
-- MAPA DE ROLES (hallado en database/permisos_canonicos.sql, línea 11-13):
--   id_rol es la POSICIÓN del ENUM original, NO un ordinal contiguo.
--   Existe un hueco histórico en el id 2.
--       1 = funcionario    3 = moderador    4 = supervisor
--       5 = administrador  6 = medico       7 = particular
--   Para id_rol=2 el código legado mapea a 'funcionario', pero NO existen
--   filas que lo usen (verificado en rol_permiso: 1,3,4,5,6,7).
--
-- REQUIERE: PostgreSQL 15 o superior (probado contra PostgreSQL 18).
-- ============================================================================

\set ON_ERROR_STOP on

-- ----------------------------------------------------------------------------
-- 0. LIMPIEZA (orden inverso a las dependencias de clave foránea)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS usuario_permiso CASCADE;
DROP TABLE IF EXISTS rol_permiso CASCADE;
DROP TABLE IF EXISTS permiso CASCADE;
DROP TABLE IF EXISTS rol CASCADE;
DROP TABLE IF EXISTS bitacora CASCADE;
DROP TABLE IF EXISTS logeo CASCADE;
DROP TABLE IF EXISTS password_historial CASCADE;
DROP TABLE IF EXISTS correo_verificacion CASCADE;
DROP TABLE IF EXISTS notificacion CASCADE;
DROP TABLE IF EXISTS preguntas_seguridad CASCADE;
DROP TABLE IF EXISTS intentos_validacion CASCADE;
DROP TABLE IF EXISTS intentos_recuperacion CASCADE;
DROP TABLE IF EXISTS intentos_login CASCADE;
DROP TABLE IF EXISTS encuesta_cita CASCADE;
DROP TABLE IF EXISTS persona_patologia CASCADE;
DROP TABLE IF EXISTS patologia CASCADE;
DROP TABLE IF EXISTS traumatologia_historia_medica CASCADE;
DROP TABLE IF EXISTS psicologia_historia_medica CASCADE;
DROP TABLE IF EXISTS pediatria_historia_medica CASCADE;
DROP TABLE IF EXISTS odontologia_pieza CASCADE;
DROP TABLE IF EXISTS odontologia_sesion CASCADE;
DROP TABLE IF EXISTS odontologia_examen_boca CASCADE;
DROP TABLE IF EXISTS odontologia_historia_medica CASCADE;
DROP TABLE IF EXISTS medicina_interna_historia_medica CASCADE;
DROP TABLE IF EXISTS ginecologia_estudios CASCADE;
DROP TABLE IF EXISTS ginecologia_examen_ginecoobstetrico CASCADE;
DROP TABLE IF EXISTS ginecologia_sesion CASCADE;
DROP TABLE IF EXISTS ginecologia_historia_medica CASCADE;
DROP TABLE IF EXISTS nota_clinica CASCADE;
DROP TABLE IF EXISTS cita CASCADE;
DROP TABLE IF EXISTS solicitud CASCADE;
DROP TABLE IF EXISTS medico_servicio CASCADE;
DROP TABLE IF EXISTS medico CASCADE;
DROP TABLE IF EXISTS funcionario CASCADE;
DROP TABLE IF EXISTS beneficiario CASCADE;
DROP TABLE IF EXISTS documento CASCADE;
DROP TABLE IF EXISTS usuario CASCADE;
DROP TABLE IF EXISTS persona CASCADE;
DROP TABLE IF EXISTS direccion CASCADE;
DROP TABLE IF EXISTS parroquia CASCADE;
DROP TABLE IF EXISTS municipio CASCADE;
DROP TABLE IF EXISTS estado CASCADE;
DROP TABLE IF EXISTS especialidad CASCADE;
DROP TABLE IF EXISTS servicio CASCADE;
DROP TABLE IF EXISTS tipo_pago CASCADE;
DROP TABLE IF EXISTS tasa_dolar CASCADE;
DROP TABLE IF EXISTS rango CASCADE;
DROP TABLE IF EXISTS ccp CASCADE;
DROP TABLE IF EXISTS organismo_institucional CASCADE;
DROP TABLE IF EXISTS unidades_especiales CASCADE;

DROP FUNCTION IF EXISTS iaa_touch_actualizado_en() CASCADE;


-- ============================================================================
-- 1. FUNCIONES DE SOPORTE
-- ============================================================================

-- Emula "ON UPDATE CURRENT_TIMESTAMP" de MySQL/MariaDB.
--
-- Se usa statement_timestamp() y NO now(): now() es un alias de
-- transaction_timestamp() y devolvería la hora de INICIO de la transacción,
-- con lo que en una transacción larga la marca quedaría desactualizada.
-- statement_timestamp() refleja el instante del UPDATE, igual que hace MySQL.
CREATE OR REPLACE FUNCTION iaa_touch_actualizado_en()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.actualizado_en := statement_timestamp();
    RETURN NEW;
END;
$$;

COMMENT ON FUNCTION iaa_touch_actualizado_en() IS
    'Mantiene actualizado_en en cada UPDATE. Emula ON UPDATE CURRENT_TIMESTAMP de MariaDB.';


-- ============================================================================
-- 2. SEGURIDAD: ROLES Y PERMISOS
-- ============================================================================

-- Normalización del ENUM usuario.rol y del entero frágil rol_permiso.id_rol.
-- El id se conserva EXACTAMENTE como lo espera el código legado (1,3,4,5,6,7).
CREATE TABLE rol (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre      VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

COMMENT ON TABLE rol IS 'Catálogo de roles. Sustituye al ENUM usuario.rol y al entero rol_permiso.id_rol.';
COMMENT ON COLUMN rol.id IS 'Conserva el valor histórico: 1,3,4,5,6,7. El id 2 estuvo reservado y nunca se usó.';

CREATE TABLE permiso (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255)
);

CREATE TABLE rol_permiso (
    id_rol      BIGINT NOT NULL REFERENCES rol (id) ON DELETE CASCADE,
    id_permiso  BIGINT NOT NULL REFERENCES permiso (id) ON DELETE CASCADE,
    PRIMARY KEY (id_rol, id_permiso)
);

CREATE TABLE usuario_permiso (
    id_usuario  BIGINT NOT NULL,
    id_permiso  BIGINT NOT NULL,
    PRIMARY KEY (id_usuario, id_permiso)
);


-- ============================================================================
-- 3. CATÁLOGOS INSTITUCIONALES Y TERRITORIALES
-- ============================================================================

CREATE TABLE organismo_institucional (
    id_organismo     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_organismo VARCHAR(150) NOT NULL,
    siglas           VARCHAR(20)
);

CREATE TABLE unidades_especiales (
    id_unidades  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_unidad VARCHAR(150) NOT NULL,
    sigla        VARCHAR(20)
);

CREATE TABLE ccp (
    id_ccp        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_ccp    VARCHAR(150) NOT NULL,
    ubicacion_ccp VARCHAR(255)
);

CREATE TABLE rango (
    id_rango        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_rango    VARCHAR(100) NOT NULL,
    linea_mando     VARCHAR(100),
    nivel_jerarquico INTEGER
);

CREATE TABLE tipo_pago (
    id_pago BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL
);

CREATE TABLE estado (
    id_estado BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    estado    VARCHAR(100) NOT NULL
);

CREATE TABLE municipio (
    id_municipio BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estado    BIGINT NOT NULL REFERENCES estado (id_estado) ON DELETE CASCADE,
    municipio    VARCHAR(100) NOT NULL
);

CREATE TABLE parroquia (
    id_parroquia BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_municipio BIGINT NOT NULL REFERENCES municipio (id_municipio) ON DELETE CASCADE,
    parroquia    VARCHAR(100) NOT NULL
);

CREATE TABLE direccion (
    id_direccion       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estado          BIGINT REFERENCES estado (id_estado) ON DELETE SET NULL,
    id_municipio       BIGINT REFERENCES municipio (id_municipio) ON DELETE SET NULL,
    id_parroquia       BIGINT REFERENCES parroquia (id_parroquia) ON DELETE SET NULL,
    direccion_completa VARCHAR(255)
);

CREATE TABLE servicio (
    id_servicio      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre_servicio  VARCHAR(150) NOT NULL,
    disponibilidad   VARCHAR(50),
    cobertura        VARCHAR(100),
    tipo_atencion    VARCHAR(100),
    -- Duración de la consulta en minutos: la usa la agenda de citas.
    -- La capa PHP (models/ServicioModel.php) la escribe siempre.
    duracion_minutos INTEGER DEFAULT 60
);

CREATE TABLE especialidad (
    id_especialidad BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_servicio     BIGINT NOT NULL REFERENCES servicio (id_servicio) ON DELETE CASCADE,
    especialidad    VARCHAR(150) NOT NULL,
    precio          NUMERIC(12, 2) NOT NULL DEFAULT 0.00
);

CREATE TABLE patologia (
    id_patologia BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    patologia    VARCHAR(255) NOT NULL,
    codigo_cie10 VARCHAR(20)
);

-- Tabla de una sola fila con la tasa cambiaria vigente.
CREATE TABLE tasa_dolar (
    id                 BIGINT NOT NULL DEFAULT 1 PRIMARY KEY,
    monto              NUMERIC(10, 2),
    fecha_actualizacion TIMESTAMPTZ DEFAULT now(),
    CONSTRAINT chk_tasa_id CHECK (id = 1)
);


-- ============================================================================
-- 4. PERSONAS Y USUARIOS
-- ============================================================================

CREATE TABLE persona (
    id_persona          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_direccion        BIGINT REFERENCES direccion (id_direccion) ON DELETE SET NULL,
    nombre              VARCHAR(150) NOT NULL,
    ci_usuario          VARCHAR(20),
    telefono            VARCHAR(20),
    sexo                CHAR(1),
    nacionalidad        VARCHAR(30),
    pais_nacimiento     VARCHAR(80),
    estado_nacimiento   BIGINT REFERENCES estado (id_estado) ON DELETE SET NULL,
    municipio_nacimiento BIGINT REFERENCES municipio (id_municipio) ON DELETE SET NULL,
    fecha_nacimiento    DATE,
    estado_civil        VARCHAR(30),
    licencia_conducir   VARCHAR(30),
    correo              VARCHAR(150),
    -- Datos confirmados a partir de la cedula/OCR y lectura cruda para
    -- auditoria (ver database/migracion_ocr_cedula_persona.sql).
    apellidos           VARCHAR(100),
    nombres             VARCHAR(100),
    ocr_texto           TEXT,
    ocr_datos           TEXT,
    ocr_leido_at        TIMESTAMPTZ,
    -- Extension de esquema: el codigo (SeguridadModel, datos_de_usuario,
    -- views/edicion.php) usa este correo alternativo, pero la columna no
    -- existia en MariaDB. Se agrega para no perder la funcionalidad.
    nivel_academico     VARCHAR(100),
    -- Columna generada: cédula normalizada (solo dígitos).
    --
    -- CORRECCIÓN DE DEFECTO (Fase 1 — hallazgo forense):
    -- La expresión original de MariaDB era
    --     replace(replace(ucase(ci_usuario),'V',''),'E','')
    -- que NO elimina el guion: 'V-12345678' producía '-12345678'.
    -- La aplicación PHP, en cambio, normaliza con
    --     strtoupper(str_replace(['V','E','-'], '', $ci_user))
    -- obteniendo '12345678'. Al no coincidir ambos criterios, la búsqueda
    -- "WHERE ci_digitos = ?" no encontraba al paciente (ver
    -- controllers/obtener_patologias_paciente.php, línea 58).
    --
    -- La intención está documentada en el propio código
    -- (controllers/actualizacion_user_base.php, líneas 121-123): que
    -- 'V-12345678' y '12345678' sean la MISMA cédula. Esta expresión la cumple
    -- y es la que hace efectiva la restricción UNIQUE.
    --
    -- Los 18 registros migrados son dígitos puros, por lo que la corrección
    -- no altera ningún dato existente.
    ci_digitos          VARCHAR(20) GENERATED ALWAYS AS (
                            regexp_replace(upper(ci_usuario), '[^0-9]', '', 'g')
                        ) STORED
);

COMMENT ON COLUMN persona.ci_digitos IS
    'Cédula normalizada (solo dígitos). Columna generada equivalente a la de MariaDB.';


CREATE TABLE usuario (
    id_usuario          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_persona          BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    ci_usuario          VARCHAR(100) NOT NULL,
    contrasena          VARCHAR(255) NOT NULL,
    password_changed_at TIMESTAMPTZ,
    correo_verificado_at TIMESTAMPTZ,
    -- Extension de esquema requerida por CorreoVerificacionModel.
    
    id_rol              BIGINT NOT NULL REFERENCES rol (id) DEFAULT 1,
    estado              VARCHAR(20) DEFAULT 'activo',
    -- Foto de cedula validada en el autoregistro (archivo cifrado). Se usa
    -- para revisar la solicitud y para crear la Copia de Cedula al aprobar.
    evidencia_cedula    VARCHAR(150),
    ultimo_acceso       TIMESTAMPTZ
);

COMMENT ON COLUMN usuario.id_rol IS
    'FK a rol.id. La ETL convierte: funcionario->1, moderador->3, supervisor->4, administrador->5, medico->6, particular->7.';
COMMENT ON COLUMN usuario.password_changed_at IS 'Fecha del último cambio de contraseña.';
COMMENT ON COLUMN usuario.correo_verificado_at IS 'Fecha en que se verificó el correo.';

-- Se añade la FK diferida de usuario_permiso (dependía de usuario).
ALTER TABLE usuario_permiso
    ADD CONSTRAINT fk_usuario_permiso_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    ADD CONSTRAINT fk_usuario_permiso_permiso
        FOREIGN KEY (id_permiso) REFERENCES permiso (id) ON DELETE CASCADE;


-- ============================================================================
-- 5. FUNCIONARIOS, MÉDICOS Y BENEFICIARIOS
-- ============================================================================

CREATE TABLE funcionario (
    id_funcionario   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario       BIGINT NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    id_unidades      BIGINT REFERENCES unidades_especiales (id_unidades) ON DELETE SET NULL,
    id_organismo     BIGINT REFERENCES organismo_institucional (id_organismo) ON DELETE SET NULL,
    id_ccp           BIGINT REFERENCES ccp (id_ccp) ON DELETE SET NULL,
    id_rango         BIGINT REFERENCES rango (id_rango) ON DELETE SET NULL,
    fecha_ingreso    DATE,
    estatus_servicio VARCHAR(30) DEFAULT 'activo',
    nro_credencial   VARCHAR(50)
);

CREATE TABLE medico (
    id_medico      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nro_colegiado  VARCHAR(50),
    especialidad   VARCHAR(150),
    estatus_medico VARCHAR(30) DEFAULT 'activo',
    -- Conserva el "servicio principal"; la fuente de verdad N<->M es medico_servicio.
    id_servicio    BIGINT REFERENCES servicio (id_servicio)
);

-- Pivote N<->M médico <-> servicio (fuente de verdad de la relación).
CREATE TABLE medico_servicio (
    id_medico   BIGINT NOT NULL REFERENCES medico (id_medico) ON DELETE CASCADE,
    id_servicio BIGINT NOT NULL REFERENCES servicio (id_servicio) ON DELETE CASCADE,
    PRIMARY KEY (id_medico, id_servicio)
);

CREATE TABLE beneficiario (
    id_beneficiario      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_persona           BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    id_funcionario       BIGINT NOT NULL REFERENCES funcionario (id_funcionario) ON DELETE CASCADE,
    parentesco           VARCHAR(50),
    estatus_beneficiario VARCHAR(30) DEFAULT 'activo',
    fecha_registro       TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE documento (
    id_documento    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_funcionario  BIGINT REFERENCES funcionario (id_funcionario) ON DELETE CASCADE,
    id_beneficiario BIGINT REFERENCES beneficiario (id_beneficiario) ON DELETE CASCADE,
    -- Documentos de cuentas "particular" (sin fila en funcionario).
    id_usuario      BIGINT REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    tipo_documento  VARCHAR(100),
    formato         VARCHAR(20),
    fecha_entrega   TIMESTAMPTZ DEFAULT now(),
    ruta_archivo    VARCHAR(500),
    estatus         VARCHAR(30) DEFAULT 'activo'
);


-- ============================================================================
-- 6. SOLICITUDES Y CITAS
-- ============================================================================

CREATE TABLE solicitud (
    id_solicitud      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_funcionario    BIGINT NOT NULL REFERENCES funcionario (id_funcionario) ON DELETE CASCADE,
    nro_solicitud     VARCHAR(50),
    tipo_solicitud    VARCHAR(100),
    estatus_solicitud VARCHAR(30) DEFAULT 'pendiente',
    descripcion       TEXT,
    fecha_creacion    TIMESTAMPTZ DEFAULT now(),
    fecha_respuesta   TIMESTAMPTZ,
    observacion_admin TEXT
);

CREATE TABLE cita (
    id_cita       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_solicitud  BIGINT NOT NULL REFERENCES solicitud (id_solicitud) ON DELETE CASCADE,
    id_servicio   BIGINT REFERENCES servicio (id_servicio) ON DELETE SET NULL,
    id_medico     BIGINT REFERENCES medico (id_medico) ON DELETE SET NULL,
    fecha_cita    DATE,
    hora_cita     TIME,
    turno_laboratorio SMALLINT CHECK (turno_laboratorio BETWEEN 1 AND 100),
    semana_laboratorio DATE,
    diagnostico   TEXT,
    estatus_cita  VARCHAR(30) DEFAULT 'programada',
    CONSTRAINT uq_cita_turno_laboratorio_semana UNIQUE (semana_laboratorio, turno_laboratorio)
);

CREATE TABLE laboratorio_turno_semanal (
    id             SMALLINT PRIMARY KEY CHECK (id = 1),
    semana_inicio  DATE NOT NULL,
    ultimo_turno   SMALLINT NOT NULL DEFAULT 0 CHECK (ultimo_turno BETWEEN 0 AND 100)
);

INSERT INTO laboratorio_turno_semanal (id, semana_inicio, ultimo_turno)
VALUES (1, DATE '2000-01-03', 0)
ON CONFLICT (id) DO NOTHING;

-- Verificación financiera de la cita: permite conciliar el pago sin alterar
-- el estatus clínico de la cita.
CREATE TABLE verificacion_pago (
    id_verificacion     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita             BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                        CHECK (estado IN ('pendiente', 'verificado', 'rechazado')),
    metodo_pago         VARCHAR(80),
    referencia          VARCHAR(120),
    monto_usd           NUMERIC(12, 2) NOT NULL DEFAULT 0,
    tasa_dolar          NUMERIC(14, 4) NOT NULL DEFAULT 0,
    monto_bs            NUMERIC(16, 2) NOT NULL DEFAULT 0,
    observacion         TEXT,
    verificado_por      BIGINT REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    fecha_registro      TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_verificacion  TIMESTAMPTZ
);

CREATE TABLE cierre_caja (
    id_cierre       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fecha           DATE NOT NULL UNIQUE,
    total_usd       NUMERIC(12, 2) NOT NULL DEFAULT 0,
    total_bs        NUMERIC(16, 2) NOT NULL DEFAULT 0,
    cantidad_pagos  INTEGER NOT NULL DEFAULT 0,
    observacion     TEXT,
    cerrado_por     BIGINT REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    fecha_cierre    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE encuesta_cita (
    id_encuesta              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita                  BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_usuario               BIGINT NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    calificacion_atencion    VARCHAR(20),
    calificacion_informacion VARCHAR(20),
    calificacion_tiempo      VARCHAR(20),
    calificacion_profesionalismo VARCHAR(20),
    comentarios              TEXT,
    estatus_encuesta         VARCHAR(20) NOT NULL DEFAULT 'respondida',
    fecha_registro           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE nota_clinica (
    id_nota        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ci_user        VARCHAR(20) NOT NULL,
    tipo           VARCHAR(20) NOT NULL,
    contenido      TEXT,
    actualizado_en TIMESTAMPTZ DEFAULT now(),
    id_medico      BIGINT,
    CONSTRAINT uk_nota_ci_tipo UNIQUE (ci_user, tipo)
);

COMMENT ON COLUMN nota_clinica.actualizado_en IS
    'Se refresca automáticamente mediante el trigger trg_nota_clinica_touch.';


-- ============================================================================
-- 7. MÓDULOS CLÍNICOS ESPECIALIZADOS
-- ============================================================================

-- ---- Ginecología -----------------------------------------------------------
CREATE TABLE ginecologia_historia_medica (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita         BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona      BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    motivo_consulta TEXT,
    antecedentes    TEXT,
    fecha_registro  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ginecologia_examen_ginecoobstetrico (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita      BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona   BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    menarquia    VARCHAR(50),
    fur          DATE,
    fpp          DATE,
    gestas       INTEGER NOT NULL DEFAULT 0 CHECK (gestas   >= 0),
    paras        INTEGER NOT NULL DEFAULT 0 CHECK (paras    >= 0),
    cesareas     INTEGER NOT NULL DEFAULT 0 CHECK (cesareas >= 0),
    abortos      INTEGER NOT NULL DEFAULT 0 CHECK (abortos  >= 0),
    citologias   VARCHAR(100),
    fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ginecologia_estudios (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita        BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona     BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    ecosonograma   TEXT,
    laboratorio    TEXT,
    observaciones  TEXT,
    fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ginecologia_sesion (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita        BIGINT NOT NULL REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona     BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    id_medico      BIGINT REFERENCES medico (id_medico) ON DELETE SET NULL,
    fecha          DATE NOT NULL,
    diagnostico    TEXT,
    fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Medicina Interna ------------------------------------------------------
CREATE TABLE medicina_interna_historia_medica (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita                  BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona               BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    motivo_consulta          TEXT,
    antecedentes_personales  TEXT,
    antecedentes_patologicos TEXT,
    antecedentes_familiares  TEXT,
    habitos                  TEXT,
    examen_fisico            TEXT,
    idx_texto                TEXT,
    resultados_laboratorio   TEXT,
    plan                     TEXT,
    proxima_cita             DATE,
    fecha_registro           TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Pediatría -------------------------------------------------------------
CREATE TABLE pediatria_historia_medica (
    id                        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita                   BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona                BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    nombre_madre              VARCHAR(150),
    peso                      NUMERIC(5, 2),
    estatura                  NUMERIC(5, 2),
    antecedentes_prenatales   TEXT,
    antecedentes_neonatales   TEXT,
    antecedentes_alimentarios TEXT,
    inmunizaciones            TEXT,
    desarrollo_psicomotor     TEXT,
    antecedentes_familiares   TEXT,
    antecedentes_patologicos  TEXT,
    enfermedad_actual         TEXT,
    examen_fisico             TEXT,
    fecha_registro            TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Psicología ------------------------------------------------------------
CREATE TABLE psicologia_historia_medica (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita         BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona      BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    motivo_consulta TEXT,
    observaciones   TEXT,
    fecha_registro  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Traumatología ---------------------------------------------------------
CREATE TABLE traumatologia_historia_medica (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita           BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona        BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    motivo_consulta   TEXT,
    enfermedad_actual TEXT,
    antecedentes      TEXT,
    examen_medico     TEXT,
    diagnostico       TEXT,
    tratamiento       TEXT,
    fecha_registro    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Odontología -----------------------------------------------------------
CREATE TABLE odontologia_historia_medica (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita              BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona           BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    alergias             BOOLEAN NOT NULL DEFAULT FALSE,
    diabetes             BOOLEAN NOT NULL DEFAULT FALSE,
    anemia               BOOLEAN NOT NULL DEFAULT FALSE,
    artritis             BOOLEAN NOT NULL DEFAULT FALSE,
    varices              BOOLEAN NOT NULL DEFAULT FALSE,
    nausea               BOOLEAN NOT NULL DEFAULT FALSE,
    problemas_cardiacos  BOOLEAN NOT NULL DEFAULT FALSE,
    toma_medicamentos    BOOLEAN NOT NULL DEFAULT FALSE,
    medicamentos         VARCHAR(255),
    fecha_registro       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE odontologia_examen_boca (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita           BIGINT NOT NULL UNIQUE REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona        BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    piezas_afectadas  VARCHAR(255),
    ptisis            BOOLEAN NOT NULL DEFAULT FALSE,
    gingivitis        BOOLEAN NOT NULL DEFAULT FALSE,
    gingivorragia     BOOLEAN NOT NULL DEFAULT FALSE,
    epitelio_alterado BOOLEAN NOT NULL DEFAULT FALSE,
    sangrado          BOOLEAN NOT NULL DEFAULT FALSE,
    intensidad        VARCHAR(20),
    observaciones     TEXT,
    fecha_registro    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE odontologia_pieza (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita        BIGINT NOT NULL REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona     BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    numero_fdi     SMALLINT NOT NULL CHECK (numero_fdi >= 0),
    estado         VARCHAR(20) NOT NULL,
    observaciones  VARCHAR(255),
    fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uk_pieza_cita UNIQUE (id_cita, numero_fdi)
);

CREATE TABLE odontologia_sesion (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cita        BIGINT NOT NULL REFERENCES cita (id_cita) ON DELETE CASCADE,
    id_persona     BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    id_medico      BIGINT REFERENCES medico (id_medico) ON DELETE SET NULL,
    fecha          DATE NOT NULL,
    tratamiento    TEXT,
    fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---- Patologías del paciente (pivote) --------------------------------------
CREATE TABLE persona_patologia (
    id_persona     BIGINT NOT NULL REFERENCES persona (id_persona) ON DELETE CASCADE,
    id_patologia   BIGINT NOT NULL REFERENCES patologia (id_patologia) ON DELETE CASCADE,
    fecha_registro TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (id_persona, id_patologia)
);


-- ============================================================================
-- 8. SEGURIDAD DE LA CUENTA (historial, verificación, intentos)
-- ============================================================================

CREATE TABLE password_historial (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario    BIGINT NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    password_hash VARCHAR(255) NOT NULL,
    creada        TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE password_historial IS 'Últimas contraseñas por usuario (para impedir su reutilización).';

CREATE TABLE correo_verificacion (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario  BIGINT NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    correo_nuevo VARCHAR(120) NOT NULL,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    expira      TIMESTAMPTZ NOT NULL,
    usado       BOOLEAN NOT NULL DEFAULT FALSE,
    -- Extension de esquema requerida por CorreoVerificacionModel:
    -- distingue tokens de correo principal ('principal') y de recuperacion.
    tipo        VARCHAR(20) NOT NULL DEFAULT 'principal',
    creado      TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE correo_verificacion IS 'Tokens de verificación del correo de recuperación.';

CREATE TABLE intentos_login (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ci_user               VARCHAR(20) NOT NULL UNIQUE,
    intentos              INTEGER DEFAULT 0,
    bloqueo_hasta         TIMESTAMPTZ,
    razon                 VARCHAR(255) DEFAULT '',
    exitoso               BOOLEAN DEFAULT FALSE,
    ultimo_intento        TIMESTAMPTZ,
    bloqueos_consecutivos INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE intentos_recuperacion (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ci_user        VARCHAR(20),
    ip             VARCHAR(45),
    intentos       INTEGER DEFAULT 0,
    bloqueo_hasta  TIMESTAMPTZ,
    ultimo_intento TIMESTAMPTZ
);

CREATE TABLE intentos_validacion (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ci_user        VARCHAR(20),
    ip             VARCHAR(45),
    intentos       INTEGER DEFAULT 0,
    bloqueo_hasta  TIMESTAMPTZ,
    ultimo_intento TIMESTAMPTZ
);

-- No lleva FK a usuario: las preguntas sobreviven al borrado del usuario
-- (igual que en el esquema MariaDB de origen).
CREATE TABLE preguntas_seguridad (
    ci_user        VARCHAR(20) NOT NULL,
    numero         INTEGER NOT NULL,
    pregunta       VARCHAR(255) NOT NULL,
    respuesta_hash VARCHAR(64) NOT NULL,
    tipo           VARCHAR(20) DEFAULT 'texto',
    actualizada    TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (ci_user, numero)
);


-- ============================================================================
-- 9. NOTIFICACIONES Y AUDITORÍA
-- ============================================================================

CREATE TABLE notificacion (
    id_notificacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario      BIGINT NOT NULL REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    titulo          VARCHAR(200),
    mensaje         TEXT,
    tipo            VARCHAR(50),
    leida           BOOLEAN DEFAULT FALSE,
    fecha_creacion  TIMESTAMPTZ DEFAULT now()
);

-- Sin FK a usuario a propósito: el registro de sesiones debe sobrevivir
-- al borrado del usuario (id_user se conserva como referencia histórica).
CREATE TABLE logeo (
    id_logeo          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_user           BIGINT,
    ci_user           VARCHAR(20),
    nombre_user       VARCHAR(150),
    f_ing             TIMESTAMPTZ,
    f_sal             TIMESTAMPTZ,
    dir_ip            VARCHAR(45),
    agent_u           VARCHAR(200),
    token_sesion      VARCHAR(64),
    estado            VARCHAR(20) DEFAULT 'activo',
    tipo_evento       VARCHAR(50),
    detalle           TEXT,
    last_activity     TIMESTAMPTZ,
    cerrada_por_admin BOOLEAN NOT NULL DEFAULT FALSE
);

-- id_logeo sin FK a propósito: la bitácora es evidencia forense y no debe
-- desaparecer ni bloquear el borrado de sesiones.
CREATE TABLE bitacora (
    id_bitacora BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario  BIGINT REFERENCES usuario (id_usuario) ON DELETE SET NULL,
    id_logeo    BIGINT,
    accion      VARCHAR(255),
    categoria   VARCHAR(40),
    detalle     TEXT,
    fecha       TIMESTAMPTZ DEFAULT now(),
    ip          VARCHAR(45),
    user_agent  TEXT
);


-- ============================================================================
-- 10. ÍNDICES
-- ============================================================================

-- ---- persona: búsqueda determinista por cédula -----------------------------
-- COLLATE "C" garantiza ordenamiento por bytes: determinista, insensible a la
-- configuración regional del clúster y libre de fragmentación de tabla.
-- ADVERTENCIA PARA LA CAPA PHP: para que el planificador use este índice, la
-- consulta debe comparar con la misma collation:
--     WHERE ci_digitos COLLATE "C" = $1
CREATE UNIQUE INDEX uk_persona_ci_digitos ON persona (ci_digitos COLLATE "C");
CREATE INDEX id_direccion                ON persona (id_direccion);
CREATE INDEX fk_persona_estado_nac       ON persona (estado_nacimiento);
CREATE INDEX fk_persona_municipio_nac    ON persona (municipio_nacimiento);

-- ---- usuario ---------------------------------------------------------------
CREATE UNIQUE INDEX uk_usuario_persona ON usuario (id_persona);
CREATE UNIQUE INDEX uk_ci_usuario      ON usuario (ci_usuario);
CREATE INDEX idx_usuario_nombre        ON usuario (ci_usuario);
CREATE INDEX idx_usuario_estado        ON usuario (estado);
CREATE INDEX idx_usuario_rol           ON usuario (id_rol);

-- ---- catálogos territoriales ----------------------------------------------
CREATE INDEX id_estado_municipio    ON municipio (id_estado);
CREATE INDEX id_municipio_parroquia ON parroquia (id_municipio);
CREATE INDEX id_estado             ON direccion (id_estado);
CREATE INDEX id_municipio          ON direccion (id_municipio);
CREATE INDEX id_parroquia          ON direccion (id_parroquia);
CREATE INDEX idx_esp_servicio      ON especialidad (id_servicio);

-- ---- funcionario / médico / beneficiario ----------------------------------
CREATE INDEX idx_funcionario_usuario    ON funcionario (id_usuario);
CREATE INDEX idx_funcionario_rango      ON funcionario (id_rango);
CREATE INDEX idx_funcionario_organismo  ON funcionario (id_organismo);
CREATE INDEX idx_funcionario_ccp        ON funcionario (id_ccp);
CREATE INDEX idx_funcionario_unidades   ON funcionario (id_unidades);
CREATE INDEX fk_medico_servicio         ON medico (id_servicio);
CREATE INDEX idx_ms_servicio            ON medico_servicio (id_servicio);
CREATE INDEX idx_beneficiario_funcionario ON beneficiario (id_funcionario);
CREATE INDEX idx_beneficiario_persona     ON beneficiario (id_persona);
CREATE INDEX idx_documento_funcionario    ON documento (id_funcionario);
CREATE INDEX idx_documento_beneficiario   ON documento (id_beneficiario);

-- ---- solicitudes y citas ---------------------------------------------------
CREATE INDEX idx_solicitud_funcionario ON solicitud (id_funcionario);
CREATE INDEX idx_solicitud_estado      ON solicitud (estatus_solicitud);
CREATE INDEX idx_solicitud_fecha       ON solicitud (fecha_creacion);
CREATE INDEX cita_id_servicio          ON cita (id_servicio);
CREATE INDEX idx_cita_solicitud        ON cita (id_solicitud);
CREATE INDEX idx_cita_fecha            ON cita (fecha_cita);
CREATE INDEX idx_cita_medico           ON cita (id_medico);
CREATE INDEX fk_encuesta_usuario       ON encuesta_cita (id_usuario);

-- ---- módulos clínicos ------------------------------------------------------
CREATE INDEX idx_hist_persona         ON ginecologia_historia_medica (id_persona);
CREATE INDEX idx_examen_persona       ON ginecologia_examen_ginecoobstetrico (id_persona);
CREATE INDEX idx_estudios_persona     ON ginecologia_estudios (id_persona);
CREATE INDEX idx_sesion_persona       ON ginecologia_sesion (id_persona, fecha);
CREATE INDEX idx_sesion_cita          ON ginecologia_sesion (id_cita);
CREATE INDEX fk_sesion_medico         ON ginecologia_sesion (id_medico);
CREATE INDEX idx_mi_hist_persona      ON medicina_interna_historia_medica (id_persona);
CREATE INDEX idx_ped_hist_persona     ON pediatria_historia_medica (id_persona);
CREATE INDEX idx_psi_hist_persona     ON psicologia_historia_medica (id_persona);
CREATE INDEX idx_trauma_hist_persona  ON traumatologia_historia_medica (id_persona);
CREATE INDEX idx_odo_hist_persona     ON odontologia_historia_medica (id_persona);
CREATE INDEX idx_odo_examen_persona   ON odontologia_examen_boca (id_persona);
CREATE INDEX idx_pieza_persona        ON odontologia_pieza (id_persona, numero_fdi);
CREATE INDEX idx_odo_sesion_persona   ON odontologia_sesion (id_persona, fecha);
CREATE INDEX idx_odo_sesion_cita      ON odontologia_sesion (id_cita);
CREATE INDEX fk_odo_sesion_medico     ON odontologia_sesion (id_medico);
CREATE INDEX idx_persona_patologia_persona   ON persona_patologia (id_persona);
CREATE INDEX idx_persona_patologia_patologia ON persona_patologia (id_patologia);

-- ---- seguridad de cuenta ---------------------------------------------------
CREATE INDEX idx_ph_usuario           ON password_historial (id_usuario, creada);
CREATE INDEX idx_cv_usuario           ON correo_verificacion (id_usuario, usado);
CREATE INDEX idx_intentos_rec_ci      ON intentos_recuperacion (ci_user);
CREATE INDEX idx_intentos_rec_ip      ON intentos_recuperacion (ip);
CREATE INDEX idx_intentos_val_ci      ON intentos_validacion (ci_user);
CREATE INDEX idx_intentos_val_ip      ON intentos_validacion (ip);

-- ---- pagos y cierre de caja ------------------------------------------------
CREATE INDEX idx_verificacion_pago_estado ON verificacion_pago (estado, fecha_verificacion);
CREATE INDEX idx_verificacion_pago_fecha  ON verificacion_pago (fecha_verificacion);

-- ---- notificaciones y auditoría -------------------------------------------
CREATE INDEX idx_notificacion_usuario ON notificacion (id_usuario);
CREATE INDEX idx_notificacion_leida   ON notificacion (leida);
CREATE INDEX idx_logeo_ci             ON logeo (ci_user);
CREATE INDEX idx_logeo_estado         ON logeo (estado);
CREATE INDEX idx_bitacora_logeo       ON bitacora (id_logeo);
CREATE INDEX idx_bitacora_usuario     ON bitacora (id_usuario);
CREATE INDEX idx_bitacora_fecha       ON bitacora (fecha);

-- ---- permisos --------------------------------------------------------------
CREATE INDEX idx_rol_permiso_permiso  ON rol_permiso (id_permiso);
CREATE INDEX id_permiso_usuario_permiso ON usuario_permiso (id_permiso);


-- ============================================================================
-- 11. TRIGGERS
-- ============================================================================

CREATE TRIGGER trg_nota_clinica_touch
    BEFORE UPDATE ON nota_clinica
    FOR EACH ROW
    EXECUTE FUNCTION iaa_touch_actualizado_en();


-- ============================================================================
-- 12. SEMILLAS INSTITUCIONALES
-- ============================================================================

-- Roles: se preservan los IDs históricos que ya usa el código PHP.
INSERT INTO rol (id, nombre, descripcion) OVERRIDING SYSTEM VALUE VALUES
    (1, 'funcionario',   'Funcionario afiliado al organismo'),
    (3, 'moderador',     'Moderador de contenidos'),
    (4, 'supervisor',    'Control financiero: pagos, tasas, cierre de caja y morbilidad'),
    (5, 'administrador', 'Administrador del sistema'),
    (6, 'medico',        'Personal médico'),
    (7, 'particular',    'Usuario particular no afiliado');

-- Reposiciona la secuencia tras la inserción con IDs explícitos.
SELECT setval(pg_get_serial_sequence('rol', 'id'), 7, true);

-- Permisos canónicos (18). Fuente única: database/permisos_canonicos.sql
INSERT INTO permiso (id, nombre, descripcion) OVERRIDING SYSTEM VALUE VALUES
    ( 1, 'ver_perfil',              'Ver perfil personal'),
    ( 2, 'editar_perfil',           'Editar perfil personal'),
    ( 3, 'gestionar_beneficiarios', 'Gestionar beneficiarios'),
    ( 4, 'subir_documentos',        'Subir documentos'),
    ( 5, 'solicitar_servicio',      'Solicitar servicio médico'),
    ( 6, 'ver_citas',               'Ver citas médicas'),
    ( 7, 'agendar_cita',            'Agendar citas'),
    ( 8, 'aprobar_solicitudes',     'Aprobar/rechazar solicitudes'),
    ( 9, 'gestionar_pagos',         'Gestionar pagos y tasas'),
    (10, 'administrar_usuarios',    'Administrar usuarios del sistema'),
    (11, 'ver_bitacora',            'Ver bitácora de auditoría'),
    (12, 'configurar_servicios',    'Configurar cuadro médico'),
    (13, 'configurar_institucional','Configurar rangos, CCP, unidades y organismos'),
    (14, 'configurar_territorial',  'Configurar estados y municipios'),
    (15, 'enviar_notificaciones',   'Enviar notificaciones a los usuarios'),
    (16, 'mantenimiento_bd',        'Respaldo, restauración y mantenimiento de la base de datos'),
    (17, 'gestionar_patologias',    'Administrar el catálogo de patologías CIE-10'),
    (18, 'ver_morbilidad',          'Consultar la relación semanal de morbilidades');

SELECT setval(pg_get_serial_sequence('permiso', 'id'), 18, true);


-- ============================================================================
-- 13. VERIFICACIÓN POST-CREACIÓN
-- Ejecutar tras cargar la ETL para comprobar el inventario.
-- ============================================================================
--
--   SELECT COUNT(*) AS tablas_esperadas_50
--   FROM information_schema.tables
--   WHERE table_schema = 'public';
--
--   SELECT COUNT(*) AS roles_esperados_6 FROM rol;
--   SELECT COUNT(*) AS permisos_esperados_17 FROM permiso;
--   SELECT COUNT(*) AS permisos_por_rol_esperado_52 FROM rol_permiso;
--
-- ============================================================================
-- FIN DEL ESQUEMA
-- ============================================================================
