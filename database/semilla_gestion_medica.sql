-- ============================================================================
-- MÓDULO DE GESTIÓN MÉDICA — SEMILLA DE DATOS (IAA)
-- Archivo: database/semilla_gestion_medica.sql
-- ----------------------------------------------------------------------------
-- Datos mínimos para poder levantar y probar el módulo de Gestión Médica:
--   * Matriz de permisos por rol (rol_permiso).
--   * Cuenta administrativa de muestra con contraseña deshabilitada.
--   * Cuentas de médicos con su ficha profesional (medico + medico_servicio).
--   * Catálogos de servicios, especialidades y patologías CIE-10.
--
-- Ejecutar DESPUÉS de database/esquema.sql:
--   psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 \
--        -f database/esquema.sql
--   psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 \
--        -f database/semilla_gestion_medica.sql
--
-- Las cuentas de muestra usan una contraseña inválida ('!') y no pueden iniciar sesión.
-- ============================================================================

\set ON_ERROR_STOP on

-- ----------------------------------------------------------------------------
-- 1. MATRIZ DE PERMISOS POR ROL
-- ----------------------------------------------------------------------------
-- El administrador recibe los 18 permisos canónicos; el médico, los que
-- necesita para operar su consulta.
DELETE FROM rol_permiso WHERE id_rol IN (5, 6);

INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 5, id FROM permiso;

INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT 6, id FROM permiso
WHERE nombre IN ('ver_perfil', 'solicitar_servicio', 'ver_citas', 'agendar_cita');

-- ----------------------------------------------------------------------------
-- 2. CUENTA DE ADMINISTRADOR
-- ----------------------------------------------------------------------------
INSERT INTO persona (id_persona, nombre, ci_usuario, telefono, sexo, correo)
OVERRIDING SYSTEM VALUE
VALUES (1, 'Administrador Demo', 'V-99000001', '0000000000', 'M', 'admin@example.invalid');

SELECT setval(pg_get_serial_sequence('persona', 'id_persona'), 1, true);

INSERT INTO usuario (id_usuario, id_persona, ci_usuario, contrasena,
                     password_changed_at, id_rol, estado)
OVERRIDING SYSTEM VALUE
VALUES (1, 1, 'V-99000001',
        '!',
        now(), 5, 'activo');

SELECT setval(pg_get_serial_sequence('usuario', 'id_usuario'), 1, true);

-- ----------------------------------------------------------------------------
-- 3. CATÁLOGO DE SERVICIOS
-- ----------------------------------------------------------------------------
INSERT INTO servicio (id_servicio, nombre_servicio, disponibilidad, cobertura, tipo_atencion)
OVERRIDING SYSTEM VALUE
VALUES (1, 'Medicina General y Preventiva', 'Disponible', 'Consulta externa', 'Presencial'),
       (2, 'Especialidades Médicas',         'Disponible', 'Consulta externa', 'Presencial'),
       (3, 'Servicios de Apoyo Diagnóstico', 'Disponible', 'Diagnóstico',    'Presencial');

SELECT setval(pg_get_serial_sequence('servicio', 'id_servicio'), 3, true);

-- ----------------------------------------------------------------------------
-- 4. ESPECIALIDADES MÉDICAS
-- ----------------------------------------------------------------------------
INSERT INTO especialidad (id_especialidad, id_servicio, especialidad, precio)
OVERRIDING SYSTEM VALUE
VALUES (1, 1, 'Medicina Interna',        0.00),
       (2, 1, 'Pediatría',               0.00),
       (3, 2, 'Ginecología y Obstetricia',0.00),
       (4, 2, 'Traumatología',           0.00),
       (5, 2, 'Odontología',             0.00),
       (6, 3, 'Laboratorio Clínico',     0.00);

SELECT setval(pg_get_serial_sequence('especialidad', 'id_especialidad'), 6, true);

-- ----------------------------------------------------------------------------
-- 5. PATOLOGÍAS (CIE-10)
-- ----------------------------------------------------------------------------
INSERT INTO patologia (id_patologia, patologia, codigo_cie10)
OVERRIDING SYSTEM VALUE
VALUES (1, 'Hipertensión esencial (primaria)',        'I10'),
       (2, 'Diabetes mellitus no insulinodependiente', 'E11'),
       (3, 'Faringitis aguda',                         'J02'),
       (4, 'Dolor lumbar bajo',                        'M54.5');

SELECT setval(pg_get_serial_sequence('patologia', 'id_patologia'), 4, true);

-- ----------------------------------------------------------------------------
-- 6. MÉDICOS (cuenta + ficha profesional + servicios asignados)
-- ----------------------------------------------------------------------------
-- El nombre del médico se resuelve desde persona.nombre cruzando por cédula
-- (medico.nro_colegiado = usuario.ci_usuario).
INSERT INTO persona (id_persona, nombre, ci_usuario, telefono, sexo, correo)
OVERRIDING SYSTEM VALUE
VALUES (2, 'Profesional Demo 01', 'V-99000002', '0000000000', 'F', 'profesional01@example.invalid'),
       (3, 'Profesional Demo 02', 'V-99000003', '0000000000', 'M', 'profesional02@example.invalid'),
       (4, 'Profesional Demo 03', 'V-99000004', '0000000000', 'F', 'profesional03@example.invalid'),
       (5, 'Profesional Demo 04', 'V-99000005', '0000000000', 'M', 'profesional04@example.invalid');

SELECT setval(pg_get_serial_sequence('persona', 'id_persona'), 5, true);

INSERT INTO usuario (id_usuario, id_persona, ci_usuario, contrasena,
                     password_changed_at, id_rol, estado)
OVERRIDING SYSTEM VALUE
VALUES (2, 2, 'V-99000002', '!', now(), 6, 'activo'),
       (3, 3, 'V-99000003', '!', now(), 6, 'activo'),
       (4, 4, 'V-99000004', '!', now(), 6, 'activo'),
       (5, 5, 'V-99000005', '!', now(), 6, 'activo');

SELECT setval(pg_get_serial_sequence('usuario', 'id_usuario'), 5, true);

INSERT INTO medico (id_medico, nro_colegiado, especialidad, estatus_medico, id_servicio)
OVERRIDING SYSTEM VALUE
VALUES (1, 'V-99000002', 'Medicina Interna',         'activo', 1),
       (2, 'V-99000003', 'Pediatría',                'activo', 1),
       (3, 'V-99000004', 'Ginecología y Obstetricia', 'activo', 2),
       (4, 'V-99000005', 'Traumatología',            'activo', 2);

SELECT setval(pg_get_serial_sequence('medico', 'id_medico'), 4, true);

INSERT INTO medico_servicio (id_medico, id_servicio)
VALUES (1, 1), (2, 1), (3, 2), (4, 2);

-- ----------------------------------------------------------------------------
-- 7. VERIFICACIÓN
-- ----------------------------------------------------------------------------
--   SELECT nombre_servicio FROM servicio ORDER BY id_servicio;
--   SELECT especialidad, id_servicio FROM especialidad ORDER BY id_especialidad;
--   SELECT nro_colegiado, especialidad FROM medico ORDER BY id_medico;
--   SELECT codigo_cie10, patologia FROM patologia ORDER BY id_patologia;
