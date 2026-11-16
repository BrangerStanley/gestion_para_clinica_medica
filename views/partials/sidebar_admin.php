<?php
// views/partials/sidebar_admin.php
// Barra lateral del módulo de Gestión Médica (extracción del panel de
// administración de IAA). Misma lógica de marcado y de permisos que el
// sidebar original, reducida al alcance de este repositorio: Dashboard y el
// desplegable "Gestión Médica".
//
// Uso: include __DIR__ . '/partials/sidebar_admin.php'; dentro de <main class="d-flex">.
$paginaActualAdmin = basename($_SERVER['PHP_SELF'] ?? '');
$rolSidebar = (int)rolActual();
function ia_admin_activo($actual, $esperado) {
    return $actual === $esperado ? 'active' : '';
}

$verDashboard        = ($rolSidebar === 5);
$puedeConfigServicios = ($rolSidebar === 5) && tienePermiso('configurar_servicios');
$verCuadroMedico     = $puedeConfigServicios;
$verExpedientes      = $puedeConfigServicios;
$verServicios        = $puedeConfigServicios;
$verEspecialidades  = $puedeConfigServicios;
$verMedicos          = $puedeConfigServicios;
$verPatologias       = ($rolSidebar === 5) && tienePermiso('gestionar_patologias');
$verConfigServicios  = $verCuadroMedico || $verExpedientes || $verServicios || $verEspecialidades || $verMedicos;
?>
<div class="barra-lateral py-3" id="sidebar">
    <?php if ($verDashboard): ?>
    <a class="bl <?php echo ia_admin_activo($paginaActualAdmin, 'dashboard.php'); ?>" href="dashboard.php">Dashboard</a>
    <?php endif; ?>

    <?php if ($verConfigServicios || $verPatologias): ?>
    <div class="dropdown">
        <a class="bl dropdown-toggle <?php echo in_array($paginaActualAdmin, ['gestion_servicios.php', 'gestion_especialidades.php', 'gestion_medicos.php', 'gestion_patologias.php', 'gestion_medica.php', 'expedientes_medicos.php'], true) ? 'active' : ''; ?>" href="#" onclick="event.preventDefault(); var el=document.getElementById('dropdownGestionMedica'); el.classList.toggle('show'); el.style.display = el.classList.contains('show')?'block':'none';">
            <span>Gestión Médica</span>
        </a>
        <div class="dropdown-content" id="dropdownGestionMedica">
            <?php if ($verCuadroMedico): ?>
            <a href="gestion_medica.php">Cuadro Médico</a>
            <?php endif; ?>
            <?php if ($verExpedientes): ?>
            <a href="expedientes_medicos.php">Expedientes médicos (solo lectura)</a>
            <?php endif; ?>
            <?php if ($verServicios): ?>
            <a href="gestion_servicios.php">Servicios</a>
            <?php endif; ?>
            <?php if ($verEspecialidades): ?>
            <a href="gestion_especialidades.php">Especialidades</a>
            <?php endif; ?>
            <?php if ($verMedicos): ?>
            <a href="gestion_medicos.php">Médicos</a>
            <?php endif; ?>
            <?php if ($verPatologias): ?>
            <a href="gestion_patologias.php">Patologías (CIE-10)</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
