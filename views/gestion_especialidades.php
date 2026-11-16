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
<style nonce="<?php echo csp_nonce(); ?>">
    #kpis_especialidades .ui-kpi .card-body { align-items: flex-start; gap: .45rem; min-width: 0; }
    #kpis_especialidades .ui-kpi .min-w-0 { flex: 1 1 auto; min-width: 0; }
    #kpis_especialidades .ui-kpi__valor { font-size: 1.02rem; overflow-wrap: anywhere; }
    #kpis_especialidades .ui-kpi__valor .precio-dual {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0;
        white-space: normal;
        line-height: 1.2;
    }
    #kpis_especialidades .ui-kpi__valor .separador { display: none; }
    #kpis_especialidades .ui-kpi__valor .precio-bsf { font-size: .72rem; color: #6c757d; font-weight: 500; }
    @media (max-width: 767.98px) {
        #kpis_especialidades .ui-kpi .card-body { padding: .5rem .55rem !important; }
        #kpis_especialidades .ui-kpi__ico { width: 30px; height: 30px; font-size: .85rem; }
        #kpis_especialidades .ui-kpi__valor { font-size: .9rem; }
    }
</style>
<main class="d-flex" style="min-height: calc(100vh - 80px);">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4 mt-3 ms-3 me-3">
        <div class="container-fluid px-2 py-3">

            <!-- ENCABEZADO -->
            <div class="ui-titulo">
                <div>
                    <div class="ui-miga">Catálogos / Gestión Médica</div>
                    <h2><i class="bi bi-award-fill me-2" style="color:#087f8c;"></i>Gestión de Especialidades</h2>
                    <p class="ui-lema">Cada especialidad pertenece a un servicio y define su precio. Filtre por
                        servicio para ver solo las que le corresponden.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="ui-refresco" id="btn_refrescar" title="Recargar" aria-label="Recargar especialidades">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-2 mb-3" id="kpis_especialidades">
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
                            <span class="ui-kpi__ico is-serv"><i class="bi bi-cash-coin"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Precio prom.</div>
                                <div class="ui-kpi__valor" id="kpi_precio_prom">—</div>
                                <div class="ui-kpi__sub" id="kpi_precio_prom_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-arrow-down-circle"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Precio mín.</div>
                                <div class="ui-kpi__valor" id="kpi_precio_min">—</div>
                                <div class="ui-kpi__sub" id="kpi_precio_min_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-arrow-up-circle"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Precio máx.</div>
                                <div class="ui-kpi__valor" id="kpi_precio_max">—</div>
                                <div class="ui-kpi__sub" id="kpi_precio_max_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card ui-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="ui-kpi__ico"><i class="bi bi-file-earmark-text"></i></span>
                            <div class="min-w-0">
                                <div class="ui-kpi__etiqueta">Solicitudes</div>
                                <div class="ui-kpi__valor" id="kpi_solicitudes">—</div>
                                <div class="ui-kpi__sub" id="kpi_solicitudes_sub">Cargando…</div>
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
                                <div class="ui-kpi__valor" id="kpi_ingresos">—</div>
                                <div class="ui-kpi__sub" id="kpi_ingresos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL DE ESPECIALIDADES -->
            <div class="ui-panel">
                <div class="ui-panel__cabecera">
                    <span class="ui-ico is-esp"><i class="bi bi-award-fill"></i></span>
                    <div class="ui-panel__txt">
                        <h3 class="ui-panel__titulo">Especialidades</h3>
                        <div class="ui-panel__sub" id="sub_filtro_especialidades">Todas las especialidades</div>
                    </div>
                    <span class="ui-contador is-esp" id="count_especialidades" aria-live="polite">—</span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Exportar estadísticas">
                            <button class="btn btn-success" type="button" onclick="exportarEstadisticas('especialidades','excel')" title="Exportar a Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </button>
                            <button class="btn btn-primary" type="button" onclick="exportarEstadisticas('especialidades','csv')" title="Exportar a CSV">
                                <i class="bi bi-filetype-csv"></i> CSV
                            </button>
                            <button class="btn btn-danger" type="button" onclick="exportarEstadisticas('especialidades','pdf')" title="Exportar a PDF">
                                <i class="bi bi-filetype-pdf"></i> PDF
                            </button>
                        </div>
                        <button class="ui-add is-esp" id="btn_add_especialidad" title="Agregar Especialidad" aria-label="Agregar especialidad">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="ui-filtros">
                    <div class="ui-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_especialidad" placeholder="Buscar especialidad…" aria-label="Buscar especialidad">
                    </div>
                    <label for="select_filtrar_servicio" class="form-label mb-0">Servicio</label>
                    <select id="select_filtrar_servicio" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Todos los servicios</option>
                    </select>
                </div>

                <div class="ui-cuerpo">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 7%;">ID</th>
                                <th>Especialidad</th>
                                <th style="width: 11%;">Precio</th>
                                <th style="width: 20%;">Servicio</th>
                                <th style="width: 11%;" class="text-center">Solicitudes</th>
                                <th style="width: 12%;" class="text-end">Ingresos</th>
                                <th style="width: 9%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_especialidades"></tbody>
                    </table>
                </div>
                <div class="ui-pie"><span id="pie_especialidades">Cargando…</span></div>
            </div>

        </div>
    </div>

</main>

<?php include __DIR__ . '/partials/modales_admin.php'; ?>

<!-- MODAL ESPECIALIDAD -->
<div class="modal fade" id="modalEspecialidad" tabindex="-1" aria-labelledby="modalEspecialidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_especialidad" onsubmit="guardarEspecialidad(event)">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title h6" id="modalEspecialidadLabel">
                        <i class="bi bi-award-fill me-2"></i>Gestión de Especialidad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_especialidad" id="id_especialidad_input">

                    <div class="mb-3">
                        <label for="servicio_especialidad_select" class="form-label small fw-bold">Servicio</label>
                        <select class="form-select form-select-sm" name="id_servicio" id="servicio_especialidad_select" required>
                            <option value="" disabled selected>Seleccione un servicio...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="nombre_especialidad_input" class="form-label small fw-bold">Nombre de la Especialidad</label>
                        <input type="text" class="form-control form-control-sm" name="especialidad" id="nombre_especialidad_input" required placeholder="Ej. Ortodoncia">
                    </div>

                    <div class="mb-3">
                        <label for="precio_input" class="form-label small fw-bold">Precio ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="precio" id="precio_input" required placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" id="btn_eliminar_especialidad" class="btn btn-danger btn-sm d-none" onclick="confirmarEliminarEspecialidad()">
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

<script src="js/gestion_especialidades.js?v=4"></script>
<script src="js/moneda.js?v=1"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
