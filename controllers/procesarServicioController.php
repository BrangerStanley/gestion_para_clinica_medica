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
require_once __DIR__ . '/../models/ServicioModel.php';

$servicioModel = new ServicioModel($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = isset($_POST['id_servicio']) ? intval($_POST['id_servicio']) : 0;
    $servicio    = isset($_POST['servicio']) ? trim($_POST['servicio']) : '';
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
        if ($id_servicio > 0) {
            $resultado = false;
            $mensaje = 'Error al eliminar el servicio';
            try {
                $resultado = $servicioModel->eliminarServicio($id_servicio);
                if ($resultado) {
                    $mensaje = 'Servicio eliminado correctamente';
                } elseif ($servicioModel->ultimoError !== '') {
                    // H-15: el modelo ya ha comprobado los hijos.
                    $mensaje = $servicioModel->ultimoError;
                } elseif ((int)$conexion->errno === 1451) {
                    $mensaje = 'No se puede eliminar: el servicio tiene médicos asignados. Reasigne o elimine esos médicos primero.';
                }
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1451) {
                    $mensaje = 'No se puede eliminar: el servicio tiene médicos asignados. Reasigne o elimine esos médicos primero.';
                } else {
                    error_log('procesarServicioController delete: ' . $e->getMessage());
                }
            } catch (Throwable $e) {
                // PostgreSQL: SQLSTATE 23503 = violacion de clave foranea. Con
                // MySQL era 1451, pero aqui la excepcion es de PDO y el catch
                // de mysqli no la cubria: se escapaba como error 500.
                $codigo = (string)$e->getCode();
                if ($codigo === '23503' || stripos($e->getMessage(), 'foreign key') !== false) {
                    $resultado = false;
                    $mensaje = 'No se puede eliminar: hay datos asociados. Reasigne o elimine primero los datos dependientes.';
                } else {
                    error_log('procesarServicioController delete: ' . $e->getMessage());
                }
            }
            if ($resultado) {
                require_once __DIR__ . '/../config/bitacora_helper.php';
                registrarBitacora($conexion, 'crud_servicio', 'Servicio eliminado (ID ' . $id_servicio . ')', 'catalogo');
            }
            echo json_encode(['success' => $resultado, 'message' => $mensaje]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de servicio no válido']);
        }
        exit;
    }

    // Validar campo obligatorio
    if (empty($servicio)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del servicio es obligatorio']);
        exit;
    }

    $disponibilidad = isset($_POST['disponibilidad']) ? trim($_POST['disponibilidad']) : 'Disponible';
    $cobertura      = isset($_POST['cobertura']) && trim($_POST['cobertura']) !== '' ? trim($_POST['cobertura']) : null;
    $tipo_atencion  = isset($_POST['tipo_atencion']) ? trim($_POST['tipo_atencion']) : 'Presencial';

    try {
        if ($id_servicio > 0) {
            // Actualizar (5 argumentos: id, nombre, disponibilidad, cobertura, tipo_atencion)
            $resultado = $servicioModel->actualizarServicio($id_servicio, $servicio, $disponibilidad, $cobertura, $tipo_atencion);
            $mensaje = $resultado ? 'Servicio actualizado exitosamente' : 'Error al actualizar el servicio';
        } else {
            // Crear
            $resultado = $servicioModel->crearServicio($servicio, $disponibilidad, $cobertura, $tipo_atencion);
            $mensaje = $resultado ? 'Servicio creado exitosamente' : 'Error al crear el servicio';
        }
    } catch (mysqli_sql_exception $e) {
        // Sin esto, PHP 8 lanza la excepción y el JS recibe HTML de fatal en medio
        // de un Content-Type: application/json → el usuario no ve ningún mensaje.
        error_log('procesarServicioController guardar: ' . $e->getMessage());
        $resultado = false;
        $mensaje = (int)$e->getCode() === 1452
            ? 'El servicio indicado no existe.'
            : 'Error de base de datos al guardar el servicio. Revise el log.';
    }

    echo json_encode(['success' => $resultado, 'message' => $mensaje]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>