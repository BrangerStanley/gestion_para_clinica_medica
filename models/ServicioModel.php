<?php
require_once __DIR__ . '/../config/dependencias.php';
class ServicioModel {
    private $conexion;

    /** Ultimo error de negocio: por que no se pudo borrar. */
    public $ultimoError = '';

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    public function obtenerTodos() {
        $sql = "SELECT * FROM servicio ORDER BY nombre_servicio";
        $result = $this->conexion->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerPorId($id_servicio) {
        $sql = "SELECT * FROM servicio WHERE id_servicio = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_servicio);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function crear($nombre_servicio, $disponibilidad = 'Disponible', $cobertura = null, $tipo_atencion = 'Presencial', $duracion_minutos = 60) {
        $sql = "INSERT INTO servicio (nombre_servicio, disponibilidad, cobertura, tipo_atencion, duracion_minutos) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ssssi", $nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos);
        if ($stmt->execute()) {
            return $this->conexion->insert_id;
        }
        return null;
    }

    public function actualizar($id_servicio, $nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos = 60) {
        $sql = "UPDATE servicio SET nombre_servicio = ?, disponibilidad = ?, cobertura = ?, tipo_atencion = ?, duracion_minutos = ? WHERE id_servicio = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("ssssii", $nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos, $id_servicio);
        return $stmt->execute();
    }

    public function eliminar($id_servicio) {
        // H-15/H-07: no se borra una fila que tiene hijos. Antes el
        // ON DELETE CASCADE de la base de datos los destruia en silencio.
        $this->ultimoError = '';
        $hijos = iaa_contar_hijos($this->conexion,
            "SELECT count(*) AS n FROM especialidad WHERE id_servicio = ?",
            (int)$id_servicio
        );
        if ($hijos > 0) {
            $this->ultimoError = iaa_mensaje_hijos($hijos, 'especialidad', 'especialidades') . ' Elimine primero las especialidades del servicio.';
            return false;
        }


        $sql = "DELETE FROM servicio WHERE id_servicio = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_servicio);
        return $stmt->execute();
    }

    // Métodos con nombres que esperan los controladores (aliases)
    public function crearServicio($nombre_servicio, $disponibilidad = 'Disponible', $cobertura = null, $tipo_atencion = 'Presencial', $duracion_minutos = 60) {
        return $this->crear($nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos);
    }

    public function actualizarServicio($id_servicio, $nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos = 60) {
        return $this->actualizar($id_servicio, $nombre_servicio, $disponibilidad, $cobertura, $tipo_atencion, $duracion_minutos);
    }

    public function eliminarServicio($id_servicio) {
        return $this->eliminar($id_servicio);
    }

    public function obtenerDisponibles() {
        $sql = "SELECT * FROM servicio WHERE disponibilidad = 'Disponible' ORDER BY nombre_servicio";
        $result = $this->conexion->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>