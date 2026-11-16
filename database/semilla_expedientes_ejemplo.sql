-- ===========================================================================
-- semilla_expedientes_ejemplo.sql
-- Dos expedientes de prueba con campos ficticios de demostración:
--
--   1) GINECOLOGÍA  -> cita DEMO-CITA-05 (paciente ficticio V-92000003)
--        historia médica + examen ginecoobstétrico + estudios + sesión
--   2) ODONTOLOGÍA  -> cita DEMO-CITA-02 (paciente ficticio V-92000002)
--        historia médica + examen de la boca + odontograma + sesión
--
-- Requiere haber ejecutado antes database/semilla_ejemplos.sql.
-- Es idempotente: se puede ejecutar varias veces.
--
--   psql -U postgres -d iaa_gestion_medica -v ON_ERROR_STOP=1 \
--        -f database/semilla_expedientes_ejemplo.sql
-- ===========================================================================

BEGIN;

-- ===========================================================================
-- 1. EXPEDIENTE DE GINECOLOGÍA (DEMO-CITA-05)
-- ===========================================================================

-- 1.1 Historia médica
INSERT INTO ginecologia_historia_medica (id_cita, id_persona, motivo_consulta, antecedentes, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '1 day'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-05'
ON CONFLICT (id_cita) DO UPDATE SET
    motivo_consulta = EXCLUDED.motivo_consulta,
    antecedentes = EXCLUDED.antecedentes;

-- 1.2 Examen ginecoobstétrico
INSERT INTO ginecologia_examen_ginecoobstetrico
    (id_cita, id_persona, menarquia, fur, fpp, gestas, paras, cesareas, abortos, citologias, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio', CURRENT_DATE, CURRENT_DATE,
       0, 0, 0, 0,
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '1 day'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-05'
ON CONFLICT (id_cita) DO UPDATE SET
    menarquia = EXCLUDED.menarquia,
    fur = EXCLUDED.fur,
    fpp = EXCLUDED.fpp,
    gestas = EXCLUDED.gestas,
    paras = EXCLUDED.paras,
    cesareas = EXCLUDED.cesareas,
    abortos = EXCLUDED.abortos,
    citologias = EXCLUDED.citologias;

-- 1.3 Estudios complementarios
INSERT INTO ginecologia_estudios (id_cita, id_persona, ecosonograma, laboratorio, observaciones, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '1 day'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-05'
ON CONFLICT (id_cita) DO UPDATE SET
    ecosonograma = EXCLUDED.ecosonograma,
    laboratorio = EXCLUDED.laboratorio,
    observaciones = EXCLUDED.observaciones;

-- 1.4 Sesión / evolución de la cita
DELETE FROM ginecologia_sesion
WHERE id_cita = (SELECT c.id_cita FROM cita c
                 JOIN solicitud s ON s.id_solicitud = c.id_solicitud
                 WHERE s.nro_solicitud = 'DEMO-CITA-05');

INSERT INTO ginecologia_sesion (id_cita, id_persona, id_medico, fecha, diagnostico, fecha_registro)
SELECT c.id_cita, u.id_persona, m.id_medico, c.fecha_cita,
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '1 day'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
LEFT JOIN medico m ON m.nro_colegiado = 'V-91000005'
WHERE s.nro_solicitud = 'DEMO-CITA-05';

-- ===========================================================================
-- 2. EXPEDIENTE DE ODONTOLOGÍA (DEMO-CITA-02)
-- ===========================================================================

-- 2.1 Historia médica (antecedentes del paciente)
INSERT INTO odontologia_historia_medica
    (id_cita, id_persona, alergias, diabetes, anemia, artritis, varices, nausea,
     problemas_cardiacos, toma_medicamentos, medicamentos, fecha_registro)
SELECT c.id_cita, u.id_persona,
       false, false, true, false, false, false, false, true,
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '3 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-02'
ON CONFLICT (id_cita) DO UPDATE SET
    alergias = EXCLUDED.alergias,
    diabetes = EXCLUDED.diabetes,
    anemia = EXCLUDED.anemia,
    artritis = EXCLUDED.artritis,
    varices = EXCLUDED.varices,
    nausea = EXCLUDED.nausea,
    problemas_cardiacos = EXCLUDED.problemas_cardiacos,
    toma_medicamentos = EXCLUDED.toma_medicamentos,
    medicamentos = EXCLUDED.medicamentos;

-- 2.2 Examen de la boca
INSERT INTO odontologia_examen_boca
    (id_cita, id_persona, piezas_afectadas, ptisis, gingivitis, gingivorragia,
     epitelio_alterado, sangrado, intensidad, observaciones, fecha_registro)
SELECT c.id_cita, u.id_persona,
       'Dato ficticio', false, false, false, false, false, 'leve',
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '3 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-02'
ON CONFLICT (id_cita) DO UPDATE SET
    piezas_afectadas = EXCLUDED.piezas_afectadas,
    ptisis = EXCLUDED.ptisis,
    gingivitis = EXCLUDED.gingivitis,
    gingivorragia = EXCLUDED.gingivorragia,
    epitelio_alterado = EXCLUDED.epitelio_alterado,
    sangrado = EXCLUDED.sangrado,
    intensidad = EXCLUDED.intensidad,
    observaciones = EXCLUDED.observaciones;

-- 2.3 Odontograma (solo piezas distintas de "sano")
DELETE FROM odontologia_pieza
WHERE id_cita = (SELECT c.id_cita FROM cita c
                 JOIN solicitud s ON s.id_solicitud = c.id_solicitud
                 WHERE s.nro_solicitud = 'DEMO-CITA-02');

INSERT INTO odontologia_pieza (id_cita, id_persona, numero_fdi, estado, observaciones, fecha_registro)
SELECT c.id_cita, u.id_persona, v.numero, v.estado, v.obs, NOW() - INTERVAL '3 days'
FROM (VALUES
    (16::smallint, 'caries',     'Dato ficticio de demostración.'),
    (26::smallint, 'obturado',   'Dato ficticio de demostración.'),
    (36::smallint, 'caries',     'Dato ficticio de demostración.'),
    (46::smallint, 'ausente',    'Dato ficticio de demostración.')
) AS v(numero, estado, obs)
CROSS JOIN cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
WHERE s.nro_solicitud = 'DEMO-CITA-02';

-- 2.4 Sesión / tratamiento de la cita
DELETE FROM odontologia_sesion
WHERE id_cita = (SELECT c.id_cita FROM cita c
                 JOIN solicitud s ON s.id_solicitud = c.id_solicitud
                 WHERE s.nro_solicitud = 'DEMO-CITA-02');

INSERT INTO odontologia_sesion (id_cita, id_persona, id_medico, fecha, tratamiento, fecha_registro)
SELECT c.id_cita, u.id_persona, m.id_medico, c.fecha_cita,
       'Dato ficticio de demostración.',
       NOW() - INTERVAL '3 days'
FROM cita c
JOIN solicitud s ON s.id_solicitud = c.id_solicitud
JOIN funcionario f ON f.id_funcionario = s.id_funcionario
JOIN usuario u ON u.id_usuario = f.id_usuario
LEFT JOIN medico m ON m.nro_colegiado = 'V-91000002'
WHERE s.nro_solicitud = 'DEMO-CITA-02';

COMMIT;
