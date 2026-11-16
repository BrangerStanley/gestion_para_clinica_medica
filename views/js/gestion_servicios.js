let serviciosCache = [];
let serviciosFilas = {};

document.addEventListener('DOMContentLoaded', function() {
    cargarServicios();

    const btnAddServicio = document.getElementById('btn_add_servicio');
    if (btnAddServicio) btnAddServicio.addEventListener('click', abrirModalCrearServicio);

    const btnRefrescar = document.getElementById('btn_refrescar');
    if (btnRefrescar) btnRefrescar.addEventListener('click', cargarServicios);

    const buscador = document.getElementById('busc_servicio');
    if (buscador) buscador.addEventListener('input', renderServicios);

    delegarTabla('tbody_servicios', function(accion) {
        if (accion) abrirModalEditarServicio(accion.dataset.id);
    });

    // --- Panel de médicos dentro del modal de servicio ---
    const btnAsignarMedico = document.getElementById('btn_asignar_medico');
    if (btnAsignarMedico) {
        btnAsignarMedico.addEventListener('click', function () {
            const select = document.getElementById('select_agregar_medico');
            const idMedico = select ? parseInt(select.value, 10) : 0;
            if (!idMedico) {
                alert('Seleccione un médico de la lista.');
                return;
            }
            cambiarAsignacionMedico(idMedico, 'asignar');
        });
    }

    // Delegación: los botones "quitar" se re-crean en cada recarga del panel.
    const listaMedicos = document.getElementById('lista_medicos_servicio');
    if (listaMedicos) {
        listaMedicos.addEventListener('click', function (ev) {
            const btn = ev.target.closest('[data-quitar-medico]');
            if (!btn) return;
            const idMedico = parseInt(btn.dataset.quitarMedico, 10);
            if (idMedico) cambiarAsignacionMedico(idMedico, 'quitar');
        });
    }

    // Al cerrar el modal, limpiar el panel para el próximo uso.
    const modalServicio = document.getElementById('modalServicio');
    if (modalServicio) {
        modalServicio.addEventListener('hidden.bs.modal', ocultarPanelMedicos);
    }
});

function pintarKpisServicios(kpi) {
    setKpi('kpi_servicios', fmtNum(kpi.servicios));
    setKpi('kpi_servicios_sub', 'en el catálogo');
    setKpi('kpi_citas', fmtNum(kpi.citas));
    setKpi('kpi_citas_sub', `${fmtNum(kpi.hoy)} para hoy`);
    setKpi('kpi_atendidas', fmtNum(kpi.atendidas));
    setKpi('kpi_atendidas_sub', `de ${fmtNum(kpi.citas)} citas`);
    setKpi('kpi_programadas', fmtNum(kpi.programadas));
    setKpi('kpi_programadas_sub', 'pendientes');
    setKpi('kpi_canceladas', fmtNum(kpi.canceladas));
    setKpi('kpi_canceladas_sub', 'descartadas');
    setKpi('kpi_ingresos', fmtMoney(kpi.ingresos));
    setKpi('kpi_ingresos_sub', `${fmtNum(kpi.solicitudes)} solicitudes`);
}

function cargarServicios() {
    const tbody = document.getElementById('tbody_servicios');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(6, 7);
    setTexto('pie_servicios', 'Cargando servicios…');
    setContador('count_servicios', '…');

    Promise.all([
        fetch('../controllers/get_servicios.php').then(r => r.json()),
        fetch('../controllers/get_estadisticas_cuadro.php?tipo=servicios').then(r => r.json()).catch(() => ({ success: false }))
    ])
    .then(([servicios, statsRes]) => {
        const stats = (statsRes && statsRes.success && statsRes.data) ? statsRes.data : { filas: {}, kpi: {} };
        pintarKpisServicios(stats.kpi || {});
        serviciosFilas = stats.filas || {};
        serviciosCache = Array.isArray(servicios) ? servicios : [];
        renderServicios();
    })
    .catch(err => {
        console.error('Error servicios:', err);
        serviciosCache = [];
        serviciosFilas = {};
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar los servicios', 'Verifique su conexión e intente de nuevo', 7);
        setContador('count_servicios', '0');
        setTexto('pie_servicios', 'Error de carga');
    });
}

function renderServicios() {
    const tbody = document.getElementById('tbody_servicios');
    if (!tbody) return;

    const q = (document.getElementById('busc_servicio')?.value || '').trim().toLowerCase();
    const visibles = serviciosCache.filter(s =>
        !q || String(s.servicio).toLowerCase().includes(q) || String(s.id_servicio).includes(q)
    );

    setContador('count_servicios', fmtNum(serviciosCache.length));
    pieFiltrado('pie_servicios', visibles.length, serviciosCache.length, 'servicio', 'servicios');

    if (serviciosCache.length === 0) {
        tbody.innerHTML = estadoVacio('bi-folder-x', 'Aún no hay servicios', 'Use el botón + para crear el primero', 7);
        return;
    }
    if (visibles.length === 0) {
        tbody.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 7);
        return;
    }

    tbody.innerHTML = visibles.map(s => {
        const st = serviciosFilas[s.id_servicio] || {};
        return `<tr>
            <td><span class="ui-id">${escapeHtml(s.id_servicio)}</span></td>
            <td class="ui-nombre">${escapeHtml(s.servicio)}</td>
            <td class="text-center"><span class="ui-cifra">${escapeHtml(fmtNum(st.especialidades))}</span></td>
            <td class="text-center"><span class="ui-cifra">${escapeHtml(fmtNum(st.citas))}</span></td>
            <td class="text-center"><span class="ui-cifra" style="color:#198754;">${escapeHtml(fmtNum(st.atendidas))}</span></td>
            <td class="text-end"><span class="ui-dinero">${escapeHtml(fmtMoney(st.ingresos))}</span></td>
            <td class="text-center">
                <button type="button" class="ui-accion" data-accion="editar" data-id="${escapeHtml(s.id_servicio)}"
                        title="Editar servicio" aria-label="Editar servicio ${escapeHtml(s.servicio)}">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function abrirModalCrearServicio() {
    const form = document.getElementById('form_servicio');
    if (form) form.reset();
    const inputId = document.getElementById('id_servicio_input');
    if (inputId) inputId.value = '';

    // Defaults coherentes con los que aplica el controlador en el alta
    const inputDisp = document.getElementById('disponibilidad_input');
    if (inputDisp) inputDisp.value = 'Disponible';
    const inputTipo = document.getElementById('tipo_atencion_input');
    if (inputTipo) inputTipo.value = 'Presencial';

    const btnEliminar = document.getElementById('btn_eliminar_servicio');
    if (btnEliminar) btnEliminar.classList.add('d-none');

    const label = document.getElementById('modalServicioLabel');
    if (label) label.textContent = 'Crear Servicio';

    // El panel de médicos solo aplica a servicios ya existentes.
    ocultarPanelMedicos();

    mostrarModal('modalServicio');
}

// ---------------------------------------------------------------
// Panel de médicos del servicio (dentro del modal)
// ---------------------------------------------------------------
let servicioModalId = null;

function ocultarPanelMedicos() {
    servicioModalId = null;
    const panel = document.getElementById('panel_medicos_servicio');
    if (panel) panel.classList.add('d-none');
    const lista = document.getElementById('lista_medicos_servicio');
    if (lista) lista.innerHTML = '';
    const select = document.getElementById('select_agregar_medico');
    if (select) select.innerHTML = '<option value="">Seleccione un médico…</option>';
    setContador('count_medicos_servicio', '0');
}

async function cargarMedicosDelServicio(idServicio) {
    const lista  = document.getElementById('lista_medicos_servicio');
    const select = document.getElementById('select_agregar_medico');
    if (lista) lista.innerHTML = '<div class="text-muted small">Cargando…</div>';

    let payload;
    try {
        const res = await fetch(`../controllers/get_medicos_servicio.php?id_servicio=${encodeURIComponent(idServicio)}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        payload = await res.json();
    } catch (err) {
        console.error('Error cargando médicos del servicio:', err);
        if (lista) {
            lista.innerHTML = '<div class="text-danger small">No se pudieron cargar los médicos. '
                            + 'Revise la consola o el log.</div>';
        }
        return;
    }

    if (!payload || payload.success !== true || !payload.data) {
        if (lista) lista.innerHTML = '<div class="text-danger small">Respuesta inesperada del servidor.</div>';
        return;
    }

    const asignados   = Array.isArray(payload.data.asignados)   ? payload.data.asignados   : [];
    const disponibles = Array.isArray(payload.data.disponibles) ? payload.data.disponibles : [];

    setContador('count_medicos_servicio', fmtNum(asignados.length));

    // --- Lista de adscritos ---
    if (lista) {
        if (asignados.length === 0) {
            lista.innerHTML = '<div class="text-muted small">Todavía no hay médicos en este servicio. '
                            + 'Use el selector de abajo para asignar uno.</div>';
        } else {
            lista.innerHTML = asignados.map(m => {
                const inactivo = m.estatus_medico !== 'activo';
                const badge = inactivo
                    ? '<span class="badge bg-secondary ms-1">Inactivo</span>'
                    : '';
                return `<div class="d-flex align-items-center justify-content-between border-bottom py-1">
                            <span class="small">${escapeHtml(m.nombre_med || m.ci_med || 'Sin nombre')}
                                <span class="text-muted">· CI ${escapeHtml(m.ci_med || 'N/A')}</span>${badge}</span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1"
                                    data-quitar-medico="${escapeHtml(m.id_med)}"
                                    title="Quitar del servicio" aria-label="Quitar médico del servicio">
                                <i class="bi bi-person-dash"></i>
                            </button>
                        </div>`;
            }).join('');
        }
    }

    // --- Selector de disponibles ---
    if (select) {
        select.innerHTML = '<option value="">Seleccione un médico…</option>';
        if (disponibles.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.disabled = true;
            opt.textContent = 'No hay médicos disponibles para agregar';
            select.appendChild(opt);
            select.disabled = true;
        } else {
            select.disabled = false;
            disponibles.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id_med;
                opt.textContent = `${m.nombre_med || 'Sin nombre'} — CI ${m.ci_med || 'N/A'}`;
                select.appendChild(opt);
            });
        }
    }
}

// Asigna o quita un médico. Se pide contraseña de admin, igual que el resto
// del catálogo, y se recarga solo la lista del panel.
function cambiarAsignacionMedico(idMedico, accion) {
    const idServicio = servicioModalId;
    if (!idServicio) return;

    const formData = new FormData();
    formData.append('id_servicio', idServicio);
    formData.append('id_medico', idMedico);
    formData.append('accion', accion);

    solicitarConfirmacionAdmin(formData, '../controllers/procesarAsignacionMedicoServicio.php', () => {
        cargarMedicosDelServicio(idServicio);
    });
}

function abrirModalEditarServicio(id) {
    const s = serviciosCache.find(x => parseInt(x.id_servicio) === parseInt(id));
    if (!s) return;

    const form = document.getElementById('form_servicio');
    if (form) form.reset();

    const inputId = document.getElementById('id_servicio_input');
    if (inputId) inputId.value = s.id_servicio;

    const inputNombre = document.getElementById('nombre_servicio_input');
    if (inputNombre) inputNombre.value = s.servicio;

    // Rellenar los 3 campos que el controlador persiste, para no borrarlos al guardar
    const inputDisp = document.getElementById('disponibilidad_input');
    if (inputDisp) inputDisp.value = s.disponibilidad || 'Disponible';
    const inputCob = document.getElementById('cobertura_input');
    if (inputCob) inputCob.value = s.cobertura || '';
    const inputTipo = document.getElementById('tipo_atencion_input');
    if (inputTipo) inputTipo.value = s.tipo_atencion || 'Presencial';

    const btnEliminar = document.getElementById('btn_eliminar_servicio');
    if (btnEliminar) btnEliminar.classList.remove('d-none');

    const label = document.getElementById('modalServicioLabel');
    if (label) label.textContent = 'Editar Servicio';

    mostrarModal('modalServicio');

    // El panel de médicos solo existe para servicios ya guardados.
    const panel = document.getElementById('panel_medicos_servicio');
    if (panel) {
        servicioModalId = parseInt(s.id_servicio, 10);
        panel.classList.remove('d-none');
        cargarMedicosDelServicio(servicioModalId);
    }
}

function guardarServicio(event) {
    event.preventDefault();
    const form = document.getElementById('form_servicio');
    const formData = new FormData(form);

    const modalEl = document.getElementById('modalServicio');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarServicioController.php', () => {
        cargarServicios();
    });
}

function confirmarEliminarServicio() {
    const id = document.getElementById('id_servicio_input').value;
    const formData = new FormData();
    formData.append('id_servicio', id);
    formData.append('action', 'delete');

    const modalEl = document.getElementById('modalServicio');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarServicioController.php', () => {
        cargarServicios();
    });
}

function exportarEstadisticas(modo, formato) {
    const params = new URLSearchParams({ modo, formato });
    const url = `/administracion_medica/controllers/exportar_estadisticas_gestion.php?${params.toString()}`;
    if (formato === 'pdf') {
        window.open(url, '_blank');
    } else {
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }
}
window.exportarEstadisticas = exportarEstadisticas;
