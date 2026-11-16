<?php
// views/dashboard.php
// Dashboard del módulo de Gestión Médica (extracción del panel de
// administración de IAA). Misma estructura visual que el dashboard original:
// cabecera de administración, barra lateral y tarjetas KPI.
require_once __DIR__ . '/../config/seguridad.php';
configurarSesionSegura();
$rol = isset($_SESSION['id_rol']) ? $_SESSION['id_rol'] : (isset($_SESSION['per_id']) ? $_SESSION['per_id'] : 0);
if ($rol != 5) {
    denegarAccesoVista();
}
require_once '../models/PermisoModel.php';
$permisoModel = new PermisoModel();
$roles = $permisoModel->obtenerRoles();
include '../models/datos_de_usuario.php';
$tituloPagina = 'IAA - Panel de Administración Médica';
include __DIR__ . '/partials/head_admin.php';
include __DIR__ . '/partials/header_admin.php';
?>
<main class="d-flex" style="min-height: calc(100vh - 80px);">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4 mt-3 ms-3 me-3">
        <div class="container-fluid px-2 py-3">

            <div class="ui-titulo">
                <div>
                    <div class="ui-miga">Panel / Administración</div>
                    <h2><i class="bi bi-hospital-fill me-2 text-primary"></i>Panel de Administración Médica</h2>
                    <p class="ui-lema">Configuración del cuadro médico, servicios, especialidades, médicos,
                        patologías CIE-10 y consulta de expedientes clínicos.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="ui-refresco" onclick="location.reload()" title="Recargar" aria-label="Recargar panel">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="gestion_medica.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-diagram-3 me-1"></i> Cuadro Médico
                    </a>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-briefcase-fill"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Servicios</div>
                                <div class="ui-kpi__valor is-serv" id="kpi_servicios">—</div>
                                <div class="ui-kpi__sub" id="kpi_servicios_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-esp"><i class="bi bi-award-fill"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Especialidades</div>
                                <div class="ui-kpi__valor" id="kpi_especialidades">—</div>
                                <div class="ui-kpi__sub" id="kpi_especialidades_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-med"><i class="bi bi-person-badge-fill"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Médicos</div>
                                <div class="ui-kpi__valor is-med" id="kpi_medicos">—</div>
                                <div class="ui-kpi__sub" id="kpi_medicos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-dor"><i class="bi bi-journal-medical"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Patologías</div>
                                <div class="ui-kpi__valor is-dor" id="kpi_patologias">—</div>
                                <div class="ui-kpi__sub" id="kpi_patologias_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ui-panel">
                <div class="ui-panel__cabecera">
                    <span class="ui-ico"><i class="bi bi-grid-1x2-fill"></i></span>
                    <div class="ui-panel__txt">
                        <h3 class="ui-panel__titulo">Módulos de gestión médica</h3>
                        <div class="ui-panel__sub">Seleccione un módulo para administrar su catálogo</div>
                    </div>
                    <span class="ui-contador">6 módulos</span>
                </div>
                <div class="ui-cuerpo p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="gestion_medica.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico"><i class="bi bi-diagram-3-fill"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Cuadro Médico</h6>
                                        <p class="ui-panel__sub mb-0">Servicios, especialidades y médicos vinculados en una sola vista.</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="expedientes_medicos.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico"><i class="bi bi-folder2-open"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Expedientes Médicos</h6>
                                        <p class="ui-panel__sub mb-0">Historial clínico y consultas registradas (solo lectura).</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="gestion_servicios.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico"><i class="bi bi-briefcase-fill"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Servicios</h6>
                                        <p class="ui-panel__sub mb-0">CRUD del catálogo base de servicios institucionales.</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="gestion_especialidades.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico is-esp"><i class="bi bi-award-fill"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Especialidades</h6>
                                        <p class="ui-panel__sub mb-0">CRUD de las especialidades médicas por servicio.</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="gestion_medicos.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico is-med"><i class="bi bi-person-badge-fill"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Médicos</h6>
                                        <p class="ui-panel__sub mb-0">CRUD del personal médico y sus servicios asignados.</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="gestion_patologias.php" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <span class="ui-ico is-dor"><i class="bi bi-journal-medical"></i></span>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Patologías (CIE-10)</h6>
                                        <p class="ui-panel__sub mb-0">CRUD del catálogo de diagnósticos oficiales.</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="ui-pie">
                    <span>Módulo extraído del sistema IAA para el repositorio de GitHub</span>
                    <span>PostgreSQL · PHP · Bootstrap 5</span>
                </div>
            </div>
        </div>
    </div>
</main>

<script nonce="<?php echo csp_nonce(); ?>">
(function () {
    function cargarKpis() {
        fetch('../controllers/get_estadisticas_cuadro.php?tipo=servicios')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                var d = data.estadisticas || data;
                if (d.total !== undefined) {
                    document.getElementById('kpi_servicios').textContent = d.total;
                    document.getElementById('kpi_servicios_sub').textContent = 'Registrados';
                }
            })
            .catch(function () {});
    }
    document.addEventListener('DOMContentLoaded', cargarKpis);
})();
</script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
