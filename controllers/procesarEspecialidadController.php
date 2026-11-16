<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/seguridad.php';
requireRol([5]);

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit;
}

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../config/verificar_admin.php';
require_once __DIR__ . '/../models/EspecialidadModel.php';

$espModel = new EspecialidadModel($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_especialidad = isset($_POST['id_especialidad']) ? intval($_POST['id_especialidad']) : 0;
    $id_servicio     = isset($_POST['id_servicio']) ? intval($_POST['id_servicio']) : 0;
    $especialidad    = isset($_POST['especialidad']) ? trim($_POST['especialidad']) : '';
    $precio          = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;
    $action          = isset($_POST['action']) ? $_POST['action'] : '';

    // 🔒 VALIDAR CONTRASEÑA DEL ADMINISTRADOR (o re-auth reciente en sesión)
    $password_admin = isset($_POST['password_admin']) ? trim($_POST['password_admin']) : '';
    $authEstado = autorizarAccionAdmin($conexion, $password_admin);
    if ($authEstado !== 'ok') {
        echo json_encode(['success' => false, 'message' => mensajeErrorAutorizacionAdmin($authEstado)]);
        exit;
    }

    // Si la acción es eliminar
    if ($action === 'delete') {
        if ($id_especialidad > 0) {
            $resultado = $espModel->eliminarEspecialidad($id_especialidad);
            if ($resultado) {
                require_once __DIR__ . '/../config/bitacora_helper.php';
                registrarBitacora($conexion, 'crud_especialidad', 'Especialidad eliminada (ID ' . $id_especialidad . ')', 'catalogo');
            }
            echo json_encode([
                'success' => $resultado, 
                'message' => $resultado ? 'Especialidad eliminada correctamente' : ($espModel->ultimoError !== '' ? $espModel->ultimoError : 'Error al eliminar')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de especialidad no válido']);
        }
        exit;
    }

    // Validaciones
    if (empty($especialidad) || $id_servicio <= 0 || $precio < 0) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
        exit;
    }

    if ($id_especialidad > 0) {
        // Actualizar
        $resultado = $espModel->actualizarEspecialidad($id_especialidad, $especialidad, $precio);
        $mensaje = $resultado ? 'Especialidad actualizada exitosamente' : 'Error al actualizar';
    } else {
        // Crear
        $resultado = $espModel->crearEspecialidad($id_servicio, $especialidad, $precio);
        $mensaje = $resultado ? 'Especialidad creada exitosamente' : 'Error al crear';
    }

    if ($resultado) {
        require_once __DIR__ . '/../config/bitacora_helper.php';
        registrarBitacora($conexion, 'crud_especialidad', $mensaje . ($id_especialidad > 0 ? ' (ID ' . $id_especialidad . ')' : ''), 'catalogo');
    }
    echo json_encode(['success' => $resultado, 'message' => $mensaje]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>