<?php
// controllers/procesarPatologiaController.php
// POST -> crear / actualizar / eliminar (action=delete) una patologia del catalogo.
// Exige: sesion + rol administrador + token CSRF + re-autenticacion del admin.

ob_start();

require_once __DIR__ . '/../config/seguridad.php';
requireRol([5]);
requirePermiso('gestionar_patologias');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../config/verificar_admin.php';
require_once __DIR__ . '/../models/PatologiaModel.php';
require_once __DIR__ . '/../config/bitacora_helper.php';

if (ob_get_length()) {
    ob_clean();
}

$model = new PatologiaModel($conexion);

$id_patologia  = isset($_POST['id_patologia']) ? intval($_POST['id_patologia']) : 0;
$patologia     = isset($_POST['patologia']) ? trim($_POST['patologia']) : '';
$codigo_cie10  = isset($_POST['codigo_cie10']) ? strtoupper(trim($_POST['codigo_cie10'])) : '';
$action        = isset($_POST['action']) ? trim($_POST['action']) : '';

// --- Autorizacion del administrador (re-auth 15 min) ---
$password_admin = isset($_POST['password_admin']) ? trim($_POST['password_admin']) : '';
$authEstado = autorizarAccionAdmin($conexion, $password_admin);
if ($authEstado !== 'ok') {
    echo json_encode([
        'success' => false,
        'message' => mensajeErrorAutorizacionAdmin($authEstado),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function respuestaPatologia($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================== ELIMINAR =====================
if ($action === 'delete') {
    if ($id_patologia <= 0) {
        respuestaPatologia(false, 'ID de patologia no valido');
    }

    $existente = $model->obtenerPorId($id_patologia);
    if (!$existente) {
        respuestaPatologia(false, 'La patologia indicada no existe');
    }

    // persona_patologia tiene ON DELETE CASCADE: borrar una patologia en uso
    // eliminaria silenciosamente antecedentes clinicos de empleados.
    if ($model->estaEnUso($id_patologia)) {
        $personas = $model->contarPersonasConPatologia($id_patologia);
        respuestaPatologia(
            false,
            'No se puede eliminar: la patologia esta registrada en ' . $personas .
            ' historial(es) clinico(s). Desasignela antes de eliminarla.'
        );
    }

    $ok = $model->eliminarPatologia($id_patologia);
    if ($ok) {
        registrarBitacora(
            $conexion,
            'crud_patologia',
            'Patologia eliminada: ' . $existente['patologia'] .
            ($existente['codigo_cie10'] ? ' (CIE-10 ' . $existente['codigo_cie10'] . ')' : ''),
            'catalogo'
        );
    }
    respuestaPatologia($ok, $ok ? 'Patologia eliminada correctamente' : 'Error al eliminar la patologia');
}

// ===================== CREAR / ACTUALIZAR =====================
if ($patologia === '') {
    respuestaPatologia(false, 'El nombre de la patologia es obligatorio');
}

if (mb_strlen($patologia) > 255) {
    respuestaPatologia(false, 'El nombre de la patologia no puede exceder 255 caracteres');
}

// CIE-10: letra + 2 digitos, opcionalmente un punto y hasta 4 caracteres mas.
// Ejemplos validos: I10, E11.9, N18.5, S82.9
if ($codigo_cie10 !== '' && !preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', $codigo_cie10)) {
    respuestaPatologia(false, 'El codigo CIE-10 no tiene un formato valido (ejemplos: I10, E11.9, N18.5)');
}

if ($codigo_cie10 !== '' && $model->existeCodigoCIE10($codigo_cie10, $id_patologia)) {
    respuestaPatologia(false, 'Ya existe otra patologia con el codigo CIE-10 ' . $codigo_cie10);
}

$codigoSql = ($codigo_cie10 === '') ? null : $codigo_cie10;

if ($id_patologia > 0) {
    $existente = $model->obtenerPorId($id_patologia);
    if (!$existente) {
        respuestaPatologia(false, 'La patologia que intenta editar no existe');
    }

    $ok = $model->actualizarPatologia($id_patologia, $patologia, $codigoSql);
    $verbo = 'actualizada';
} else {
    $nuevoId = $model->crearPatologia($patologia, $codigoSql);
    $ok = ($nuevoId !== null);
    $id_patologia = $nuevoId;
    $verbo = 'creada';
}

if ($ok) {
    registrarBitacora(
        $conexion,
        'crud_patologia',
        'Patologia ' . $verbo . ': ' . $patologia .
        ($codigoSql ? ' (CIE-10 ' . $codigoSql . ')' : '') .
        ' [ID ' . intval($id_patologia) . ']',
        'catalogo'
    );
}

respuestaPatologia(
    $ok,
    $ok ? 'Patologia ' . $verbo . ' exitosamente' : 'Error al guardar la patologia'
);
?>
