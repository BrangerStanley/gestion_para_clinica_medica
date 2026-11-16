<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['ci_user'])) {
    echo json_encode([]);
    exit;
}

include __DIR__ . '/../config/Conexion.php';
include __DIR__ . '/../models/MedicoModel.php';

$medicoModel = new MedicoModel($conexion);

// Filtros soportados: id_servicio, buscar (cedula/nombre), estatus (activo/inactivo)
$filtros = [];
$idServicio = isset($_GET['id_servicio']) ? intval($_GET['id_servicio']) : 0;
if ($idServicio > 0) {
    $filtros['id_servicio'] = $idServicio;
}
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
if ($buscar !== '') {
    $filtros['buscar'] = $buscar;
}
$estatus = isset($_GET['estatus']) ? trim($_GET['estatus']) : '';
if ($estatus !== '' && in_array($estatus, ['activo', 'inactivo'], true)) {
    $filtros['estatus_medico'] = $estatus;
}

try {
    $medicos = $medicoModel->obtenerTodos($filtros);

    // Mapear columnas para que el JS reciba los nombres esperados.
    //
    // RECONVERSIÓN DE SEMÁNTICA:
    //   nro_colegiado = cédula  (se conserva: es la unión con la cuenta)
    //   especialidad  = especialidad REAL del médico (antes guardaba el nombre)
    //   nombre        = persona.nombre, resuelto desde la cuenta por la cédula
    //
    // 'id_servicio'/'servicio' quedan como servicio PRINCIPAL (compatibilidad),
    // 'id_servicios'/'servicios' traen la lista completa desde la pivote.
    $medicosMapeados = [];
    foreach ($medicos as $m) {
        $idsServicios = isset($m['servicios_ids']) && is_array($m['servicios_ids'])
            ? $m['servicios_ids']
            : (isset($m['id_servicio']) && $m['id_servicio'] !== null ? [intval($m['id_servicio'])] : []);

        // Sin cuenta => no hay nombre. No se inventa uno: hay que crearle la cuenta.
        $nombreResuelto = trim((string)($m['nombre_persona'] ?? ''));
        $tieneCuenta = !empty($m['tiene_cuenta']);

        $medicosMapeados[] = [
            'id_med'        => $m['id_medico'],
            'ci_med'        => $m['nro_colegiado'] ?? '',
            'nombre_med'    => $nombreResuelto,
            'especialidad'  => trim((string)($m['especialidad'] ?? '')),
            'estatus_medico'=> $m['estatus_medico'] ?? 'activo',
            'id_servicio'   => isset($m['id_servicio']) && $m['id_servicio'] !== null ? intval($m['id_servicio']) : null,
            'servicio'      => $m['nombre_servicio'] ?? '',
            'id_servicios'  => $idsServicios,
            'servicios'     => $m['servicios_nombres'] ?? ($m['nombre_servicio'] ?? ''),
            'tiene_cuenta'  => $tieneCuenta,
        ];
    }

    echo json_encode($medicosMapeados);
} catch (Exception $e) {
    // Sin este log, "error de consulta" y "no hay médicos" se ven igual para el
    // operador: siempre recibe [].
    error_log('get_medicos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudieron consultar los médicos.']);
}
