<?php
require_once __DIR__ . '/../config/seguridad.php';
configurarSesionSegura();

// Verificación de seguridad: Solo Administradores (Rol 5)
$rol = isset($_SESSION['id_rol']) ? $_SESSION['id_rol'] : (isset($_SESSION['per_id']) ? $_SESSION['per_id'] : 0);
if ($rol != 5) {
    denegarAccesoVista();
}
exigirPermisoPagina('configurar_servicios');
require_once '../models/PermisoModel.php';
$permisoModel = new PermisoModel();
$roles = $permisoModel->obtenerRoles();
include '../models/datos_de_usuario.php';
$tituloPagina = 'IAA - Panel de Administrador';
include __DIR__ . '/partials/head_admin.php';
include __DIR__ . '/partials/header_admin.php';
?>
<main class="d-flex" style="min-height: calc(100vh - 80px);">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4 mt-3 ms-3 me-3">
        <div class="container-fluid px-2 py-3">

            <!-- ENCABEZADO -->
            <div class="ui-titulo">
                <div>
                    <div class="ui-miga">Catálogos / Gestión Médica</div>
                    <h2><i class="bi bi-briefcase-fill me-2" style="color: var(--ui-serv);"></i>Gestión de Servicios</h2>
                    <p class="ui-lema">Alta y edición del catálogo base de servicios, con el resumen de
                        especialidades, citas e ingresos que genera cada uno.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="ui-refresco" id="btn_refrescar" title="Recargar" aria-label="Recargar servicios">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-2 mb-3" id="kpis_servicios">
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
                                <div class="ui-kpi__etiqueta">Citas</div>
                                <div class="ui-kpi__valor" id="kpi_citas">—</div>
                                <div class="ui-kpi__sub" id="kpi_citas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-med"><i class="bi bi-check2-circle"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Atendidas</div>
                                <div class="ui-kpi__valor is-ok" id="kpi_atendidas">—</div>
                                <div class="ui-kpi__sub" id="kpi_atendidas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-dor"><i class="bi bi-calendar-check"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Programadas</div>
                                <div class="ui-kpi__valor is-dor" id="kpi_programadas">—</div>
                                <div class="ui-kpi__sub" id="kpi_programadas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico" style="background: rgba(220,53,69,.12); color:#dc3545;"><i class="bi bi-x-circle"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Canceladas</div>
                                <div class="ui-kpi__valor is-no" id="kpi_canceladas">—</div>
                                <div class="ui-kpi__sub" id="kpi_canceladas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-med"><i class="bi bi-cash-stack"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Ingresos</div>
                                <div class="ui-kpi__valor is-ok" id="kpi_ingresos">—</div>
                                <div class="ui-kpi__sub" id="kpi_ingresos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL DE SERVICIOS -->
            <div class="ui-panel">
                <div class="ui-panel__cabecera">
                    <span class="ui-ico"><i class="bi bi-briefcase-fill"></i></span>
                    <div class="ui-panel__txt">
                        <h3 class="ui-panel__titulo">Servicios</h3>
                        <div class="ui-panel__sub">Catálogo base del cuadro médico</div>
                    </div>
                    <span class="ui-contador" id="count_servicios" aria-live="polite">—</span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Exportar estadísticas">
                            <button class="btn btn-success" type="button" onclick="exportarEstadisticas('servicios','excel')" title="Exportar a Excel">
                                <i class="bi bi-file-earmark-excel"></i> Excel
                            </button>
                            <button class="btn btn-primary" type="button" onclick="exportarEstadisticas('servicios','csv')" title="Exportar a CSV">
                                <i class="bi bi-filetype-csv"></i> CSV
                            </button>
                            <button class="btn btn-danger" type="button" onclick="exportarEstadisticas('servicios','pdf')" title="Exportar a PDF">
                                <i class="bi bi-filetype-pdf"></i> PDF
                            </button>
                        </div>
                        <button class="ui-add" id="btn_add_servicio" title="Agregar Servicio" aria-label="Agregar servicio">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="ui-filtros">
                    <div class="ui-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_servicio" placeholder="Buscar servicio…" aria-label="Buscar servicio">
                    </div>
                </div>

                <div class="ui-cuerpo">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 7%;">ID</th>
                                <th>Servicio</th>
                                <th style="width: 8%;" class="text-center">Esp.</th>
                                <th style="width: 8%;" class="text-center">Citas</th>
                                <th style="width: 10%;" class="text-center">Atendidas</th>
                                <th style="width: 12%;" class="text-end">Ingresos</th>
                                <th style="width: 9%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_servicios"></tbody>
                    </table>
                </div>
                <div class="ui-pie"><span id="pie_servicios">Cargando…</span></div>
            </div>

        </div>
    </div>

</main>

<?php include __DIR__ . '/partials/modales_admin.php'; ?>

<!-- MODAL SERVICIO -->
<div class="modal fade" id="modalServicio" tabindex="-1" aria-labelledby="modalServicioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_servicio" onsubmit="guardarServicio(event)">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title h6" id="modalServicioLabel">
                        <i class="bi bi-briefcase-fill me-2"></i>Gestión de Servicio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_servicio" id="id_servicio_input">

                    <div class="mb-3">
                        <label for="nombre_servicio_input" class="form-label small fw-bold">Nombre del Servicio</label>
                        <input type="text" class="form-control form-control-sm" name="servicio" id="nombre_servicio_input" required placeholder="Ej. Odontología">
                    </div>

                    <div class="mb-3">
                        <label for="disponibilidad_input" class="form-label small fw-bold">Disponibilidad</label>
                        <input type="text" class="form-control form-control-sm" name="disponibilidad" id="disponibilidad_input" placeholder="Ej. Disponible">
                    </div>

                    <div class="mb-3">
                        <label for="cobertura_input" class="form-label small fw-bold">Cobertura</label>
                        <input type="text" class="form-control form-control-sm" name="cobertura" id="cobertura_input" placeholder="Ej. Consulta externa">
                    </div>

                    <div class="mb-3">
                        <label for="tipo_atencion_input" class="form-label small fw-bold">Tipo de Atención</label>
                        <input type="text" class="form-control form-control-sm" name="tipo_atencion" id="tipo_atencion_input" placeholder="Ej. Presencial">
                    </div>

                    <!-- MÉDICOS DEL SERVICIO (solo visible al editar) -->
                    <div id="panel_medicos_servicio" class="mt-4 d-none">
                        <hr>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label small fw-bold mb-0">Médicos del servicio</label>
                            <span class="badge bg-secondary" id="count_medicos_servicio">0</span>
                        </div>

                        <div id="lista_medicos_servicio" class="border rounded p-2 mb-3" style="max-height:160px; overflow-y:auto;">
                            <div class="text-muted small">Cargando…</div>
                        </div>

                        <label class="form-label small fw-bold">Agregar médico</label>
                        <div class="input-group input-group-sm mb-1">
                            <select id="select_agregar_medico" class="form-select form-select-sm">
                                <option value="">Seleccione un médico…</option>
                            </select>
                            <button type="button" class="btn btn-success" id="btn_asignar_medico" title="Asignar al servicio">
                                <i class="bi bi-person-plus"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Un médico puede atender varios servicios: agregarlo aquí no lo quita del servicio que ya tenía.
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" id="btn_eliminar_servicio" class="btn btn-danger btn-sm d-none" onclick="confirmarEliminarServicio()">
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

<script src="js/gestion_servicios.js?v=3"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
