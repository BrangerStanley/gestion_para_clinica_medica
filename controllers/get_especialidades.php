<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['ci_user'])) {
    echo json_encode([]);
    exit;
}

include __DIR__ . '/../config/Conexion.php';
include __DIR__ . '/../models/EspecialidadModel.php';

$id_servicio = isset($_GET['id_servicio']) ? intval($_GET['id_servicio']) : 0;
$espModel = new EspecialidadModel($conexion);

try {
    // id_servicio > 0 -> filtrar; sin filtro -> todas con nombre de servicio
    if ($id_servicio > 0) {
        $especialidades = $espModel->obtenerEspecialidadesPorServicio($id_servicio);
    } else {
        $especialidades = $espModel->obtenerTodas();
    }
    echo json_encode($especialidades);
} catch (Exception $e) {
    error_log('get_especialidades: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudieron consultar las especialidades.']);
}
