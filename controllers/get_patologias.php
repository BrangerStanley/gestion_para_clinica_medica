<?php
// controllers/get_patologias.php
// GET ?buscar=&pagina=&por_pagina=
// Lista paginada del catalogo de patologias CIE-10.
// Salida: { success, data:[...], total, pagina, por_pagina, total_paginas }

ob_start();

require_once __DIR__ . '/../config/seguridad.php';
requireSesion();
requirePermiso('gestionar_patologias');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/Conexion.php';
    if (ob_get_length()) {
        ob_clean();
    }

    require_once __DIR__ . '/../models/PatologiaModel.php';

    $buscar     = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
    $pagina     = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    $porPagina  = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 50;

    $model = new PatologiaModel($conexion);
    $filas = $model->obtenerTodos($buscar, $pagina, $porPagina);
    $total = $model->contarTodas($buscar);

    $porPagina = min(200, max(1, $porPagina));
    $pagina     = max(1, $pagina);

    $data = [];
    foreach ($filas as $f) {
        $data[] = [
            'id_patologia' => intval($f['id_patologia']),
            'patologia'    => $f['patologia'],
            'codigo_cie10' => $f['codigo_cie10'] !== null ? $f['codigo_cie10'] : '',
            'usos'         => intval($f['usos']),
        ];
    }

    echo json_encode([
        'success'      => true,
        'data'         => $data,
        'total'        => $total,
        'pagina'       => $pagina,
        'por_pagina'   => $porPagina,
        'total_paginas' => $porPagina > 0 ? (int)ceil($total / $porPagina) : 0,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    error_log('get_patologias: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar el catalogo de patologias',
    ], JSON_UNESCAPED_UNICODE);
}
?>
