<?php
// config/modo_demo.php
// ---------------------------------------------------------------------------
// MODO DEMOSTRACION
//
// El modo demo concede acceso de administrador sin login y permite escribir.
// Permanece desactivado salvo activacion explicita en un entorno local.
//
// Solo afecta a la navegacion del panel: los modelos, controladores,
// validaciones, CSRF y el acceso a datos siguen siendo los originales.
//
// No habilitarlo en una instalacion accesible desde una red.
// ---------------------------------------------------------------------------

if (!defined('IAA_MODO_DEMO')) {
    $valorEnv = function_exists('iaa_env_valor') ? iaa_env_valor('IAA_MODO_DEMO', '0') : '0';
    $solicitado = trim((string)$valorEnv) === '1';

    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = (string)(parse_url('http://' . $host, PHP_URL_HOST) ?: '');
    $host = trim($host, '[]');
    $hostLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $clienteLocal = in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    $permitido = $solicitado && $hostLocal && $clienteLocal;

    if ($solicitado && !$permitido) {
        error_log('IAA_MODO_DEMO ignorado: solo se permite desde localhost.');
    }

    define('IAA_MODO_DEMO', $permitido);
}

/**
 * Inyecta la identidad del administrador de demostracion en la sesion.
 * Se invoca desde configurarSesionSegura(), es decir, antes de que las vistas
 * comprueben $_SESSION['ci_user'].
 */
function demoInyectarSesion()
{
    if (!IAA_MODO_DEMO) {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!empty($_SESSION['ci_user'])) {
        return;
    }

    $_SESSION['ci_user']     = 'DEMO-MEDICA';
    $_SESSION['id_user']     = 0;
    $_SESSION['usuario_id']  = 0;
    $_SESSION['id_rol']      = 5;
    $_SESSION['per_id']      = 5;
    $_SESSION['nombre_user'] = 'Administrador Demo';
    $_SESSION['permisos']     = null;
    $_SESSION['permisos_ids'] = null;
}
