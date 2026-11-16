-- ============================================================================
-- MIGRACIÓN: duración de la consulta en el catálogo de servicios
-- Archivo: database/migracion_duracion_servicio.sql
-- ----------------------------------------------------------------------------
-- models/ServicioModel.php inserta y actualiza la columna `duracion_minutos`
-- (la usa la agenda de citas para calcular el horario de la consulta).
-- El esquema heredado de MariaDB la traía; el esquema PostgreSQL no la
-- declaraba, de modo que el alta de servicios fallaba con
--   SQLSTATE 42703: no existe la columna «duracion_minutos»
--
-- La migración es IDEMPOTENTE: se puede ejecutar varias veces sin efecto.
-- ============================================================================

\set ON_ERROR_STOP on

ALTER TABLE servicio
    ADD COLUMN IF NOT EXISTS duracion_minutos INTEGER DEFAULT 60;

-- Los servicios ya existentes quedan con la duración estándar de 60 minutos.
UPDATE servicio
   SET duracion_minutos = 60
 WHERE duracion_minutos IS NULL;

COMMENT ON COLUMN servicio.duracion_minutos IS
    'Duración de la consulta en minutos. La usa la agenda de citas.';
