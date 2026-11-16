<?php
// controllers/buscar_patologias_catalogo.php
// GET ?buscar=texto
// Búsqueda deDiagnósticos en el catálogo CIE-10 para el personal médico.
// Es SOLO lectura: el alta/baja del catálogo la lleva get_patologias.php /
// procesarPatologiaController.php, que exigen el permiso 'gestionar_patologias'.

ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/seguridad.php';
requireSesion();

// Médicos (6), supervisores (4) y administradores (5).
$rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;
if (!in_array($rol, [4, 5, 6], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

require_once __DIR__ . '/../config/Conexion.php';

if (!($conexion instanceof IaaConnection)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

$buscar    = isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : '';
$porPagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 20;
$porPagina = max(1, min(50, $porPagina));

if (mb_strlen($buscar, 'UTF-8') < 2) {
    echo json_encode(['success' => true, 'data' => [], 'total' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Coincide por nombre de patología o por código CIE-10.
    $stmt = $conexion->prepare(
        "SELECT p.id_patologia, p.patologia, p.codigo_cie10
         FROM patologia p
         WHERE p.patologia LIKE ?
            OR (p.codigo_cie10 IS NOT NULL AND p.codigo_cie10 LIKE ?)
         ORDER BY
            CASE
                WHEN UPPER(p.codigo_cie10) = UPPER(?) THEN 0
                WHEN UPPER(p.patologia) LIKE UPPER(?) THEN 1
                ELSE 2
            END,
            p.patologia ASC
         LIMIT ?"
    );
    $like = '%' . $buscar . '%';
    $prefijo = $buscar . '%';
    $stmt->bind_param('ssssi', $like, $like, $buscar, $prefijo, $porPagina);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];
    while ($fila = $res->fetch_assoc()) {
        $data[] = [
            'id_patologia' => (int)$fila['id_patologia'],
            'patologia'    => $fila['patologia'],
            'codigo_cie10' => (string)($fila['codigo_cie10'] ?? ''),
        ];
    }
    $res->free();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'total'   => count($data),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    error_log('buscar_patologias_catalogo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al buscar patologías.']);
}