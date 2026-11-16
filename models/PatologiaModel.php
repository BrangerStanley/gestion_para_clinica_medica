<?php
// models/PatologiaModel.php
// Catalogo de patologias CIE-10 (tabla `patologia`) y su asignacion a
// personas (tabla puente `persona_patologia`).
//
// Convenciones del proyecto:
//  - Sin framework. Toda consulta usa prepare() + bind_param().
//  - $conexion es un objeto mysqli recibido por el constructor.
//  - Se devuelven arrays asociativos (MYSQLI_ASSOC), nunca objetos mysqli.
class PatologiaModel {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // ===================== LECTURA =====================

    /**
     * Lista el catalogo con filtro opcional y paginacion.
     * Cada fila incluye `usos`: cuantos historicos clinicos usan esa patologia.
     *
     * @param string $buscar    Texto libre para patologia o codigo_cie10.
     * @param int    $pagina    Pagina 1-based.
     * @param int    $porPagina Tamano de pagina (1..200).
     * @return array
     */
    public function obtenerTodos($buscar = '', $pagina = 1, $porPagina = 50) {
        $pagina    = max(1, intval($pagina));
        $porPagina = min(200, max(1, intval($porPagina)));
        $desplaz   = ($pagina - 1) * $porPagina;
        $buscar    = trim($buscar);

        $base = "SELECT p.id_patologia, p.patologia, p.codigo_cie10,
                        (SELECT COUNT(*) FROM persona_patologia pp
                          WHERE pp.id_patologia = p.id_patologia) AS usos
                   FROM patologia p";
        $orden = " ORDER BY p.patologia ASC";

        // bind_param exige argumentos por referencia, así que no se puede
        // construir la llamada de forma dinamica (ni con spread ni con
        // call_user_func_array). Se resuelve con ramas explicitas.
        if ($buscar !== '') {
            $termino  = '%' . $buscar . '%';
            $sql      = $base . " WHERE p.patologia LIKE ? OR p.codigo_cie10 LIKE ?" . $orden . " LIMIT ? OFFSET ?";
            $stmt = $this->conexion->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ssii', $termino, $termino, $porPagina, $desplaz);
        } else {
            $sql  = $base . $orden . " LIMIT ? OFFSET ?";
            $stmt = $this->conexion->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ii', $porPagina, $desplaz);
        }

        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $filas;
    }

    /**
     * Total de registros del catalogo, aplicando el mismo filtro de obtenerTodos().
     */
    public function contarTodas($buscar = '') {
        $sql    = "SELECT COUNT(*) AS total FROM patologia p";
        $buscar = trim($buscar);
        if ($buscar !== '') {
            $sql .= " WHERE p.patologia LIKE ? OR p.codigo_cie10 LIKE ?";
            $termino = '%' . $buscar . '%';
            $stmt = $this->conexion->prepare($sql);
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param('ss', $termino, $termino);
        } else {
            $stmt = $this->conexion->prepare($sql);
            if (!$stmt) {
                return 0;
            }
        }
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($fila['total']) ? intval($fila['total']) : 0;
    }

    /**
     * Todas las patologias sin paginar. Usada por el catalogo de consulta.
     */
    public function obtenerTodas() {
        $sql = "SELECT id_patologia, patologia, codigo_cie10 FROM patologia ORDER BY patologia";
        $result = $this->conexion->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerPorId($id_patologia) {
        $sql = "SELECT * FROM patologia WHERE id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_patologia);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $fila ? $fila : null;
    }

    /**
     * Busqueda para el autocompletado del historial clinico.
     * Contrato de salida: [{ id, clave, descripcion }]
     */
    public function buscar($termino) {
        $sql = "SELECT id_patologia AS id, codigo_cie10 AS clave, patologia AS descripcion
                  FROM patologia
                 WHERE codigo_cie10 LIKE ? OR patologia LIKE ?
                 ORDER BY patologia
                 LIMIT 20";
        $stmt = $this->conexion->prepare($sql);
        $buscar = "%{$termino}%";
        $stmt->bind_param("ss", $buscar, $buscar);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $filas;
    }

    // ===================== ESCRITURA =====================

    public function crear($patologia, $codigo_cie10 = null) {
        $sql = "INSERT INTO patologia (patologia, codigo_cie10) VALUES (?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ss", $patologia, $codigo_cie10);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $this->conexion->insert_id : null;
    }

    public function actualizar($id_patologia, $patologia, $codigo_cie10) {
        $sql = "UPDATE patologia SET patologia = ?, codigo_cie10 = ? WHERE id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ssi", $patologia, $codigo_cie10, $id_patologia);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function eliminar($id_patologia) {
        $sql = "DELETE FROM patologia WHERE id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_patologia);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ===================== VALIDACION DE NEGOCIO =====================

    /**
     * Verifica si un codigo CIE-10 ya pertenece a otra patologia del catalogo.
     * $excluirId se usa al editar para no detectarse a si mismo.
     */
    public function existeCodigoCIE10($codigo, $excluirId = 0) {
        $codigo = trim((string)$codigo);
        if ($codigo === '') {
            return false;
        }
        $sql = "SELECT COUNT(*) AS total FROM patologia
                 WHERE codigo_cie10 = ? AND id_patologia <> ?";
        $stmt = $this->conexion->prepare($sql);
        $excluirId = intval($excluirId);
        $stmt->bind_param("si", $codigo, $excluirId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($fila['total']) && intval($fila['total']) > 0;
    }

    /**
     * Indica si la patologia esta asignada a alguna persona.
     * El FK persona_patologia -> patologia es ON DELETE CASCADE, asi que
     * borrar una patologia en uso eliminaria silenciosamente antecedentes
     * clinicos reales. El controller debe impedirlo usando este metodo.
     */
    public function estaEnUso($id_patologia) {
        $sql = "SELECT COUNT(*) AS total FROM persona_patologia WHERE id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_patologia);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($fila['total']) && intval($fila['total']) > 0;
    }

    public function contarPersonasConPatologia($id_patologia) {
        $sql = "SELECT COUNT(*) AS total FROM persona_patologia WHERE id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_patologia);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($fila['total']) ? intval($fila['total']) : 0;
    }

    // ===================== ASIGNACION A PERSONAS =====================

    public function obtenerPorPersona($id_persona) {
        $sql = "SELECT p.id_patologia, p.patologia, p.codigo_cie10, pp.fecha_registro
                FROM persona_patologia pp
                INNER JOIN patologia p ON pp.id_patologia = p.id_patologia
                WHERE pp.id_persona = ?
                ORDER BY p.patologia";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_persona);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $filas;
    }

    public function agregarAPersona($id_persona, $id_patologia) {
        $sql = "INSERT INTO persona_patologia (id_persona, id_patologia) VALUES (?, ?)
                ON CONFLICT (id_persona, id_patologia) DO UPDATE SET fecha_registro = CURRENT_TIMESTAMP";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ii", $id_persona, $id_patologia);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function eliminarDePersona($id_persona, $id_patologia) {
        $sql = "DELETE FROM persona_patologia WHERE id_persona = ? AND id_patologia = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ii", $id_persona, $id_patologia);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ===================== ALIAS DE COMPATIBILIDAD =====================
    // Mismo nombre que usan los demas catalogos (MedicoModel, ServicioModel)
    // para que los controllers se lean igual.

    public function crearPatologia($patologia, $codigo_cie10 = null) {
        return $this->crear($patologia, $codigo_cie10);
    }

    public function actualizarPatologia($id_patologia, $patologia, $codigo_cie10) {
        return $this->actualizar($id_patologia, $patologia, $codigo_cie10);
    }

    public function eliminarPatologia($id_patologia) {
        return $this->eliminar($id_patologia);
    }
}
?>
