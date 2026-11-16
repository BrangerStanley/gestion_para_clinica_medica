<?php
/**
 * config/dependencias.php — Guardas de borrado (H-15, H-07).
 *
 * Varias tablas de catalogo y de datos tienen hijos. En el esquema, la FK
 * estaba en ON DELETE CASCADE, de modo que borrar un estado se llevaba por
 * delante sus municipios, y borrar una cita o una persona arrastraba el
 * historial clinico. El borrado en cascada no avisa de nada: el usuario cree
 * que esta borrando un estado y en realidad ha destruido el historico de
 * cientos de personas.
 *
 * Estos helpers comprueban los hijos ANTES de borrar y devuelven un mensaje
 * que el controlador muestra al usuario. La restriccion definitiva esta en la
 * base de datos (database/migracion_integridad_v2.sql, que cambia las FK a
 * ON DELETE RESTRICT): la guardia de la aplicacion es la primera capa y la
 * base de datos la ultima.
 */

if (!function_exists('iaa_contar_hijos')) {
    /**
     * Cuenta las filas dependientes de una fila.
     *
     * @param object $conexion Conexion IaaConnection.
     * @param string  $sql      Consulta con un unico marcador '?' para el id.
     * @param int     $id       Id de la fila que se quiere borrar.
     * @return int Numero de filas dependientes (0 si no hay ninguna).
     */
    function iaa_contar_hijos($conexion, string $sql, int $id): int
    {
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $fila ? (int)($fila['n'] ?? 0) : 0;
    }
}

if (!function_exists('iaa_mensaje_hijos')) {
    /**
     * Mensaje estandar para el bloqueo de un borrado.
     *
     * @param int    $hijos    Numero de filas dependientes.
     * @param string $sustantivo Singular de lo que depende ("municipio").
     * @param string $plural    Plural de lo que depende ("municipios").
     */
    function iaa_mensaje_hijos(int $hijos, string $sustantivo, string $plural): string
    {
        $etiqueta = $hijos === 1 ? $sustantivo : $plural;
        return sprintf(
            'No se puede eliminar: hay %d %s asociado(s).',
            $hijos,
            $etiqueta
        );
    }
}
