<?php
// controllers/get_medicos_servicio.php
// GET ?id_servicio=N
//
// Alimenta la lista de médicos del modal de Servicio en
// views/gestion_servicios.php. Devuelve:
//   - asignados: médicos ya adscritos a ese servicio (vinculo en la pivote)
//   - disponibles: médicos activos que NO están en ese servicio, para poder
//     agregarlos. En una relación N <-> M no hay "médicos de otro servicio"
//     excluyentes: uno puede atender varios.
//
// Salida: { success, data: { asignados: [...], disponibles: [...] } }

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/seguridad.php';
requireRol([5]);

require_once __DIR__ . '/../config/Conexion.php';

$idServicio = isset($_GET['id_servicio']) ? intval($_GET['id_servicio']) : 0;
if ($idServicio <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'id_servicio no válido.']);
    exit;
}

try {
    // Médicos YA adscritos a este servicio. El nombre se resuelve desde
    // persona (especialidad es la especialidad real, no el nombre).
    $sqlAsignados = "SELECT m.id_medico, m.nro_colegiado, m.especialidad,
                            m.estatus_medico, p.nombre AS nombre_persona
                     FROM medico_servicio ms
                     INNER JOIN medico m ON m.id_medico = ms.id_medico
                     LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
                     LEFT JOIN persona p ON p.id_persona = u.id_persona
                     WHERE ms.id_servicio = ?
                     ORDER BY p.nombre ASC, m.nro_colegiado ASC";
    $stmt = $conexion->prepare($sqlAsignados);
    $stmt->bind_param("i", $idServicio);
    $stmt->execute();
    $asignados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Médicos activos que faltan por adscribir.
    $sqlDisponibles = "SELECT m.id_medico, m.nro_colegiado, m.especialidad,
                              m.estatus_medico, p.nombre AS nombre_persona
                       FROM medico m
                       LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
                       LEFT JOIN persona p ON p.id_persona = u.id_persona
                       WHERE m.estatus_medico = 'activo'
                         AND NOT EXISTS (SELECT 1 FROM medico_servicio ms
                                         WHERE ms.id_medico = m.id_medico
                                           AND ms.id_servicio = ?)
                       ORDER BY p.nombre ASC, m.nro_colegiado ASC";
    $stmt = $conexion->prepare($sqlDisponibles);
    $stmt->bind_param("i", $idServicio);
    $stmt->execute();
    $disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Mapear a los nombres que usa el JS. El nombre viene de persona
    // (persona.nombre); 'especialidad' se expone aparte, ya que es la
    // especialidad real y ya no guarda el nombre del médico.
    $mapear = function ($filas) {
        $out = [];
        foreach ($filas as $f) {
            $out[] = [
                'id_med'        => (int)$f['id_medico'],
                'ci_med'        => $f['nro_colegiado'] ?? '',
                'nombre_med'    => trim((string)($f['nombre_persona'] ?? '')),
                'especialidad'  => trim((string)($f['especialidad'] ?? '')),
                'estatus_medico' => $f['estatus_medico'] ?? 'activo',
            ];
        }
        return $out;
    };

    echo json_encode([
        'success' => true,
        'data' => [
            'asignados'   => $mapear($asignados),
            'disponibles' => $mapear($disponibles),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_medicos_servicio: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudieron consultar los médicos del servicio.']);
}
?>
