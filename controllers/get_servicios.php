<?php
// controllers/get_servicios.php
require_once __DIR__ . '/../config/seguridad.php';
requireSesion();

// Importamos la conexión y el modelo que acabamos de crear
include __DIR__ . '/../config/Conexion.php';
include __DIR__ . '/../models/ServicioModel.php';

$servicioModel = new ServicioModel($conexion);

header('Content-Type: application/json');

try {
    $servicios = $servicioModel->obtenerTodos();

    // Mapear columnas para que el JS reciba los nombres esperados
    $serviciosMapeados = [];
    foreach ($servicios as $s) {
        $serviciosMapeados[] = [
            'id_servicio' => $s['id_servicio'],
            'servicio' => $s['nombre_servicio'],
            'disponibilidad' => $s['disponibilidad'] ?? 'Disponible',
            'cobertura' => $s['cobertura'] ?? '',
            'tipo_atencion' => $s['tipo_atencion'] ?? 'Presencial'
        ];
    }

    echo json_encode($serviciosMapeados);
} catch (Exception $e) {
    // Sin esto, un fallo de consulta se confunde con "el catálogo está vacío".
    error_log('get_servicios: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo consultar el catálogo de servicios.']);
}