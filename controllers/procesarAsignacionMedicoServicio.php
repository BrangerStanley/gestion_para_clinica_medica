<?php
// controllers/procesarAsignacionMedicoServicio.php
// POST
// Agrega o quita médicos de un servicio desde el modal de Servicio
// (views/gestion_servicios.php). Opera sobre la pivote medico_servicio.
//
// Campos: id_servicio, id_medico, accion = 'asignar' | 'quitar', password_admin

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/seguridad.php';
requireRol([5]);

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../config/verificar_admin.php';

$idServicio = isset($_POST['id_servicio']) ? intval($_POST['id_servicio']) : 0;
$idMedico   = isset($_POST['id_medico'])   ? intval($_POST['id_medico'])   : 0;
$accion     = isset($_POST['accion'])      ? trim($_POST['accion'])        : '';

if ($idServicio <= 0 || $idMedico <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Servicio o médico no válido.']);
    exit;
}
if (!in_array($accion, ['asignar', 'quitar'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;
}

// 🔒 Misma validación de contraseña que el resto del catálogo.
$password_admin = isset($_POST['password_admin']) ? trim($_POST['password_admin']) : '';
$authEstado = autorizarAccionAdmin($conexion, $password_admin);
if ($authEstado !== 'ok') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => mensajeErrorAutorizacionAdmin($authEstado)]);
    exit;
}

try {
    if ($accion === 'asignar') {
        // ON CONFLICT DO NOTHING: si ya estaba adscrito, no falla ni duplica (PK compuesta).
        $sql = "INSERT INTO medico_servicio (id_medico, id_servicio) VALUES (?, ?) ON CONFLICT DO NOTHING";
    } else {
        $sql = "DELETE FROM medico_servicio WHERE id_medico = ? AND id_servicio = ?";
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $idMedico, $idServicio);
    $stmt->execute();
    $filasAfectadas = $stmt->affected_rows;
    $stmt->close();

    // Al quitar, la columna legada id_servicio debe seguir apuntando a un
    // servicio válido de la pivote. Si se quitó el principal, se reasigna
    // al primero que quede; si no queda ninguno, queda NULL.
    if ($accion === 'quitar') {
        $stmt = $conexion->prepare(
            "SELECT id_servicio FROM medico_servicio WHERE id_medico = ? ORDER BY id_servicio ASC LIMIT 1"
        );
        $stmt->bind_param("i", $idMedico);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nuevoPrincipal = $fila ? (int)$fila['id_servicio'] : null;
        $stmt = $conexion->prepare("UPDATE medico SET id_servicio = ? WHERE id_medico = ?");
        $stmt->bind_param("ii", $nuevoPrincipal, $idMedico);
        $stmt->execute();
        $stmt->close();
    }

    // Con 0 filas afectadas no hubo cambio real: avisar en vez de fingir éxito.
    $huboCambio = $filasAfectadas > 0;
    $mensaje = $accion === 'asignar'
        ? ($huboCambio ? 'Médico asignado al servicio.' : 'El médico ya estaba asignado a este servicio.')
        : ($huboCambio ? 'Médico quitado del servicio.' : 'El médico no estaba asignado a este servicio.');

    if ($huboCambio) {
        require_once __DIR__ . '/../config/bitacora_helper.php';
        registrarBitacora(
            $conexion,
            'asignar_medico_servicio',
            ($accion === 'asignar' ? 'Asignado' : 'Quitado') . ' médico ID ' . $idMedico
                . ' ' . ($accion === 'asignar' ? 'a' : 'de') . ' servicio ID ' . $idServicio,
            'catalogo'
        );
    }

    echo json_encode([
        'success'      => true,
        'hubo_cambio'  => $huboCambio,
        'message'      => $mensaje,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('procesarAsignacionMedicoServicio: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => (int)$e->getCode() === 1452
            ? 'El médico o el servicio indicado ya no existe.'
            : 'Error de base de datos al asignar el médico.',
    ]);
}
?>
