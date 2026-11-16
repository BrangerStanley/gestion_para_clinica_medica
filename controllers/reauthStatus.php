<?php
require_once __DIR__ . '/../config/seguridad.php';
if (session_status() === PHP_SESSION_NONE) {
    configurarSesionSegura();
}
require_once __DIR__ . '/../config/verificar_admin.php';

// Es un endpoint de solo lectura sobre el estado de re-autenticacion de la
// sesion actual: sin sesion no hay nada que consultar y no tiene sentido
// devolver un 200 con datos.
requireSesion();

header('Content-Type: application/json; charset=utf-8');

$activo = reauthAdminActivo();
$ts = (int)($_SESSION['_reauth_admin_at'] ?? 0);
$restante = $activo ? max(0, ($ts + REAUTH_ADMIN_TTL) - time()) : 0;

echo json_encode([
    'valid' => $activo,
    'expires_in' => $restante,
    'ttl' => REAUTH_ADMIN_TTL
]);
