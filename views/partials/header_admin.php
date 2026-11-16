<?php
// views/partials/header_admin.php
// Header unificado para vistas de administración (Fase 5).
// Uso: include __DIR__ . '/partials/header_admin.php';
// Requiere: sesión iniciada + funciones de seguridad cargadas.
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/rutas.php';
}
$rolActual = (int)($_SESSION['id_rol'] ?? $_SESSION['per_id'] ?? 0);
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$vistaActual = 'gestion_usuarios.php';
if ($rolActual === 3) {
    $vistaActual = 'solicitud.php';
} elseif ($rolActual === 4) {
    $vistaActual = 'supervisor.php';
} elseif ($rolActual === 5) {
    $vistaActual = 'dashboard.php';
}
if (in_array($paginaActual, ['bitacoras.php', 'citas.php', 'crear_usuario.php', 'dashboard.php', 'enviar_notificaciones.php', 'expedientes_medicos.php', 'gestion_afiliados.php', 'gestion_no_afiliados.php', 'gestion_especialidades.php', 'gestion_financiera.php', 'gestion_institucional.php', 'gestion_medica.php', 'gestion_medicos.php', 'gestion_patologias.php', 'gestion_roles.php', 'gestion_servicios.php', 'gestion_territorial.php', 'gestion_usuarios.php', 'mantenimiento.php', 'morbilidad.php', 'reportes.php', 'solicitud.php', 'supervisor.php'], true)) {
    $vistaActual = $paginaActual;
}
$etiquetaModo = 'Modo Acceso';
if ($rolActual === 5) {
    $etiquetaModo = 'Modo Admin';
} elseif ($rolActual === 4) {
    $etiquetaModo = 'Modo Supervisor';
} elseif ($rolActual === 3) {
    $etiquetaModo = 'Modo Moderador';
}
?>
<header class="header">
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top border-bottom">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div class="logo-area d-flex align-items-center">
                <img src="../img/Logo ex.png" alt="Logo IAA" height="40">
                <div class="logo-area-text ms-2">
                    <h1 class="h5 mb-0 fw-bold">IAA</h1>
                    <p class="small text-muted mb-0">Instituto Autónomo De Ayuda a los Funcionarios</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger p-2"><i class="bi bi-shield-lock-fill me-1"></i> <?php echo $etiquetaModo; ?></span>
                <?php if (in_array($rolActual, [3, 4, 5], true)): ?>
                <form action="../controllers/redireccionarRolController.php" method="POST" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="csrftoken" value="<?php echo csrf_token(); ?>">
                    <select name="destino" class="form-select form-select-sm fw-bold border-primary" onchange="this.form.submit()">
                        <option value="<?php echo htmlspecialchars($vistaActual, ENT_QUOTES); ?>" selected>Vista actual</option>
                        <?php if ($rolActual === 5): ?>
                        <option value="dashboard.php">Modo Admin</option>
                        <?php endif; ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
