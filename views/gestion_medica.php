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
$tituloPagina = 'IAA - Cuadro Médico';
$cssExtraAdmin = ['css/cuadro_medico.css'];
include __DIR__ . '/partials/head_admin.php';
include __DIR__ . '/partials/header_admin.php';
?>
<main class="d-flex" style="min-height: calc(100vh - 80px);">
    <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>

    <div class="flex-grow-1 p-4 mt-3 ms-3 me-3 cm-root">
        <div class="container-fluid px-2 py-3">

            <!-- ENCABEZADO -->
            <div class="cm-titulo">
                <div>
                    <div class="cm-miga">Catálogos / Configurar cuadro médico</div>
                    <h2><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Cuadro Médico</h2>
                    <p class="cm-lema">Vincule Servicios, Especialidades y Médicos en una sola vista. Seleccione un servicio
                        para ver en las columnas siguientes sus especialidades y los médicos que lo atienden.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="cm-refresco" id="btn_refrescar" title="Recargar todo el cuadro">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card cm-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="cm-kpi__ico" style="background: rgba(13,110,253,.12); color:#0d6efd;">
                                <i class="bi bi-briefcase-fill"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="cm-kpi__etiqueta">Servicios</div>
                                <div class="cm-kpi__valor" id="kpi_servicios">—</div>
                                <div class="cm-kpi__sub" id="kpi_servicios_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card cm-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="cm-kpi__ico" style="background: rgba(13,202,240,.18); color:#087f8c;">
                                <i class="bi bi-award-fill"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="cm-kpi__etiqueta">Especialidades</div>
                                <div class="cm-kpi__valor" id="kpi_especialidades">—</div>
                                <div class="cm-kpi__sub" id="kpi_especialidades_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card cm-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="cm-kpi__ico" style="background: rgba(25,135,84,.12); color:#198754;">
                                <i class="bi bi-person-badge-fill"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="cm-kpi__etiqueta">Médicos</div>
                                <div class="cm-kpi__valor" id="kpi_medicos">—</div>
                                <div class="cm-kpi__sub" id="kpi_medicos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card cm-kpi">
                        <div class="card-body py-2 px-3">
                            <span class="cm-kpi__ico" style="background: rgba(255,193,7,.18); color:#b58105;">
                                <i class="bi bi-cash-stack"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="cm-kpi__etiqueta">Ingresos</div>
                                <div class="cm-kpi__valor" id="kpi_ingresos">—</div>
                                <div class="cm-kpi__sub" id="kpi_ingresos_sub">Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PASOS (solo pantallas pequeñas) -->
            <div class="cm-pasos" role="tablist" aria-label="Pasos del cuadro médico">
                <button type="button" class="cm-paso is-activo" data-paso-btn="servicios" role="tab" aria-selected="true">
                    <b>1</b> Servicios
                </button>
                <button type="button" class="cm-paso" data-paso-btn="especialidades" role="tab" aria-selected="false">
                    <b>2</b> Especialidades
                </button>
                <button type="button" class="cm-paso" data-paso-btn="medicos" role="tab" aria-selected="false">
                    <b>3</b> Médicos
                </button>
            </div>

            <!-- REJILLA MAESTRO-DETALLE -->
            <div class="cm-grid" id="cm_grid" data-paso="servicios">

                <!-- 1. SERVICIOS -->
                <section class="cm-col cm-col--serv" data-paso="servicios" aria-label="Servicios">
                    <div class="cm-col__cabecera">
                        <span class="cm-col__ico"><i class="bi bi-briefcase-fill"></i></span>
                        <div class="cm-col__txt">
                            <h3 class="cm-col__titulo">Servicios</h3>
                            <div class="cm-col__sub">Catálogo base · clic para filtrar</div>
                        </div>
                        <span class="cm-col__contador" id="count_servicios" aria-live="polite">—</span>
                        <button type="button" class="cm-col__add" id="btn_add_servicio" title="Agregar servicio" aria-label="Agregar servicio">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="cm-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_servicio" placeholder="Buscar servicio…" aria-label="Buscar servicio">
                    </div>
                    <div class="cm-cuerpo">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:14%;">ID</th>
                                    <th>Servicio</th>
                                    <th style="width:18%;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_servicios"></tbody>
                        </table>
                    </div>
                    <div class="cm-pie"><span id="pie_servicios">Cargando…</span></div>
                </section>

                <div class="cm-conector" id="conector1" aria-hidden="true"><i class="bi bi-chevron-right"></i></div>

                <!-- 2. ESPECIALIDADES -->
                <section class="cm-col cm-col--esp" data-paso="especialidades" aria-label="Especialidades">
                    <div class="cm-col__cabecera">
                        <span class="cm-col__ico"><i class="bi bi-award-fill"></i></span>
                        <div class="cm-col__txt">
                            <h3 class="cm-col__titulo">Especialidades</h3>
                            <div class="cm-col__sub" id="sub_especialidades">Del servicio seleccionado</div>
                        </div>
                        <span class="cm-col__contador" id="count_especialidades" aria-live="polite">—</span>
                        <button type="button" class="cm-col__add" id="btn_add_especialidad" title="Agregar especialidad" aria-label="Agregar especialidad">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="cm-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_especialidad" placeholder="Buscar especialidad…" aria-label="Buscar especialidad">
                    </div>
                    <div class="cm-cuerpo">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:14%;">ID</th>
                                    <th>Especialidad</th>
                                    <th style="width:24%;">Precio</th>
                                    <th style="width:18%;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_especialidades"></tbody>
                        </table>
                    </div>
                    <div class="cm-pie"><span id="pie_especialidades">Seleccione un servicio</span></div>
                </section>

                <div class="cm-conector" id="conector2" aria-hidden="true"><i class="bi bi-chevron-right"></i></div>

                <!-- 3. MÉDICOS -->
                <section class="cm-col cm-col--med" data-paso="medicos" aria-label="Médicos">
                    <div class="cm-col__cabecera">
                        <span class="cm-col__ico"><i class="bi bi-person-badge-fill"></i></span>
                        <div class="cm-col__txt">
                            <h3 class="cm-col__titulo">Médicos</h3>
                            <div class="cm-col__sub" id="sub_medicos">Del servicio seleccionado</div>
                        </div>
                        <span class="cm-col__contador" id="count_medicos" aria-live="polite">—</span>
                        <button type="button" class="cm-col__add" id="btn_add_medico" title="Vincular médico registrado a este servicio" aria-label="Vincular médico a este servicio">
                            <i class="bi bi-person-plus"></i>
                        </button>
                    </div>
                    <div class="cm-buscar">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_medico" placeholder="Buscar por cédula o nombre…" aria-label="Buscar médico">
                    </div>
                    <div class="cm-cuerpo">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:14%;">ID</th>
                                    <th style="width:26%;">Cédula</th>
                                    <th>Médico</th>
                                    <th style="width:24%;">Especialidad</th>
                                    <th style="width:14%;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_medicos"></tbody>
                        </table>
                    </div>
                    <div class="cm-pie"><span id="pie_medicos">Seleccione un servicio</span></div>
                </section>

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
                    <p class="small text-muted mb-3">Un servicio agrupa las especialidades y los médicos que lo prestan.</p>
                    <input type="hidden" name="id_servicio" id="id_servicio_input">
                    <div class="mb-3">
                        <label for="nombre_servicio_input" class="form-label small fw-bold">Nombre del Servicio</label>
                        <input type="text" class="form-control form-control-sm" name="servicio" id="nombre_servicio_input" required placeholder="Ej. Odontología" maxlength="120">
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

<!-- MODAL ESPECIALIDAD -->
<div class="modal fade" id="modalEspecialidad" tabindex="-1" aria-labelledby="modalEspecialidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_especialidad" onsubmit="guardarEspecialidad(event)">
                <div class="modal-header" style="background:#0aa2c0; color:#fff;">
                    <h5 class="modal-title h6" id="modalEspecialidadLabel">
                        <i class="bi bi-award-fill me-2"></i>Gestión de Especialidad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="hint_especialidad">Se creará dentro del servicio seleccionado en la primera columna.</p>
                    <input type="hidden" name="id_especialidad" id="id_especialidad_input">
                    <div class="mb-3">
                        <label for="nombre_especialidad_input" class="form-label small fw-bold">Nombre de la Especialidad</label>
                        <input type="text" class="form-control form-control-sm" name="especialidad" id="nombre_especialidad_input" required placeholder="Ej. Ortodoncia" maxlength="120">
                    </div>
                    <div class="mb-3">
                        <label for="precio_input" class="form-label small fw-bold">Precio ($)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" class="form-control" name="precio" id="precio_input" required placeholder="0.00">
                        </div>
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

<!-- MODAL ASIGNAR MÉDICO AL SERVICIO (solo vínculo; no crea médicos) -->
<div class="modal fade" id="modalAsignarMedico" tabindex="-1" aria-labelledby="modalAsignarMedicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="form_asignar_medico" onsubmit="return false;">
                <div class="modal-header" style="background:#198754; color:#fff;">
                    <h5 class="modal-title h6" id="modalAsignarMedicoLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Vincular médico al servicio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="hint_asignar_medico">Se vinculará al servicio seleccionado en la primera columna.</p>

                    <div class="cm-buscar mb-2">
                        <i class="bi bi-search"></i>
                        <input type="search" id="busc_medico_asignar" placeholder="Buscar por cédula, nombre o especialidad…"
                               aria-label="Buscar médico para asignar" autocomplete="off">
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small text-muted">Médicos registrados no vinculados a este servicio</span>
                        <span class="badge bg-secondary" id="count_disponibles" aria-live="polite">…</span>
                    </div>

                    <div class="border rounded overflow-auto" style="max-height: 45vh;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th style="width:14%;">ID</th>
                                    <th style="width:22%;">Cédula</th>
                                    <th>Médico</th>
                                    <th style="width:22%;">Especialidad</th>
                                    <th style="width:14%;" class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="lista_medicos_asignar"></tbody>
                        </table>
                    </div>

                    <div class="form-text mt-2">
                        Si el médico no aparece, regístrelo primero desde
                        <a href="crear_usuario.php" class="alert-link">la gestión de usuarios</a>
                        (los médicos sin cuenta no pueden iniciar sesión).
                    </div>
                    <div class="alert alert-info py-2 small mb-0 mt-2" id="aviso_sin_medicos" style="display:none;">
                        <i class="bi bi-info-circle me-1"></i>No hay médicos disponibles para vincular
                        (quizá ya están todos en este servicio).
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="small text-muted" id="seleccion_pendiente">Sin selección</span>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL FICHA DEL MÉDICO (solo datos profesionales; la identidad no se edita aquí) -->
<div class="modal fade" id="modalMedico" tabindex="-1" aria-labelledby="modalMedicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form_medico" onsubmit="guardarMedico(event)">
                <div class="modal-header" style="background:#198754; color:#fff;">
                    <h5 class="modal-title h6" id="modalMedicoLabel">
                        <i class="bi bi-person-badge-fill me-2"></i>Ficha del Médico
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="hint_medico">Edite los datos profesionales de este médico.</p>
                    <input type="hidden" name="id_med" id="id_med_input">

                    <!-- Identidad: SOLO LECTURA, viene de la cuenta de usuario -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Médico</label>
                        <div class="border rounded p-2 bg-light">
                            <div class="fw-semibold" id="identidad_medico_nombre">—</div>
                            <div class="small text-muted">Cédula: <span id="identidad_medico_ci">—</span></div>
                        </div>
                        <div class="form-text">
                            La identidad y la cédula se gestionan desde la cuenta de usuario.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="especialidad_input" class="form-label small fw-bold">Especialidad</label>
                        <input type="text" class="form-control form-control-sm" name="especialidad" id="especialidad_input" maxlength="120" placeholder="Ej. Odontología">
                    </div>

                    <div class="mb-3">
                        <label for="estatus_medico_input" class="form-label small fw-bold">Estatus</label>
                        <select class="form-select form-select-sm" name="estatus_medico" id="estatus_medico_input">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="small text-muted" id="servicios_medico_actuales">
                        <i class="bi bi-hospital me-1"></i>Servicios: —
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" id="btn_quitar_del_servicio" class="btn btn-danger btn-sm" onclick="confirmarQuitarDelServicio()">
                        <i class="bi bi-x-circle me-1"></i> Quitar de este servicio
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

<script src="js/gestion_medica.js?v=4"></script>
<script src="js/moneda.js?v=1"></script>
<?php include __DIR__ . '/partials/foot_admin.php'; ?>
