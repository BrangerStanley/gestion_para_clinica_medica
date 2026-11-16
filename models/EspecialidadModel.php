<?php
require_once __DIR__ . '/../config/dependencias.php';
class EspecialidadModel {
    private $conexion;

    /** Ultimo error de negocio: por que no se pudo borrar. */
    public $ultimoError = '';

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    public function obtenerEspecialidadesPorServicio($id_servicio) {
        $sql = "SELECT e.id_especialidad, e.id_servicio, e.especialidad, e.precio, s.nombre_servicio
                FROM especialidad e
                LEFT JOIN servicio s ON e.id_servicio = s.id_servicio
                WHERE e.id_servicio = ? 
                ORDER BY e.especialidad ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_servicio);
        $stmt->execute();
        $result = $stmt->get_result();
        $datos = [];
        while ($row = $result->fetch_assoc()) {
            $datos[] = $row;
        }
        $stmt->close();
        return $datos;
    }

    public function obtenerTodas() {
        $sql = "SELECT e.*, s.nombre_servicio 
                FROM especialidad e
                LEFT JOIN servicio s ON e.id_servicio = s.id_servicio
                ORDER BY e.especialidad ASC";
        $result = $this->conexion->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function crear($especialidad, $id_servicio, $precio = 0) {
        $sql = "INSERT INTO especialidad (especialidad, id_servicio, precio) VALUES (?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("sid", $especialidad, $id_servicio, $precio);
        return $stmt->execute();
    }

    public function actualizar($id_especialidad, $especialidad, $precio) {
        $sql = "UPDATE especialidad SET especialidad = ?, precio = ? WHERE id_especialidad = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("sdi", $especialidad, $precio, $id_especialidad);
        return $stmt->execute();
    }

    public function eliminar($id_especialidad) {
        // H-15/H-07: no se borra una fila que tiene hijos. Antes el
        // ON DELETE CASCADE de la base de datos los destruia en silencio.
        $this->ultimoError = '';
        $hijos = iaa_contar_hijos($this->conexion,
            "SELECT count(*) AS n FROM laboratorio_historia_medica WHERE id_especialidad = ?",
            (int)$id_especialidad
        );
        if ($hijos > 0) {
            $this->ultimoError = iaa_mensaje_hijos($hijos, 'resultado de laboratorio', 'resultados de laboratorio') . ' Elimine primero los resultados de laboratorio de esta especialidad.';
            return false;
        }


        $sql = "DELETE FROM especialidad WHERE id_especialidad = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("i", $id_especialidad);
        return $stmt->execute();
    }

    // Métodos con nombres que esperan los controladores
    public function crearEspecialidad($id_servicio, $especialidad, $precio) {
        return $this->crear($especialidad, $id_servicio, $precio);
    }

    public function actualizarEspecialidad($id_especialidad, $especialidad, $precio) {
        return $this->actualizar($id_especialidad, $especialidad, $precio);
    }

    public function eliminarEspecialidad($id_especialidad) {
        return $this->eliminar($id_especialidad);
    }
}
?>
