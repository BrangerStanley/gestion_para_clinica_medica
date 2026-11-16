<?php
/**
 * controllers/obtener_historia_laboratorio.php  (GET ?id_cita=N)
 * Devuelve la orden y los resultados de laboratorio de una cita.
 * La Secretaría recibe solo los datos administrativos (sin resultados clínicos).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/Conexion.php';
requireSesion();

if (!isset($conexion) || !($conexion instanceof IaaConnection)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

$id_cita = isset($_GET['id_cita']) ? (int)$_GET['id_cita'] : 0;
if ($id_cita <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de cita no válido.']);
    exit;
}

try {
    $rol = (int)($_SESSION['id_rol'] ?? 0);
    $esSuperior = in_array($rol, [4, 5], true);
    if (!$esSuperior && !in_array($rol, [1, 6], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // Perfil
    $ci = (string)($_SESSION['ci_user'] ?? '');
    $stmt = $conexion->prepare("SELECT id_medico, id_servicio, especialidad FROM medico WHERE nro_colegiado = ? LIMIT 1");
    $stmt->bind_param('s', $ci);
    $stmt->execute();
    $miMedico = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $esp = $miMedico && function_exists('mb_strtolower')
        ? mb_strtolower((string)$miMedico['especialidad'], 'UTF-8')
        : strtolower((string)($miMedico['especialidad'] ?? ''));
    $esSecretaria = $rol === 1 && strpos($esp, 'secretar') !== false;
    $esPersonalLaboratorio = $rol === 1 && (
        strpos($esp, 'secretar') !== false
        || strpos($esp, 'asistente') !== false
        || strpos($esp, 'bioanalista') !== false
    );

    if (!$esSuperior && (!$miMedico || ($rol === 1 && !$esPersonalLaboratorio))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    // Cita + paciente + orden
    $stmt = $conexion->prepare(
        "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estatus_cita, c.id_servicio, c.id_medico,
                s.nro_solicitud, s.descripcion,
                p.nombre AS paciente, u.ci_usuario,
                sv.nombre_servicio,
                NULLIF(substring(s.descripcion from 'Especialidad: *([0-9]+)'), '')::bigint AS id_especialidad_sol
         FROM cita c
         INNER JOIN solicitud s ON s.id_solicitud = c.id_solicitud
         INNER JOIN funcionario f ON f.id_funcionario = s.id_funcionario
         INNER JOIN usuario u ON u.id_usuario = f.id_usuario
         INNER JOIN persona p ON p.id_persona = u.id_persona
         LEFT JOIN servicio sv ON sv.id_servicio = c.id_servicio
         WHERE c.id_cita = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
        exit;
    }

    if ($rol === 6 && (int)($cita['id_medico'] ?? 0) !== (int)$miMedico['id_medico']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Esta cita no está asignada a su consultorio.']);
        exit;
    }

    if ($rol === 1) {
        $idServicio = (int)($miMedico['id_servicio'] ?? 0);
        $perteneceAlServicio = $idServicio > 0
            && (int)($cita['id_servicio'] ?? 0) === $idServicio;

        if (!$perteneceAlServicio && $idServicio > 0 && (int)($cita['id_medico'] ?? 0) > 0) {
            $stmt = $conexion->prepare("SELECT 1 FROM medico WHERE id_medico = ? AND id_servicio = ? LIMIT 1");
            $idMedicoCita = (int)$cita['id_medico'];
            $stmt->bind_param('ii', $idMedicoCita, $idServicio);
            $stmt->execute();
            $perteneceAlServicio = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$perteneceAlServicio) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No autorizado para consultar esta cita.']);
            exit;
        }
    }

    $datos = [
        'cita' => $cita,
        'orden' => null,
        'historia' => null,
        'hematologia' => [],
        'orina' => [],
        'heces' => [],
        'perfil' => $esSecretaria ? 'secretaria' : 'clinico',
    ];

    // Historia / estado
    $stmt = $conexion->prepare("SELECT * FROM laboratorio_historia_medica WHERE id_cita = ? LIMIT 1");
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $lab = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $idEsp = $lab && (int)$lab['id_especialidad'] > 0
        ? (int)$lab['id_especialidad']
        : (int)($cita['id_especialidad_sol'] ?? 0);

    if ($idEsp > 0) {
        $stmt = $conexion->prepare("SELECT id_especialidad, especialidad, precio FROM especialidad WHERE id_especialidad = ? LIMIT 1");
        $stmt->bind_param('i', $idEsp);
        $stmt->execute();
        $datos['orden'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $datos['historia'] = $lab ? [
        'estatus_resultado'   => $lab['estatus_resultado'],
        'observaciones_generales' => $lab['observaciones_generales'],
        'firma_bioanalista'   => $lab['firma_bioanalista'],
        'fecha_ingreso'       => $lab['fecha_ingreso'],
        'fecha_validacion'    => $lab['fecha_validacion'],
    ] : ['estatus_resultado' => 'pendiente'];

    // La Secretaría no recibe resultados clínicos.
    if (!$esSecretaria) {
        foreach (['hematologia' => 'laboratorio_hematologia', 'orina' => 'laboratorio_orina', 'heces' => 'laboratorio_heces'] as $clave => $tabla) {
            $stmt = $conexion->prepare("SELECT * FROM $tabla WHERE id_cita = ? LIMIT 1");
            $stmt->bind_param('i', $id_cita);
            $stmt->execute();
            $datos[$clave] = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }

    echo json_encode(['success' => true, 'data' => $datos], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('obtener_historia_laboratorio: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar la historia de laboratorio.']);
}
