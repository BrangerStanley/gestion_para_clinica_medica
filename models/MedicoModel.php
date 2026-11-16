<?php
// models/MedicoModel.php
//
// MAPEO DE COLUMNAS (la tabla medico no tiene FK a persona):
//   nro_colegiado = cedula del medico
//   especialidad   = nombre completo del medico (columna reutilizada)
//   estatus_medico = 'activo' | 'inactivo'
//   id_servicio    = FK -> servicio(id_servicio) NULL
//
// VINCULOS MEDICO <-> SERVICIO
//   La fuente de verdad es la tabla pivote medico_servicio (N <-> M):
//   un medico puede atender varios servicios.
//
//   medico.id_servicio se conserva y se mantiene sincronizada con el
//   PRIMER servicio de la pivote ("servicio principal"). Sirve de
//   respaldo para los puntos de lectura aun no migrados y para decidir
//   el modulo del medico (obtener_modulo_medico.php).
class MedicoModel {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Normaliza el argumento de servicios a un array de enteros > 0, sin
     * duplicados. Acepta un id suelto (int/string) o una lista de ids, para
     * que los llamadores antiguos sigan funcionando igual.
     */
    private function normalizarServicios($idsServicios) {
        if ($idsServicios === null || $idsServicios === '') {
            return [];
        }
        if (!is_array($idsServicios)) {
            $idsServicios = [$idsServicios];
        }
        $limpios = [];
        foreach ($idsServicios as $id) {
            $id = intval($id);
            if ($id > 0) {
                $limpios[$id] = $id;
            }
        }
        return array_values($limpios);
    }

    /**
     * Reemplaza todos los servicios de un medico por los indicados.
     * Se hace en transaccion: o se aplican todos o ninguno.
     * Devuelve el "servicio principal" (el primero) o null si quedo sin servicios.
     */
    public function reemplazarServicios($id_medico, $idsServicios) {
        $id_medico = intval($id_medico);
        if ($id_medico <= 0) {
            return null;
        }
        $ids = $this->normalizarServicios($idsServicios);
        $principal = $ids ? $ids[0] : null;

        $this->conexion->begin_transaction();
        try {
            $stmt = $this->conexion->prepare("DELETE FROM medico_servicio WHERE id_medico = ?");
            $stmt->bind_param("i", $id_medico);
            $stmt->execute();
            $stmt->close();

            if (!empty($ids)) {
                $stmtIns = $this->conexion->prepare(
                    "INSERT INTO medico_servicio (id_medico, id_servicio) VALUES (?, ?) ON CONFLICT DO NOTHING"
                );
                foreach ($ids as $idServicio) {
                    $stmtIns->bind_param("ii", $id_medico, $idServicio);
                    $stmtIns->execute();
                }
                $stmtIns->close();
            }

            // Mantener la columna legada en sincronia con la pivote.
            $stmtLeg = $this->conexion->prepare(
                "UPDATE medico SET id_servicio = ? WHERE id_medico = ?"
            );
            $stmtLeg->bind_param("ii", $principal, $id_medico);
            $stmtLeg->execute();
            $stmtLeg->close();

            $this->conexion->commit();
            return $principal;
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('MedicoModel::reemplazarServicios: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista los servicios de un medico. Con $conNombres=true devuelve
     * filas completas [{id_servicio, nombre_servicio}]; con false, solo ids.
     */
    public function serviciosDelMedico($id_medico, $conNombres = false) {
        $id_medico = intval($id_medico);
        $ids = [];
        if ($id_medico <= 0) {
            return $ids;
        }

        if ($conNombres) {
            $sql = "SELECT ms.id_servicio, s.nombre_servicio
                    FROM medico_servicio ms
                    INNER JOIN servicio s ON s.id_servicio = ms.id_servicio
                    WHERE ms.id_medico = ?
                    ORDER BY s.nombre_servicio ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param("i", $id_medico);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $sql = "SELECT id_servicio FROM medico_servicio WHERE id_medico = ? ORDER BY id_servicio ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_medico);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
            $ids[] = (int)$fila['id_servicio'];
        }
        return $ids;
    }

    public function crear($nro_colegiado, $especialidad, $estatus_medico = 'activo', $id_servicio = null) {
        $ids = $this->normalizarServicios($id_servicio);
        $principal = $ids ? $ids[0] : null;

        $this->conexion->begin_transaction();
        try {
            $sql = "INSERT INTO medico (nro_colegiado, especialidad, estatus_medico, id_servicio) VALUES (?, ?, ?, ?)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param("sssi", $nro_colegiado, $especialidad, $estatus_medico, $principal);
            $stmt->execute();
            $idNuevo = $this->conexion->insert_id;
            $stmt->close();

            // Alta en la pivote con los mismos servicios elegidos.
            if ($idNuevo > 0 && !empty($ids)) {
                $stmtIns = $this->conexion->prepare(
                    "INSERT INTO medico_servicio (id_medico, id_servicio) VALUES (?, ?) ON CONFLICT DO NOTHING"
                );
                foreach ($ids as $idServicio) {
                    $stmtIns->bind_param("ii", $idNuevo, $idServicio);
                    $stmtIns->execute();
                }
                $stmtIns->close();
            }

            $this->conexion->commit();
            return $idNuevo;
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('MedicoModel::crear: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * El nombre del médico NO se guarda en medico: se resuelve desde
     * persona.nombre cruzando por la cédula (medico.nro_colegiado =
     * usuario.ci_usuario). La unión es por cédula porque medico no tiene FK
     * a usuario/persona.
     *
     * Si el médico no tiene cuenta, el nombre no existe y se devuelve ''
     * (no se inventa: hay que crearle la cuenta).
     */
    public function obtenerPorId($id_medico) {
        $sql = "SELECT m.*,
                       p.nombre AS nombre_persona,
                       u.id_usuario AS id_usuario_vinculado,
                       string_agg(s.nombre_servicio, ', ' ORDER BY s.nombre_servicio) AS servicios_nombres,
                       string_agg(ms.id_servicio::text, ',' ORDER BY ms.id_servicio) AS servicios_ids
                FROM medico m
                LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
                LEFT JOIN persona p ON p.id_persona = u.id_persona
                LEFT JOIN medico_servicio ms ON ms.id_medico = m.id_medico
                LEFT JOIN servicio s ON s.id_servicio = ms.id_servicio
                WHERE m.id_medico = ?
                GROUP BY m.id_medico, p.nombre, u.id_usuario";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_medico);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        if ($fila) {
            $fila['servicios_ids'] = $this->listarIdsDe($fila['servicios_ids'] ?? '');
            // Compatibilidad: 'nombre_servicio' (singular) apunta al servicio
            // principal, que es lo que leen los puntos sin migrar.
            $fila['nombre_servicio'] = $this->primeroDe($fila['servicios_nombres'] ?? '');
            // Nombre resuelto desde persona. Sin cuenta => '' (médico huérfano).
            $fila['nombre_persona'] = trim((string)($fila['nombre_persona'] ?? ''));
            // Mismo semántico que obtenerTodos(): si no hay id_usuario vinculado,
            // el médico no puede iniciar sesión.
            $fila['tiene_cuenta'] = isset($fila['id_usuario_vinculado'])
                && $fila['id_usuario_vinculado'] !== null;
        }
        return $fila;
    }

    /**
     * Listado con filtros.
     * Filtro soportado: 'id_servicio' (devuelve los medicos de ESE servicio),
     * 'especialidad' y 'estatus_medico' y 'buscar' (cedula/nombre).
     *
     * El nombre se resuelve desde persona.nombre cruzando por la cédula
     * (ver obtenerPorId). 'especialidad' es ahora la especialidad real.
     */
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT m.*,
                       p.nombre AS nombre_persona,
                       u.id_usuario AS id_usuario_vinculado,
                       string_agg(s.nombre_servicio, ', ' ORDER BY s.nombre_servicio) AS servicios_nombres,
                       string_agg(ms.id_servicio::text, ',' ORDER BY ms.id_servicio) AS servicios_ids
                FROM medico m
                LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
                LEFT JOIN persona p ON p.id_persona = u.id_persona
                LEFT JOIN medico_servicio ms ON ms.id_medico = m.id_medico
                LEFT JOIN servicio s ON s.id_servicio = ms.id_servicio";
        $where = [];
        $params = [];
        $types = "";

        if (isset($filtros['id_servicio']) && $filtros['id_servicio'] !== '' && $filtros['id_servicio'] !== null) {
            $where[] = "EXISTS (SELECT 1 FROM medico_servicio msf
                                WHERE msf.id_medico = m.id_medico
                                  AND msf.id_servicio = ?)";
            $params[] = intval($filtros['id_servicio']);
            $types .= "i";
        }
        if (!empty($filtros['especialidad'])) {
            $where[] = "m.especialidad = ?";
            $params[] = $filtros['especialidad'];
            $types .= "s";
        }
        if (!empty($filtros['estatus_medico'])) {
            $where[] = "m.estatus_medico = ?";
            $params[] = $filtros['estatus_medico'];
            $types .= "s";
        }
        if (!empty($filtros['buscar'])) {
            // Se busca por cédula, especialidad o nombre (el nombre vive en persona).
            $where[] = "(m.nro_colegiado LIKE ? OR m.especialidad LIKE ? OR p.nombre LIKE ?)";
            $buscar = "%{$filtros['buscar']}%";
            $params[] = $buscar;
            $params[] = $buscar;
            $params[] = $buscar;
            $types .= "sss";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // GROUP BY obligatorio por el GROUP_CONCAT de los servicios.
        $sql .= " GROUP BY m.id_medico, p.nombre, u.id_usuario ORDER BY p.nombre, m.nro_colegiado";

        $stmt = $this->conexion->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($filas as &$fila) {
            $fila['servicios_ids'] = $this->listarIdsDe($fila['servicios_ids'] ?? '');
            $fila['nombre_servicio'] = $this->primeroDe($fila['servicios_nombres'] ?? '');
            // Nombre desde persona. '' => el médico no tiene cuenta (huérfano).
            $fila['nombre_persona'] = trim((string)($fila['nombre_persona'] ?? ''));
            $fila['tiene_cuenta'] = isset($fila['id_usuario_vinculado']) && $fila['id_usuario_vinculado'] !== null;
        }
        unset($fila);
        return $filas;
    }

    /**
     * Cuentas con rol 'medico' que NO tienen fila en la tabla medico.
     *
     * Estas son las que entran al sistema pero no pueden operar: obtener_modulo_medico
     * les devuelve id_medico = 0 y no ven citas ni servicio. Se listan para que el
     * administrador complete su ficha profesional desde la vista de médicos.
     *
     * Se resuelve por cédula (medico.nro_colegiado = usuario.ci_usuario), que es
     * la única unión existente entre ambas tablas.
     */
    public function cuentasMedicoSinFila() {
        $sql = "SELECT u.id_usuario, u.ci_usuario, u.estado, p.nombre AS nombre_persona
                FROM usuario u
                INNER JOIN persona p ON p.id_persona = u.id_persona
                LEFT JOIN medico m ON m.nro_colegiado = u.ci_usuario
                INNER JOIN rol r ON r.id = u.id_rol
                WHERE r.nombre = 'medico'
                  AND m.id_medico IS NULL
                ORDER BY p.nombre ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Filas medico cuya cédula no corresponde a ninguna cuenta de usuario.
     * Se pueden consultar pero no entran al sistema: no tienen login.
     */
    public function medicosSinCuenta() {
        $sql = "SELECT m.id_medico, m.nro_colegiado, m.especialidad, m.estatus_medico
                FROM medico m
                LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
                WHERE u.id_usuario IS NULL
                ORDER BY m.especialidad ASC, m.nro_colegiado ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Materializa la fila profesional de una cuenta con rol 'medico'.
     *
     * Es el puente que faltaba: cuando se crea una cuenta de médico o se cambia
     * un rol a 'medico', esta función crea la fila medico si no existe.
     *
     * Idempotente: si ya existe fila con esa cédula, no duplica y devuelve el id.
     * La cédula se toma de usuario.ci_usuario; el nombre NO se guarda aquí (vive
     * en persona).
     *
     * @return int id_medico existente o recién creado; 0 si algo falló.
     */
    public function asegurarFilaMedico($ci_usuario, $especialidad = null) {
        $ci_usuario = trim((string)$ci_usuario);
        if ($ci_usuario === '') {
            return 0;
        }

        // ¿Ya existe?
        $stmt = $this->conexion->prepare("SELECT id_medico FROM medico WHERE nro_colegiado = ? LIMIT 1");
        $stmt->bind_param("s", $ci_usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila) {
            return (int)$fila['id_medico'];
        }

        // Crear. nro_colegiado = cédula (es la llave de unión con la cuenta).
        // especialidad queda NULL hasta que el admin la complete.
        $sql = "INSERT INTO medico (nro_colegiado, especialidad, estatus_medico, id_servicio)
                VALUES (?, ?, 'activo', NULL)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ss", $ci_usuario, $especialidad);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }
        $idNuevo = (int)$this->conexion->insert_id;
        $stmt->close();
        return $idNuevo;
    }

    /**
     * Actualiza la ficha profesional de un médico EXISTENTE.
     *
     * A diferencia de actualizarMedico(), NO toca nro_colegiado: la cédula es la
     * unión con la cuenta de usuario y cambiarla desde esta vista rompería el
     * enlace. Se editan únicamente los datos profesionales y los servicios.
     *
     * @return bool true si se ejecutó; false si no cambió nada.
     */
    public function actualizarFicha($id_medico, $especialidad, $estatus_medico, $idsServicios) {
        $id_medico = intval($id_medico);
        if ($id_medico <= 0) {
            return false;
        }
        $especialidad = trim((string)$especialidad);
        $especialidad = $especialidad === '' ? null : $especialidad;

        $this->conexion->begin_transaction();
        try {
            $sql = "UPDATE medico SET especialidad = ?, estatus_medico = ? WHERE id_medico = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param("ssi", $especialidad, $estatus_medico, $id_medico);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                // reemplazarServicios() ya sincroniza la columna legada.
                $this->reemplazarServicios($id_medico, $idsServicios);
            }

            $this->conexion->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('MedicoModel::actualizarFicha: ' . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerPorEspecialidad($especialidad) {
        $sql = "SELECT * FROM medico WHERE especialidad = ? AND estatus_medico = 'activo' ORDER BY nro_colegiado";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("s", $especialidad);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function actualizar($id_medico, $nro_colegiado, $especialidad, $estatus_medico) {
        $sql = "UPDATE medico SET nro_colegiado = ?, especialidad = ?, estatus_medico = ? WHERE id_medico = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("sssi", $nro_colegiado, $especialidad, $estatus_medico, $id_medico);
        return $stmt->execute();
    }

    public function eliminar($id_medico) {
        $sql = "DELETE FROM medico WHERE id_medico = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_medico);
        $stmt->execute();
        // execute() devuelve true aunque no borre nada; hay que mirar las filas
        // afectadas para no reportar "eliminado" ante un id inexistente.
        return $stmt->affected_rows > 0;
    }

    // ---------------------------------------------------------------
    // Métodos con nombres que esperan los controladores
    // ---------------------------------------------------------------

    /**
     * $id_servicios puede ser un id suelto (compatibilidad) o una lista.
     */
    public function crearMedico($id_servicios, $ci_med, $nombre_med, $estatus_medico = 'activo') {
        return $this->crear($ci_med, $nombre_med, $estatus_medico, $id_servicios);
    }

    public function actualizarMedico($id_med, $ci_med, $nombre_med, $id_servicios, $estatus_medico = 'activo') {
        $this->conexion->begin_transaction();
        try {
            $sql = "UPDATE medico SET nro_colegiado = ?, especialidad = ?, estatus_medico = ? WHERE id_medico = ?";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param("sssi", $ci_med, $nombre_med, $estatus_medico, $id_med);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $this->reemplazarServicios($id_med, $id_servicios);
            }

            $this->conexion->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('MedicoModel::actualizarMedico: ' . $e->getMessage());
            throw $e;
        }
    }

    public function eliminarMedico($id_med) {
        return $this->eliminar($id_med);
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    /** "2,5,7" -> [2,5,7]  |  "" -> [] */
    private function listarIdsDe($texto) {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return [];
        }
        $ids = [];
        foreach (explode(',', $texto) as $trozo) {
            $id = intval($trozo);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** "Odontologia, Psicologia" -> "Odontologia"  |  "" -> "" */
    private function primeroDe($texto) {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return '';
        }
        $partes = explode(',', $texto);
        return trim($partes[0]);
    }
}
?>
