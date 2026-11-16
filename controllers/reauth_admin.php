<?php
// controllers/reauth_admin.php
// Ventana de re-autenticación del administrador: valida la contraseña y
// marca 15 minutos en los que no se vuelve a pedir (config/verificar_admin.php).
// Usado por views/js/gestion_usuarios.js (desbloqueo de datos institucionales)
// y por views/js/reauth_admin.js (estado vía GET).

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/verificar_admin.php';

if (session_status() === PHP_SESSION_NONE) {
    configurarSesionSegura();
}

// Estado de la ventana (compatibilidad con reauth_admin.js)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireSesion();
    echo json_encode(['valid' => reauthAdminActivo()]);
    exit;
}

try {
    requireSesion();

    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Token de seguridad inválido. Recargue la página e intente de nuevo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exigirRateLimitPorIp('reauth_admin', 5, 60);

    $clave = trim((string)($_POST['clave'] ?? $_POST['password_admin'] ?? ''));

    if ($clave === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Debe ingresar la contraseña de administrador.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($GLOBALS['conexion'])) {
        require_once __DIR__ . '/../config/Conexion.php';
    }

    if (!verificarClaveAdmin($conexion, $clave)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Contraseña de administrador incorrecta.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    marcarReauthAdmin();
    unset($_SESSION['_clave_admin_fallos'], $_SESSION['_clave_admin_bloqueo']);

    echo json_encode([
        'success' => true,
        'message' => 'Contraseña verificada.'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log("reauth_admin: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo verificar la contraseña. Intente de nuevo.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
