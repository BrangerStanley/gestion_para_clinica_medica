<?php
// controllers/get_estadisticas_cuadro.php
// GET ?tipo=servicios|especialidades|medicos|patologias
require_once __DIR__ . '/../config/seguridad.php';
requireSesion();

header('Content-Type: application/json');

include __DIR__ . '/../config/Conexion.php';
include __DIR__ . '/../models/EstadisticasCuadroModel.php';

$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
if (!in_array($tipo, ['servicios', 'especialidades', 'medicos', 'patologias'], true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo no válido']);
    exit;
}

try {
    $model = new EstadisticasCuadroModel($conexion);
    $data = $model->resumen($tipo);
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log('get_estadisticas_cuadro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al calcular estadísticas']);
}
