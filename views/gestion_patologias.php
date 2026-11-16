<?php
// views/gestion_patologias.php
// Administracion del catalogo de patologias CIE-10.
// Acceso exclusivo del administrador (rol 5) con permiso 'gestionar_patologias'.
require_once __DIR__ . '/../config/seguridad.php';
configurarSesionSegura();


if (rolActual() != 5) {
    denegarAccesoVista();
}
exigirPermisoPagina('gestionar_patologias');

include __DIR__ . '/../models/datos_de_usuario.php';

$tituloPagina = 'IAA - Catalogo de Patologias';
include __DIR__ . '/partials/head_admin.php';
include __DIR__ . '/partials/header_admin.php';
?>
<main class="d-flex" style="min-height: calc(100vh - 80px);">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4 mt-3 ms-3 me-3">
        <div class="container-fluid px-2 py-3">

            <!-- ENCABEZADO -->
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h2 class="h6 mb-0 fw-bold text-secondary text-uppercase">
                    <i class="bi bi-clipboard2-pulse-fill me-2"></i>Gestión de Patologías (CIE-10)
                </h2>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            <!-- KPIs -->
            <div class="row g-2 mb-3" id="kpis_patologias">
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 bg-white py-2 px-3">
                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">En catálogo</div>
                        <div class="h5 mb-0 fw-bold" id="kpi_total">—</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 bg-white py-2 px-3">
                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">Con CIE-10</div>
                        <div class="h5 mb-0 fw-bold text-primary" id="kpi_con_codigo">—</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 bg-white py-2 px-3">
                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">Sin CIE-10</div>
                        <div class="h5 mb-0 fw-bold text-warning" id="kpi_sin_codigo">—</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 bg-white py-2 px-3">
                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">En uso</div>
                        <div class="h5 mb-0 fw-bold text-success" id="kpi_en_uso">—</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 bg-white py-2 px-3">
                        <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">Asignaciones</div>
                        <div class="h5 mb-0 fw-bold text-info" id="kpi_asignaciones">—</div>
                    </div>
                </div>
            </div>

            <!-- TABLA PATOLOGIAS -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1 flex-wrap gap-2">
                    <span class="fw-bold text-dark text-uppercase small">
                        <i class="bi bi-clipboard2-pulse text-primary me-1"></i>Catálogo de Patologías
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Exportar catalogo">
                            <button class="btn btn-success" type="button" onclick="exportarCatalogoPatologias('excel')" title="Exportar a Excel">
                                <i class="bi bi-file-earmark-excel"></i> Excel
                            </button>
                            <button class="btn btn-primary" type="button" onclick="exportarCatalogoPatologias('csv')" title="Exportar a CSV">
                                <i class="bi bi-filetype-csv"></i> CSV
                            </button>
                            <button class="btn btn-danger" type="button" onclick="exportarCatalogoPatologias('pdf')" title="Exportar a PDF">
                                <i class="bi bi-filetype-pdf"></i> PDF
                            </button>
                        </div>
                        <div class="input-group input-group-sm" style="width: 320px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="buscar_patologia_input" class="form-control"
                                   placeholder="Buscar por nombre o código CIE-10..."
                                   autocomplete="off">
                        </div>
                        <button class="btn btn-primary btn-sm px-2 py-0" id="btn_add_patologia" title="Agregar Patología">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="py-2 px-2" style="width: 6%;">ID</th>
                                <th class="py-2 px-2" style="width: 14%;">CIE-10</th>
                                <th class="py-2 px-2">Patología</th>
                                <th class="py-2 px-2 text-center" style="width: 12%;">Historiales</th>
                                <th class="py-2 px-2 text-center" style="width: 10%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_patologias">
                            <tr>
                                <td colspan="5" class="text-center text-muted small py-3">Cargando catálogo...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACION -->
                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                    <span class="small text-muted" id="info_paginacion">—</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Paginacion">
                        <button class="btn btn-outline-secondary" id="btn_pagina_anterior" type="button" disabled>
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary" id="btn_pagina_actual" type="button" disabled>
                            1
                        </button>
                        <button class="btn btn-outline-secondary" id="btn_pagina_siguiente" type="button" disabled>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/modales_admin.php'; ?>

<!-- MODAL PATOLOGIA -->
<div class="modal fade" id="modalPatologia" tabindex="-1" aria-labelledby="modalPatologiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_patologia" onsubmit="guardarPatologia(event)">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title h6" id="modalPatologiaLabel">
                        <i class="bi bi-clipboard2-pulse-fill me-2"></i>Gestión de Patología
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_patologia" id="id_patologia_input">

                    <div class="mb-3">
                        <label for="patologia_input" class="form-label small fw-bold">
                            Nombre de la patología <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" name="patologia" id="patologia_input"
                               required maxlength="255" placeholder="Ej. Hipertension esencial (primaria)">
                    </div>

                    <div class="mb-2">
                        <label for="codigo_cie10_input" class="form-label small fw-bold">Código CIE-10</label>
                        <input type="text" class="form-control form-control-sm" name="codigo_cie10" id="codigo_cie10_input"
                               maxlength="20" placeholder="Ej. I10, E11.9, N18.5">
                        <div class="form-text small">
                            Formato: letra + 2 dígitos, opcionalmente un punto y hasta 4 caracteres más.
                        </div>
                    </div>

                    <div id="info_usos_patologia" class="small text-muted d-none">
                        <i class="bi bi-info-circle me-1"></i>
                        Esta patología está registrada en <span id="info_usos_cantidad">0</span> historial(es) clínico(s),
                        por lo que no podrá eliminarse.
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" id="btn_eliminar_patologia" class="btn btn-danger btn-sm d-none" onclick="confirmarEliminarPatologia()">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-save me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/gestion_patologias.js?v=1"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
