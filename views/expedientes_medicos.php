<?php
require_once __DIR__ . '/../config/seguridad.php';
configurarSesionSegura();

$rol = (int)($_SESSION['id_rol'] ?? $_SESSION['per_id'] ?? 0);
if ($rol !== 5) {
    denegarAccesoVista();
}
exigirPermisoPagina('configurar_servicios');

include '../models/datos_de_usuario.php';
$tituloPagina = 'IAA - Expedientes médicos';
$cssExtraAdmin = ['css/expedientes_medicos.css'];
include __DIR__ . '/partials/head_admin.php';
include __DIR__ . '/partials/header_admin.php';
?>
<main class="d-flex expedientes-root">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
    <section class="flex-grow-1 p-4 mt-3 mx-3">
        <div class="container-fluid">
            <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="text-muted small">Gestión Médica / Expedientes</div>
                    <h1 class="h3 mb-2"><i class="bi bi-folder2-open text-primary me-2"></i>Expedientes médicos</h1>
                    <p class="text-muted mb-0">Consulta de las historias guardadas por los médicos, organizadas por servicio, especialidad y profesional. Esta vista es solo de lectura.</p>
                </div>
                <a class="btn btn-outline-secondary" href="gestion_medica.php">
                    <i class="bi bi-arrow-left me-1"></i>Cuadro médico
                </a>
            </header>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Periodo de consulta">
                        <button type="button" class="btn btn-outline-primary" data-periodo="hoy">Ver hoy</button>
                        <button type="button" class="btn btn-outline-primary" data-periodo="semana">Ver esta semana</button>
                        <button type="button" class="btn btn-outline-secondary" data-periodo="personalizado">Aplicar fechas</button>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="filtro_desde">Desde</label>
                            <input class="form-control" type="date" id="filtro_desde">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="filtro_hasta">Hasta</label>
                            <input class="form-control" type="date" id="filtro_hasta">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="filtro_servicio">Servicio</label>
                            <select class="form-select" id="filtro_servicio"><option value="">Todos</option></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="filtro_especialidad">Especialidad</label>
                            <select class="form-select" id="filtro_especialidad"><option value="">Todas</option></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="filtro_medico">Médico</label>
                            <select class="form-select" id="filtro_medico"><option value="">Todos</option></select>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-primary" id="btn_buscar_expedientes" aria-label="Buscar expedientes">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold" id="resumen_expedientes" aria-live="polite">Cargando expedientes…</div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_actualizar_expedientes">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </div>
            <div id="lista_expedientes" class="expedientes-lista" aria-live="polite"></div>
            <nav class="d-flex justify-content-center align-items-center gap-3 my-3" aria-label="Paginación de expedientes">
                <button type="button" class="btn btn-sm btn-outline-primary" id="pagina_anterior">Anterior</button>
                <span class="small text-muted" id="pagina_actual">Página 1</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="pagina_siguiente">Siguiente</button>
            </nav>
        </div>
    </section>
</main>

<div class="modal fade" id="modalExpediente" tabindex="-1" aria-labelledby="modalExpedienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h2 class="modal-title h5 mb-1" id="modalExpedienteLabel">Expediente médico</h2>
                    <div class="small" id="modalExpedienteSubtitulo">Consulta de solo lectura</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalle_expediente">
                <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Cargando expediente…</div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto"><i class="bi bi-lock me-1"></i>Vista de solo lectura</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script src="js/expedientes_medicos.js?v=1"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
