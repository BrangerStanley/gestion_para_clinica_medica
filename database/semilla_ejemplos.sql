-- ===========================================================================
-- semilla_ejemplos.sql
-- Datos de ejemplo para demostrar el módulo de Gestión Médica:
--   * 5 médicos con cuenta de usuario (rol 6 = médico)
--   * 4 funcionarios/pacientes (rol 1 = funcionario)
--   * Solicitudes y citas de la semana en curso
--   * Historias médicas (expedientes) en cinco módulos distintos
--
-- Es idempotente: se puede ejecutar varias veces sin duplicar registros.
--
--   psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 \
--        -f database/semilla_ejemplos.sql
--
-- Las cuentas de ejemplo usan una contraseña inválida ('!') y no pueden iniciar sesión.
-- ===========================================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- PERSONAS: médicos
-- ---------------------------------------------------------------------------
INSERT INTO persona (nombre, apellidos, nombres, ci_usuario, sexo, fecha_nacimiento, correo, telefono)
SELECT v.nombre, v.apellidos, v.nombres, v.ci, v.sexo, v.fecha_nac::date, v.correo, v.telefono
FROM (VALUES
    ('Profesional Demo 01', 'Demo 01', 'Profesional 01', 'V-91000001', 'F', '1990-01-01', 'profesional01@example.invalid', '0000000000'),
    ('Profesional Demo 02', 'Demo 02', 'Profesional 02', 'V-91000002', 'M', '1990-01-01', 'profesional02@example.invalid', '0000000000'),
    ('Profesional Demo 03', 'Demo 03', 'Profesional 03', 'V-91000003', 'F', '1990-01-01', 'profesional03@example.invalid', '0000000000'),
    ('Profesional Demo 04', 'Demo 04', 'Profesional 04', 'V-91000004', 'M', '1990-01-01', 'profesional04@example.invalid', '0000000000'),
    ('Profesional Demo 05', 'Demo 05', 'Profesional 05', 'V-91000005', 'F', '1990-01-01', 'profesional05@example.invalid', '0000000000')
) AS v(nombre, apellidos, nombres, ci, sexo, fecha_nac, correo, telefono)
WHERE NOT EXISTS (SELECT 1 FROM persona p WHERE p.ci_usuario = v.ci);

-- ---------------------------------------------------------------------------
-- PERSONAS: pacientes (funcionarios)
-- ---------------------------------------------------------------------------
INSERT INTO persona (nombre, apellidos, nombres, ci_usuario, sexo, fecha_nacimiento, correo, telefono)
SELECT v.nombre, v.apellidos, v.nombres, v.ci, v.sexo, v.fecha_nac::date, v.correo, v.telefono
FROM (VALUES
    ('Paciente Demo 01', 'Demo 01', 'Paciente 01', 'V-92000001', 'M', '1990-01-01', 'paciente01@example.invalid', '0000000000'),
    ('Paciente Demo 02', 'Demo 02', 'Paciente 02', 'V-92000002', 'F', '1990-01-01', 'paciente02@example.invalid', '0000000000'),
    ('Paciente Demo 03', 'Demo 03', 'Paciente 03', 'V-92000003', 'M', '1990-01-01', 'paciente03@example.invalid', '0000000000'),
    ('Paciente Demo 04', 'Demo 04', 'Paciente 04', 'V-92000004', 'F', '1990-01-01', 'paciente04@example.invalid', '0000000000')
) AS v(nombre, apellidos, nombres, ci, sexo, fecha_nac, correo, telefono)
WHERE NOT EXISTS (SELECT 1 FROM persona p WHERE p.ci_usuario = v.ci);

-- ---------------------------------------------------------------------------
-- USUARIOS: médicos (rol 6) y pacientes (rol 1)
-- ---------------------------------------------------------------------------
INSERT INTO usuario (id_persona, ci_usuario, contrasena, id_rol, estado)
SELECT p.id_persona, p.ci_usuario,
       '!', 6, 'activo'
FROM persona p
WHERE p.ci_usuario IN ('V-91000001','V-91000002','V-91000003','V-91000004','V-91000005')
  AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.ci_usuario = p.ci_usuario);

INSERT INTO usuario (id_persona, ci_usuario, contrasena, id_rol, estado)
SELECT p.id_persona, p.ci_usuario,
       '!', 1, 'activo'
FROM persona p
WHERE p.ci_usuario IN ('V-92000001','V-92000002','V-92000003','V-92000004')
  AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.ci_usuario = p.ci_usuario);

-- ---------------------------------------------------------------------------
-- MÉDICOS: ficha + servicios asignados
-- ---------------------------------------------------------------------------
INSERT INTO medico (nro_colegiado, especialidad, estatus_medico, id_servicio)
SELECT v.ci, v.especialidad, 'activo', v.id_servicio
FROM (VALUES
    ('V-91000001', 'Medicina General',           1),
    ('V-91000002', 'Odontología',                2),
    ('V-91000003', 'Traumatología y Ortopedia',  2),
    ('V-91000004', 'Laboratorio Clínico',        3),
    ('V-91000005', 'Ginecología',                2)
) AS v(ci, especialidad, id_servicio)
WHERE NOT EXISTS (SELECT 1 FROM medico m WHERE m.nro_colegiado = v.ci);

INSERT INTO medico_servicio (id_medico, id_servicio)
SELECT m.id_medico, x.id_servicio
FROM medico m
JOIN (VALUES
    ('V-91000001', 1),
    ('V-91000002', 2),
    ('V-91000003', 2),
    ('V-91000004', 3),
    ('V-91000005', 2)
) AS x(ci, id_servicio) ON x.ci = m.nro_colegiado
ON CONFLICT DO NOTHING;

-- ---------------------------------------------------------------------------
-- FUNCIONARIOS (pacientes)
-- ---------------------------------------------------------------------------
INSERT INTO funcionario (id_usuario, estatus_servicio, fecha_ingreso, nro_credencial)
SELECT u.id_usuario, 'activo', DATE '2000-01-01', 'DEMO-' || right(u.ci_usuario, 4)
FROM usuario u
WHERE u.ci_usuario IN ('V-92000001','V-92000002','V-92000003','V-92000004')
  AND NOT EXISTS (SELECT 1 FROM funcionario f WHERE f.id_usuario = u.id_usuario);

-- ---------------------------------------------------------------------------
-- SOLICITUDES (el formato de descripcion lo parsea EstadisticasCuadroModel)
-- ---------------------------------------------------------------------------
INSERT INTO solicitud (id_funcionario, nro_solicitud, tipo_solicitud, estatus_solicitud, descripcion)
SELECT f.id_funcionario, v.nro, 'cita', v.estatus,
       format('Servicio: %s | Especialidad: %s | Medico: %s | Monto: %s', v.id_serv, v.id_esp, m.id_medico, v.monto)
FROM (VALUES
    ('V-92000001', 'DEMO-CITA-01', 'aprobada', 1, 1, 'V-91000001', 25.00),
    ('V-92000002', 'DEMO-CITA-02', 'aprobada', 2, 5, 'V-91000002', 25.00),
    ('V-92000003', 'DEMO-CITA-03', 'aprobada', 2, 4, 'V-91000003', 30.00),
    ('V-92000004', 'DEMO-CITA-04', 'aprobada', 3, 6, 'V-91000004', 40.00),
    ('V-92000003', 'DEMO-CITA-05', 'aprobada', 2, 3, 'V-91000005', 30.00),
    ('V-92000004', 'DEMO-CITA-06', 'pendiente', 1, 1, 'V-91000001', 30.00)
) AS v(ci, nro, estatus, id_serv, id_esp, ci_med, monto)
JOIN usuario u ON u.ci_usuario = v.ci
JOIN funcionario f ON f.id_usuario = u.id_usuario
JOIN medico m ON m.nro_colegiado = v.ci_med
WHERE NOT EXISTS (SELECT 1 FROM solicitud s WHERE s.nro_solicitud = v.nro);

-- ---------------------------------------------------------------------------
-- CITAS (de la semana en curso: el módulo de expedientes filtra por semana)
-- ---------------------------------------------------------------------------
INSERT INTO cita (id_solicitud, id_servicio, id_medico, fecha_cita, hora_cita, diagnostico, estatus_cita)
SELECT s.id_solicitud, v.id_serv, m.id_medico, CURRENT_DATE - v.dias, v.hora::time,
       v.diagnostico, v.estatus
FROM (VALUES
    ('DEMO-CITA-01', 1, 'V-91000001', 3, '08:30', 'Consulta ficticia de demostración', 'completada'),
    ('DEMO-CITA-02', 2, 'V-91000002', 3, '09:30', 'Consulta ficticia de demostración', 'completada'),
    ('DEMO-CITA-03', 2, 'V-91000003', 2, '10:00', 'Consulta ficticia de demostración', 'completada'),
    ('DEMO-CITA-04', 3, 'V-91000004', 2, '07:45', 'Consulta ficticia de demostración', 'completada'),
    ('DEMO-CITA-05', 2, 'V-91000005', 1, '11:00', 'Consulta ficticia de demostración', 'completada'),
    ('DEMO-CITA-06', 1, 'V-91000001', 0, '14:00', 'Consulta ficticia de demostración', 'programada')
) AS v(nro, id_serv, ci_med, dias, hora, diagnostico, estatus)
JOIN solicitud s ON s.nro_solicitud = v.nro
JOIN medico m ON m.nro_colegiado = v.ci_med
WHERE NOT EXISTS (
    SELECT 1 FROM cita c
    WHERE c.id_solicitud = s.id_solicitud AND c.fecha_cita = CURRENT_DATE - v.dias
);

-- ---------------------------------------------------------------------------
-- HISTORIAS MÉDICAS (expedientes)
-- ---------------------------------------------------------------------------
INSERT INTO medicina_interna_historia_medica
    (id_cita, id_persona, motivo_consulta, antecedentes_personales, antecedentes_patologicos,
     antecedentes_familiares, habitos, examen_fisico, resultados_laboratorio, plan, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '3 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-01'
  AND NOT EXISTS (SELECT 1 FROM medicina_interna_historia_medica h WHERE h.id_cita = c.id_cita);

INSERT INTO odontologia_historia_medica
    (id_cita, id_persona, alergias, diabetes, anemia, artritis, varices, nausea,
     problemas_cardiacos, toma_medicamentos, medicamentos, fecha_registro)
SELECT c.id_cita, u.id_persona, false, false, false, false, false, false, false, false, NULL,
       NOW() - INTERVAL '3 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-02'
  AND NOT EXISTS (SELECT 1 FROM odontologia_historia_medica h WHERE h.id_cita = c.id_cita);

INSERT INTO traumatologia_historia_medica
    (id_cita, id_persona, motivo_consulta, enfermedad_actual, antecedentes,
     examen_medico, diagnostico, tratamiento, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '2 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-03'
  AND NOT EXISTS (SELECT 1 FROM traumatologia_historia_medica h WHERE h.id_cita = c.id_cita);

INSERT INTO laboratorio_historia_medica
    (id_cita, id_especialidad, estatus_resultado, observaciones_generales,
     firma_bioanalista, id_medico_ingresa, id_medico_valida,
     fecha_ingreso, fecha_validacion, fecha_actualizacion)
SELECT c.id_cita, 6, 'validado',
       'Dato ficticio de demostración.',
       'Profesional de laboratorio (ficticio)', m.id_medico, m.id_medico,
       NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN medico m ON m.nro_colegiado = 'V-91000004'
WHERE s.nro_solicitud = 'DEMO-CITA-04'
  AND NOT EXISTS (SELECT 1 FROM laboratorio_historia_medica h WHERE h.id_cita = c.id_cita);

INSERT INTO ginecologia_historia_medica
    (id_cita, id_persona, motivo_consulta, antecedentes, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '1 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-05'
  AND NOT EXISTS (SELECT 1 FROM ginecologia_historia_medica h WHERE h.id_cita = c.id_cita);

COMMIT;
