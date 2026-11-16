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
require_once __DIR__ . '/../models/MedicoModel.php';

$medicoModel = new MedicoModel($conexion);

// Traduce una excepción mysqli a un mensaje apto para el usuario, sin filtrar
// detalle técnico. Lo demás va al log.
function mensajeErrorMedico($e) {
    $codigo = (int)$e->getCode();
    if ($codigo === 1452) {
        return 'El servicio indicado no existe.';
    }
    if ($codigo === 1062) {
        return 'Ya existe un médico con esa cédula.';
    }
    if ($codigo === 1406) {
        return 'Algún dato excede el largo permitido en la base de datos.';
    }
    error_log('procesarMedicoController: ' . $e->getMessage());
    return 'Error de base de datos al guardar el médico. Revise el log.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_med      = isset($_POST['id_med']) ? intval($_POST['id_med']) : 0;
    // Soporta id_servicios[] (lista, desde gestion_medicos) o id_servicio
    // suelto (compatibilidad con gestion_medica y otros llamadores).
    $ids_servicios = [];
    if (isset($_POST['id_servicios']) && is_array($_POST['id_servicios'])) {
        $ids_servicios = array_values(array_filter(array_map('intval', $_POST['id_servicios']), function ($v) {
            return $v > 0;
        }));
    } elseif (isset($_POST['id_servicio']) && intval($_POST['id_servicio']) > 0) {
        $ids_servicios = [intval($_POST['id_servicio'])];
    }
    // La cédula NO es editable desde aquí: es la unión con la cuenta de usuario
    // (medico.nro_colegiado = usuario.ci_usuario). Cambiarla desde esta vista
    // desvincularía al médico de su login.
    $especialidad  = isset($_POST['especialidad']) ? trim($_POST['especialidad']) : '';
    $estatus_medico = isset($_POST['estatus_medico']) ? strtolower(trim($_POST['estatus_medico'])) : 'activo';
    if ($estatus_medico === '') {
        $estatus_medico = 'activo';
    }
    $action      = isset($_POST['action']) ? $_POST['action'] : '';
    
    // 🔒 VALIDAR CONTRASEÑA DEL ADMINISTRADOR (o re-auth reciente en sesión)
    $password_admin = isset($_POST['password_admin']) ? trim($_POST['password_admin']) : '';
    $authEstado = autorizarAccionAdmin($conexion, $password_admin);
    if ($authEstado !== 'ok') {
        echo json_encode(['success' => false, 'message' => mensajeErrorAutorizacionAdmin($authEstado)]);
        exit;
    }
    // Si la acción es eliminar
    if ($action === 'delete') {
        if ($id_med > 0) {
            try {
                $resultado = $medicoModel->eliminarMedico($id_med);
                if ($resultado) {
                    require_once __DIR__ . '/../config/bitacora_helper.php';
                    registrarBitacora($conexion, 'crud_medico', 'Médico eliminado (ID ' . $id_med . ')', 'catalogo');
                }
                echo json_encode([
                    'success' => $resultado, 
                    'message' => $resultado ? 'Médico eliminado correctamente' : 'Error al eliminar'
                ]);
            } catch (mysqli_sql_exception $e) {
                // 1451 = FK RESTRICT: hay citas que lo referencian.
                if ((int)$e->getCode() === 1451) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se puede eliminar: el médico tiene citas registradas. '
                                   . 'Póngalo en estado "inactivo" en lugar de eliminarlo.'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => mensajeErrorMedico($e)]);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de médico no válido']);
        }
        exit;
    }

    // ── ALTA: ya no se permite desde aquí ────────────────────────────────
    // Un médico nace con su cuenta de usuario (crear_usuario.php o cambio de
    // rol a 'medico' en gestion_usuarios). Ese flujo materializa la fila
    // medico automáticamente; esta vista solo la administra.
    if ($id_med <= 0) {
        echo json_encode([
            'success' => false,
            'code'    => 'alta_no_permitida',
            'message' => 'Los médicos se crean desde la gestión de usuarios, '
                       . 'generando su cuenta con rol "Médico". Desde aquí solo '
                       . 'se administra la ficha y los servicios asignados.',
        ]);
        exit;
    }

    // Validaciones de la ficha editable
    if (!in_array($estatus_medico, ['activo', 'inactivo'], true)) {
        echo json_encode(['success' => false, 'message' => 'Estatus no válido (activo/inactivo)']);
        exit;
    }

    // Actualizar ficha profesional + servicios (sin tocar la cédula)
    try {
        $resultado = $medicoModel->actualizarFicha($id_med, $especialidad, $estatus_medico, $ids_servicios);
        $mensaje = $resultado ? 'Ficha del médico actualizada' : 'No hubo cambios que guardar';
    } catch (mysqli_sql_exception $e) {
        $resultado = false;
        $mensaje = mensajeErrorMedico($e);
    }

    if ($resultado) {
        require_once __DIR__ . '/../config/bitacora_helper.php';
        registrarBitacora(
            $conexion,
            'crud_medico',
            'Ficha actualizada (ID ' . $id_med . ')'
                . ($especialidad !== '' ? ' - Especialidad: ' . $especialidad : '')
                . ' - Estatus: ' . $estatus_medico
                . ' - Servicios [' . implode(',', $ids_servicios) . ']',
            'catalogo'
        );
    }
    echo json_encode(['success' => $resultado, 'message' => $mensaje]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>