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
                    <h2><i class="bi bi-person-badge-fill me-2" style="color:#198754;"></i>Gestión de Médicos</h2>
                    <p class="ui-lema">Profesionales registrados en el sistema. Se crean al generar su
                        <strong>cuenta con rol Médico</strong>; aquí solo se administra su ficha y los
                        servicios que atienden.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="ui-refresco" id="btn_refrescar" title="Recargar" aria-label="Recargar médicos">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>
            <!-- KPIs -->
            <div class="row g-2 mb-3" id="kpis_medicos">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-med"><i class="bi bi-person-badge-fill"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Médicos</div>
                                <div class="ui-kpi__valor" id="kpi_medicos">—</div>
                                <div class="ui-kpi__sub" id="kpi_medicos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-person-check"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Activos</div>
                                <div class="ui-kpi__valor" id="kpi_activos">—</div>
                                <div class="ui-kpi__sub" id="kpi_activos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico is-esp"><i class="bi bi-calendar-week"></i></span>
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
                                <div class="ui-kpi__valor" id="kpi_atendidas">—</div>
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
                                <div class="ui-kpi__valor" id="kpi_programadas">—</div>
                                <div class="ui-kpi__sub" id="kpi_programadas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-x-circle"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Canceladas</div>
                                <div class="ui-kpi__valor" id="kpi_canceladas">—</div>
                                <div class="ui-kpi__sub" id="kpi_canceladas_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL DE MÉDICOS -->
            <div class="ui-panel">
                <div class="ui-panel__cabecera">
                    <span class="ui-ico is-med"><i class="bi bi-person-badge-fill"></i></span>
                    <div class="ui-panel__txt">
                        <h3 class="ui-panel__titulo">Médicos</h3>
                        <div class="ui-panel__sub" id="sub_filtro_medicos">Todos los médicos</div>
                    </div>
                    <span class="ui-contador is-med" id="count_medicos" aria-live="polite">—</span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Exportar estadísticas">
                            <button class="btn btn-success" type="button" onclick="exportarEstadisticas('medicos','excel')" title="Exportar a Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </button>
                            <button class="btn btn-primary" type="button" onclick="exportarEstadisticas('medicos','csv')" title="Exportar a CSV">
                                <i class="bi bi-filetype-csv"></i> CSV
                            </button>
                            <button class="btn btn-danger" type="button" onclick="exportarEstadisticas('medicos','pdf')" title="Exportar a PDF">
                                <i class="bi bi-filetype-pdf"></i> PDF
                            </button>
                        </div>
                        <a href="crear_usuario.php" class="ui-add is-med" title="Crear cuenta de médico"
                           aria-label="Crear cuenta de médico (la ficha profesional se genera automáticamente)">
                            <i class="bi bi-person-plus"></i>
                        </a>
                    </div>
                </div>

                <div class="ui-filtros">
                    <div class="ui-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_medico" placeholder="Buscar por cédula o nombre…" aria-label="Buscar médico">
                    </div>
                    <label for="select_filtrar_servicio" class="form-label mb-0">Servicio</label>
                    <select id="select_filtrar_servicio" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Todos los servicios</option>
                    </select>
                    <label for="select_filtrar_estatus" class="form-label mb-0">Estatus</label>
                    <select id="select_filtrar_estatus" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Todos los estatus</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>
                </div>

                <div class="ui-cuerpo">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">ID</th>
                                <th style="width: 11%;">Cédula</th>
                                <th>Médico</th>
                                <th style="width: 12%;">Especialidad</th>
                                <th>Servicios</th>
                                <th style="width: 8%;" class="text-center">Citas</th>
                                <th style="width: 9%;" class="text-center">Atendidas</th>
                                <th style="width: 8%;" class="text-center">Próximas</th>
                                <th style="width: 9%;">Estatus</th>
                                <th style="width: 9%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_medicos"></tbody>
                    </table>
                </div>
                <div class="ui-pie"><span id="pie_medicos">Cargando…</span></div>
            </div>

        </div>
    </div>

</main>

<?php include __DIR__ . '/partials/modales_admin.php'; ?>

<!-- MODAL MÉDICO (solo ficha profesional + servicios; el alta es por cuenta) -->
<div class="modal fade" id="modalMedico" tabindex="-1" aria-labelledby="modalMedicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_medico" onsubmit="guardarMedico(event)">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title h6" id="modalMedicoLabel">
                        <i class="bi bi-person-badge-fill me-2"></i>Ficha del Médico
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_med" id="id_med_input">

                    <!-- Identidad: SOLO LECTURA, viene de la cuenta de usuario -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Médico</label>
                        <div class="border rounded p-2 bg-light">
                            <div class="fw-semibold" id="identidad_medico_nombre">—</div>
                            <div class="small text-muted">Cédula: <span id="identidad_medico_ci">—</span></div>
                        </div>
                        <div class="form-text">
                            Identidad y cédula se gestionan desde la cuenta de usuario
                            (Gestión de Usuarios). No se editan aquí: la cédula es la llave
                            que vincula al médico con su login.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="especialidad_input" class="form-label small fw-bold">Especialidad</label>
                        <input type="text" class="form-control form-control-sm" name="especialidad" id="especialidad_input" placeholder="Ej. Odontología">
                        <div class="form-text">Si se deja vacío aparecerá como "Sin especialidad".</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Servicios</label>
                        <div id="servicio_medico_checks" class="border rounded p-2"
                             style="max-height:180px; overflow-y:auto;">
                            <div class="text-muted small">Cargando servicios…</div>
                        </div>
                        <div class="form-text">
                            Puede marcar más de uno: el médico atenderá todos los seleccionados.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="estatus_medico_input" class="form-label small fw-bold">Estatus</label>
                        <select class="form-select form-select-sm" name="estatus_medico" id="estatus_medico_input" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div id="aviso_medico_sin_cuenta" class="alert alert-warning py-2 small d-none">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Este médico <strong>no tiene cuenta de usuario</strong>: no puede iniciar
                        sesión ni ver sus citas. Cree la cuenta desde
                        <a href="crear_usuario.php" class="alert-link">Gestión de Usuarios</a>.
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" id="btn_eliminar_medico" class="btn btn-danger btn-sm d-none" onclick="confirmarEliminarMedico()">
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

<script src="js/gestion_medicos.js?v=3"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
