<?php
// config/verificar_admin.php
// Función reutilizable para verificar contraseña de administrador
// + ventana de re-autenticación (no pedir la clave en cada cambio)

if (!defined('REAUTH_ADMIN_TTL')) {
    // Segundos que se acepta una acción sin volver a pedir la clave
    define('REAUTH_ADMIN_TTL', 900); // 15 minutos
}

function reauthAdminActivo()
{
    if (defined('IAA_MODO_DEMO') && IAA_MODO_DEMO) {
        return true;
    }
    $ts = (int)($_SESSION['_reauth_admin_at'] ?? 0);
    return $ts > 0 && (time() - $ts) < REAUTH_ADMIN_TTL;
}

function marcarReauthAdmin()
{
    $_SESSION['_reauth_admin_at'] = time();
}

function limpiarReauthAdmin()
{
    unset($_SESSION['_reauth_admin_at']);
}

function verificarPasswordAdmin($conexion, $ci_admin, $password_admin) {
    $sql = "SELECT u.contrasena 
            FROM usuario u
            INNER JOIN persona p ON u.id_persona = p.id_persona
            INNER JOIN rol r ON r.id = u.id_rol
            WHERE u.ci_usuario = ? AND r.nombre = 'administrador'
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $ci_admin);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return password_verify($password_admin, $row['contrasena']);
    }
    $stmt->close();
    return false;
}

/**
 * Autoriza una acción de administrador.
 * - Si viene clave: la valida y, si es correcta, marca la ventana de re-auth.
 * - Si no viene clave: solo la acepta si hay re-auth reciente en la sesión.
 *
 * @return string 'ok' | 'sin_clave' | 'clave_incorrecta'
 */
function autorizarAccionAdmin($conexion, $password_admin)
{
    if (defined('IAA_MODO_DEMO') && IAA_MODO_DEMO) {
        return 'ok';
    }
    $password_admin = trim((string)$password_admin);

    if ($password_admin !== '') {
        $ci = $_SESSION['ci_user'] ?? '';
        if ($ci === '' || !verificarPasswordAdmin($conexion, $ci, $password_admin)) {
            return 'clave_incorrecta';
        }
        marcarReauthAdmin();
        return 'ok';
    }

    return reauthAdminActivo() ? 'ok' : 'sin_clave';
}

function mensajeErrorAutorizacionAdmin($estado)
{
    if ($estado === 'clave_incorrecta') {
        return 'Contraseña de administrador incorrecta';
    }
    return 'Debe ingresar la contraseña de administrador para continuar';
}
