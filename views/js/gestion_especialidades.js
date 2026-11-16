let especialidadesCache = [];
let especialidadesFilas = {};

// Cargar tasa de cambio al inicio
document.addEventListener('DOMContentLoaded', async function() {
    await Moneda.cargarTasa();
    cargarSelectServicios().then(() => cargarEspecialidades());

    const btnAdd = document.getElementById('btn_add_especialidad');
    if (btnAdd) btnAdd.addEventListener('click', abrirModalCrearEspecialidad);

    const btnRefrescar = document.getElementById('btn_refrescar');
    if (btnRefrescar) btnRefrescar.addEventListener('click', () => cargarSelectServicios().then(cargarEspecialidades));

    const buscador = document.getElementById('busc_especialidad');
    if (buscador) buscador.addEventListener('input', renderEspecialidades);

    const selectFiltro = document.getElementById('select_filtrar_servicio');
    if (selectFiltro) selectFiltro.addEventListener('change', cargarEspecialidades);

    delegarTabla('tbody_especialidades', function(accion) {
        if (accion) abrirModalEditarEspecialidad(accion.dataset.id);
    });
});

// Carga los servicios en el filtro y en el select del modal
async function cargarSelectServicios() {
    try {
        const res = await fetch('../controllers/get_servicios.php');
        const servicios = await res.json();

        const selectFiltro = document.getElementById('select_filtrar_servicio');
        const selectModal = document.getElementById('servicio_especialidad_select');

        if (Array.isArray(servicios)) {
            servicios.forEach(s => {
                if (selectFiltro) {
                    const opt = document.createElement('option');
                    opt.value = s.id_servicio;
                    opt.textContent = s.servicio;
                    selectFiltro.appendChild(opt);
                }
                if (selectModal) {
                    const opt = document.createElement('option');
                    opt.value = s.id_servicio;
                    opt.textContent = s.servicio;
                    selectModal.appendChild(opt);
                }
            });
        }
    } catch (err) {
        console.error('Error cargando servicios:', err);
    }
}

function pintarKpisEspecialidades(kpi) {
    setKpi('kpi_especialidades', fmtNum(kpi.especialidades));
    setKpi('kpi_especialidades_sub', 'en el catálogo');
    setKpiHtml('kpi_precio_prom', Moneda.htmlDual(kpi.precio_promedio));
    setKpi('kpi_precio_prom_sub', 'valor promedio');
    setKpiHtml('kpi_precio_min', Moneda.htmlDual(kpi.precio_min));
    setKpi('kpi_precio_min_sub', 'más barata');
    setKpiHtml('kpi_precio_max', Moneda.htmlDual(kpi.precio_max));
    setKpi('kpi_precio_max_sub', 'más cara');
    setKpi('kpi_solicitudes', fmtNum(kpi.solicitudes));
    setKpi('kpi_solicitudes_sub', 'registradas');
    setKpiHtml('kpi_ingresos', Moneda.htmlDual(kpi.ingresos));
    setKpi('kpi_ingresos_sub', 'acumulado');
}

function setKpiHtml(id, html) {
    const nodo = document.getElementById(id);
    if (nodo) nodo.innerHTML = html;
}

function cargarEspecialidades() {
    const tbody = document.getElementById('tbody_especialidades');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(6, 7);
    setTexto('pie_especialidades', 'Cargando especialidades…');
    setContador('count_especialidades', '…');

    const selectFiltro = document.getElementById('select_filtrar_servicio');
    const idServicio = selectFiltro ? selectFiltro.value : '';
    const url = idServicio
        ? `../controllers/get_especialidades.php?id_servicio=${encodeURIComponent(idServicio)}`
        : '../controllers/get_especialidades.php';

    const serviciosSel = selectFiltro && selectFiltro.selectedIndex >= 0
        ? selectFiltro.options[selectFiltro.selectedIndex].textContent
        : '';
    setTexto('sub_filtro_especialidades', idServicio ? `De: ${serviciosSel}` : 'Todas las especialidades');

    Promise.all([
        fetch(url).then(r => r.json()),
        fetch('../controllers/get_estadisticas_cuadro.php?tipo=especialidades').then(r => r.json()).catch(() => ({ success: false }))
    ])
    .then(([data, statsRes]) => {
        const stats = (statsRes && statsRes.success && statsRes.data) ? statsRes.data : { filas: {}, kpi: {} };
        pintarKpisEspecialidades(stats.kpi || {});
        especialidadesFilas = stats.filas || {};
        especialidadesCache = Array.isArray(data) ? data : [];
        renderEspecialidades();
    })
    .catch(err => {
        console.error('Error especialidades:', err);
        especialidadesCache = [];
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar las especialidades', 'Verifique su conexión e intente de nuevo', 7);
        setContador('count_especialidades', '0');
        setTexto('pie_especialidades', 'Error de carga');
    });
}

function renderEspecialidades() {
    const tbody = document.getElementById('tbody_especialidades');
    if (!tbody) return;

    const q = (document.getElementById('busc_especialidad')?.value || '').trim().toLowerCase();
    const visibles = especialidadesCache.filter(e =>
        !q || String(e.especialidad).toLowerCase().includes(q) ||
        String(e.id_especialidad).includes(q) || String(e.nombre_servicio || '').toLowerCase().includes(q)
    );

    setContador('count_especialidades', fmtNum(especialidadesCache.length));
    pieFiltrado('pie_especialidades', visibles.length, especialidadesCache.length, 'especialidad', 'especialidades');

    if (especialidadesCache.length === 0) {
        tbody.innerHTML = estadoVacio('bi-award', 'Sin especialidades registradas', 'Use el botón + para crear la primera', 7);
        return;
    }
    if (visibles.length === 0) {
        tbody.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 7);
        return;
    }

    tbody.innerHTML = visibles.map(e => {
        const st = especialidadesFilas[e.id_especialidad] || {};
        return `<tr>
            <td><span class="ui-id">${escapeHtml(e.id_especialidad)}</span></td>
            <td class="ui-nombre">${escapeHtml(e.especialidad)}</td>
            <td><span class="ui-dinero">${Moneda.htmlDual(e.precio)}</span></td>
            <td class="ui-secundario">${escapeHtml(e.nombre_servicio || '—')}</td>
            <td class="text-center"><span class="ui-cifra">${escapeHtml(fmtNum(st.solicitudes))}</span></td>
            <td class="text-end"><span class="ui-dinero">${Moneda.htmlDual(st.ingresos)}</span></td>
            <td class="text-center">
                <button type="button" class="ui-accion" data-accion="editar" data-id="${escapeHtml(e.id_especialidad)}"
                        title="Editar especialidad" aria-label="Editar especialidad ${escapeHtml(e.especialidad)}">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}
function abrirModalCrearEspecialidad() {
    const form = document.getElementById('form_especialidad');
    if (form) form.reset();
    const inputId = document.getElementById('id_especialidad_input');
    if (inputId) inputId.value = '';

    // Preseleccionar el servicio del filtro actual si hay uno
    const selectFiltro = document.getElementById('select_filtrar_servicio');
    const selectModal = document.getElementById('servicio_especialidad_select');
    if (selectFiltro && selectModal && selectFiltro.value) {
        selectModal.value = selectFiltro.value;
    }

    const btnEliminar = document.getElementById('btn_eliminar_especialidad');
    if (btnEliminar) btnEliminar.classList.add('d-none');

    const label = document.getElementById('modalEspecialidadLabel');
    if (label) label.textContent = 'Crear Especialidad';

    new bootstrap.Modal(document.getElementById('modalEspecialidad')).show();
}

function abrirModalEditarEspecialidad(id) {
    const e = especialidadesCache.find(x => parseInt(x.id_especialidad) === parseInt(id));
    if (!e) return;

    const form = document.getElementById('form_especialidad');
    if (form) form.reset();

    const inputId = document.getElementById('id_especialidad_input');
    if (inputId) inputId.value = e.id_especialidad;

    const selectModal = document.getElementById('servicio_especialidad_select');
    if (selectModal && e.id_servicio) selectModal.value = e.id_servicio;

    const inputNombre = document.getElementById('nombre_especialidad_input');
    if (inputNombre) inputNombre.value = e.especialidad;

    const inputPrecio = document.getElementById('precio_input');
    if (inputPrecio) inputPrecio.value = e.precio;

    const btnEliminar = document.getElementById('btn_eliminar_especialidad');
    if (btnEliminar) btnEliminar.classList.remove('d-none');

    const label = document.getElementById('modalEspecialidadLabel');
}

function guardarEspecialidad(event) {
    event.preventDefault();
    const form = document.getElementById('form_especialidad');
    const formData = new FormData(form);

    const modalEl = document.getElementById('modalEspecialidad');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarEspecialidadController.php', () => {
        cargarEspecialidades();
    });
}

function confirmarEliminarEspecialidad() {
    const id = document.getElementById('id_especialidad_input').value;
    const formData = new FormData();
    formData.append('id_especialidad', id);
    formData.append('action', 'delete');

    const modalEl = document.getElementById('modalEspecialidad');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarEspecialidadController.php', () => {
        cargarEspecialidades();
    });
}

function exportarEstadisticas(modo, formato) {
    const params = new URLSearchParams({ modo, formato });
    const sel = document.getElementById('select_filtrar_servicio');
    if (sel && sel.value) params.set('id_servicio', sel.value);
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
