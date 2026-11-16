/* ============================================================
   Cuadro Médico · maestro-detalle
   Servicios -> Especialidades -> Medicos
   Archivo: views/js/gestion_medica.js
   ============================================================ */
// ---------- Estado ----------
let serviciosCache = [];
let especialidadesCache = [];
let medicosCache = [];
let servicioSeleccionadoId = null;

const CTRL_SERVICIO = '../controllers/procesarServicioController.php';
const CTRL_ESPECIALIDAD = '../controllers/procesarEspecialidadController.php';
const CTRL_MEDICO = '../controllers/procesarMedicoController.php';
function plural(n, uno, varios) {
    return fmtNum(n) + ' ' + (n === 1 ? uno : varios);
}

function el(id) {
    return document.getElementById(id);
}

function texto(id, valor) {
    const nodo = el(id);
    if (nodo) nodo.textContent = valor;
}
function conector(id, opciones) {
    const c = el(id);
    if (!c) return;
    c.classList.toggle('is-activo', !!(opciones && opciones.activo));
    c.classList.toggle('is-cargando', !!(opciones && opciones.cargando));
}

// ---------- Pasos (pantallas pequeñas) ----------
function irAPaso(paso) {
    const grid = el('cm_grid');
    if (grid) grid.dataset.paso = paso;
    document.querySelectorAll('[data-paso-btn]').forEach(btn => {
        const activo = btn.dataset.pasoBtn === paso;
        btn.classList.toggle('is-activo', activo);
        btn.setAttribute('aria-selected', activo ? 'true' : 'false');
    });
}

function esPantallaChica() {
    return window.matchMedia('(max-width: 1199.98px)').matches;
}

// ---------- KPIs ----------
async function cargarKpis() {
    const pedir = (tipo) => fetch(`../controllers/get_estadisticas_cuadro.php?tipo=${tipo}`)
        .then(r => r.json())
        .catch(() => null);

    const [rs, re, rm] = await Promise.all([pedir('servicios'), pedir('especialidades'), pedir('medicos')]);

    const kpiS = (rs && rs.success && rs.data) ? rs.data.kpi : {};
    const kpiE = (re && re.success && re.data) ? re.data.kpi : {};
    const kpiM = (rm && rm.success && rm.data) ? rm.data.kpi : {};

    texto('kpi_servicios', fmtNum(kpiS.servicios));
    texto('kpi_servicios_sub', `${fmtNum(kpiS.citas)} citas · ${fmtNum(kpiS.hoy)} hoy`);

    texto('kpi_especialidades', fmtNum(kpiE.especialidades));
    texto('kpi_especialidades_sub', `Precio prom. ${fmtMoney(kpiE.precio_promedio)}`);

    texto('kpi_medicos', fmtNum(kpiM.medicos));
    texto('kpi_medicos_sub', `${fmtNum(kpiM.activos)} activos · ${fmtNum(kpiM.inactivos)} inactivos`);

    texto('kpi_ingresos', fmtMoney(kpiS.ingresos));
    texto('kpi_ingresos_sub', `${fmtNum(kpiS.atendidas)} atendidas de ${fmtNum(kpiS.citas)}`);
}

// ---------- SERVICIOS ----------
async function cargarServicios() {
    const tbody = el('tbody_servicios');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(5, 3);
    texto('count_servicios', '…');
    texto('pie_servicios', 'Cargando servicios…');

    try {
        const res = await fetch('../controllers/get_servicios.php');
        const data = await res.json();
        serviciosCache = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error servicios:', err);
        serviciosCache = [];
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar los servicios', 'Verifique su conexión e intente de nuevo', 3);
        texto('count_servicios', '0');
        texto('pie_servicios', 'Error de carga');
        return;
    }

    renderServicios();

    if (serviciosCache.length > 0) {
        const conservar = serviciosCache.find(s => parseInt(s.id_servicio) === parseInt(servicioSeleccionadoId));
        seleccionarServicio(conservar ? conservar.id_servicio : serviciosCache[0].id_servicio, { saltarPaso: true });
    } else {
        servicioSeleccionadoId = null;
        conector('conector1', { activo: false });
        conector('conector2', { activo: false });
        limpiarTablasDependientes();
    }
}

function renderServicios() {
    const tbody = el('tbody_servicios');
    if (!tbody) return;

    const q = (el('busc_servicio')?.value || '').trim().toLowerCase();
    const filas = serviciosCache.filter(s =>
        !q ||
        String(s.servicio).toLowerCase().includes(q) ||
        String(s.id_servicio).includes(q)
    );

    texto('count_servicios', fmtNum(serviciosCache.length));
    texto('pie_servicios', q
        ? `Mostrando ${plural(filas.length, 'resultado', 'resultados')} de ${plural(serviciosCache.length, 'servicio', 'servicios')}`
        : plural(serviciosCache.length, 'servicio registrado', 'servicios registrados'));

    if (serviciosCache.length === 0) {
        tbody.innerHTML = estadoVacio('bi-folder-x', 'Aún no hay servicios', 'Use el botón + para crear el primero', 3);
        return;
    }
    if (filas.length === 0) {
        tbody.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 3);
        return;
    }

    tbody.innerHTML = filas.map(s => {
        const activa = parseInt(s.id_servicio) === parseInt(servicioSeleccionadoId);
        return `<tr class="cm-fila${activa ? ' is-activa' : ''}" data-id="${escapeHtml(s.id_servicio)}"
                    tabindex="0" role="button" aria-pressed="${activa ? 'true' : 'false'}">
            <td><span class="cm-id">${escapeHtml(s.id_servicio)}</span></td>
            <td class="cm-nombre">${escapeHtml(s.servicio)}</td>
            <td class="text-center">
                <button type="button" class="cm-accion" data-accion="editar" data-id="${escapeHtml(s.id_servicio)}"
                        title="Editar servicio" aria-label="Editar servicio">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function seleccionarServicio(idServicio, opciones) {
    servicioSeleccionadoId = parseInt(idServicio, 10);
    renderServicios();

    const serv = serviciosCache.find(s => parseInt(s.id_servicio) === servicioSeleccionadoId);
    texto('sub_especialidades', serv ? `De: ${serv.servicio}` : 'Del servicio seleccionado');
    texto('sub_medicos', serv ? `De: ${serv.servicio}` : 'Del servicio seleccionado');

    conector('conector1', { activo: true });

    cargarEspecialidades(servicioSeleccionadoId);
    cargarMedicos(servicioSeleccionadoId);

    if (!opciones || !opciones.saltarPaso) {
        if (esPantallaChica()) irAPaso('especialidades');
    }
}

function limpiarTablasDependientes() {
    texto('sub_especialidades', 'Del servicio seleccionado');
    texto('sub_medicos', 'Del servicio seleccionado');
    texto('count_especialidades', '—');
    texto('count_medicos', '—');
    texto('pie_especialidades', 'Seleccione un servicio');
    texto('pie_medicos', 'Seleccione un servicio');

    const esp = el('tbody_especialidades');
    const med = el('tbody_medicos');
    if (esp) esp.innerHTML = estadoVacio('bi-arrow-left-circle', 'Seleccione un servicio', 'Se mostrarán sus especialidades aquí', 4);
    if (med) med.innerHTML = estadoVacio('bi-arrow-left-circle', 'Seleccione un servicio', 'Se mostrarán sus médicos aquí', 5);

    especialidadesCache = [];
    medicosCache = [];
}

// ---------- ESPECIALIDADES ----------
async function cargarEspecialidades(idServicio) {
    const tbody = el('tbody_especialidades');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(4, 4);
    texto('count_especialidades', '…');
    texto('pie_especialidades', 'Buscando especialidades…');
    conector('conector2', { activo: false, cargando: true });

    try {
        const res = await fetch(`../controllers/get_especialidades.php?id_servicio=${encodeURIComponent(idServicio)}`);
        const data = await res.json();
        especialidadesCache = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error especialidades:', err);
        especialidadesCache = [];
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar las especialidades', 'Verifique su conexión e intente de nuevo', 4);
        texto('count_especialidades', '0');
        texto('pie_especialidades', 'Error de carga');
        conector('conector2', { activo: false });
        return;
    }

    renderEspecialidades();
    conector('conector2', { activo: especialidadesCache.length > 0 });
}

function renderEspecialidades() {
    const tbody = el('tbody_especialidades');
    if (!tbody) return;

    const q = (el('busc_especialidad')?.value || '').trim().toLowerCase();
    const filas = especialidadesCache.filter(e =>
        !q || String(e.especialidad).toLowerCase().includes(q) || String(e.id_especialidad).includes(q)
    );

    texto('count_especialidades', fmtNum(especialidadesCache.length));
    texto('pie_especialidades', q
        ? `Mostrando ${plural(filas.length, 'resultado', 'resultados')} de ${plural(especialidadesCache.length, 'especialidad', 'especialidades')}`
        : plural(especialidadesCache.length, 'especialidad', 'especialidades'));

    if (especialidadesCache.length === 0) {
        tbody.innerHTML = estadoVacio('bi-award', 'Sin especialidades en este servicio', 'Use el botón + para vincular una', 4);
        return;
    }
    if (filas.length === 0) {
        tbody.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 4);
        return;
    }

    tbody.innerHTML = filas.map(e => {
        return `<tr class="cm-fila" data-id="${escapeHtml(e.id_especialidad)}" tabindex="0" role="button">
            <td><span class="cm-id">${escapeHtml(e.id_especialidad)}</span></td>
            <td class="cm-nombre">${escapeHtml(e.especialidad)}</td>
            <td class="cm-precio">${Moneda.htmlDual(e.precio)}</td>
            <td class="text-center">
                <button type="button" class="cm-accion" data-accion="editar" data-id="${escapeHtml(e.id_especialidad)}"
                        title="Editar especialidad" aria-label="Editar especialidad">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

// ---------- MÉDICOS ----------
async function cargarMedicos(idServicio) {
    const tbody = el('tbody_medicos');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(4, 4);
    texto('count_medicos', '…');
    texto('pie_medicos', 'Buscando médicos…');

    try {
        const res = await fetch(`../controllers/get_medicos.php?id_servicio=${encodeURIComponent(idServicio)}`);
        const data = await res.json();
        medicosCache = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error médicos:', err);
        medicosCache = [];
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar los médicos', 'Verifique su conexión e intente de nuevo', 4);
        texto('count_medicos', '0');
        texto('pie_medicos', 'Error de carga');
        return;
    }

    renderMedicos();
}

function renderMedicos() {
    const tbody = el('tbody_medicos');
    if (!tbody) return;

    const q = (el('busc_medico')?.value || '').trim().toLowerCase();
    const filas = medicosCache.filter(m =>
        !q ||
        String(m.nombre_med).toLowerCase().includes(q) ||
        String(m.ci_med || '').toLowerCase().includes(q)
    );

    texto('count_medicos', fmtNum(medicosCache.length));
    texto('pie_medicos', q
        ? `Mostrando ${plural(filas.length, 'resultado', 'resultados')} de ${plural(medicosCache.length, 'médico', 'médicos')}`
        : plural(medicosCache.length, 'médico', 'médicos'));

    if (medicosCache.length === 0) {
        tbody.innerHTML = estadoVacio('bi-person-x', 'Sin médicos en este servicio',
            'Use el botón para vincular un médico ya registrado', 4);
        return;
    }
    if (filas.length === 0) {
        tbody.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 4);
        return;
    }

    tbody.innerHTML = filas.map(m => {
        // El nombre viene de la cuenta (persona.nombre). Vacío = sin cuenta.
        const tieneCuenta = m.tiene_cuenta !== false;
        const nombreVisible = m.nombre_med
            ? escapeHtml(m.nombre_med)
            : '<span class="text-muted fst-italic">Sin nombre (sin cuenta)</span>';
        const etiquetaCuenta = tieneCuenta
            ? ''
            : ' <span class="badge bg-warning text-dark" title="Sin cuenta de usuario: no puede iniciar sesión">Sin cuenta</span>';
        const esp = (m.especialidad || '').trim();
        const etiquetaEsp = esp ? escapeHtml(esp) : '<span class="text-muted fst-italic">Sin especialidad</span>';

        return `<tr class="cm-fila" data-id="${escapeHtml(m.id_med)}" tabindex="0" role="button">
        <td><span class="cm-id">${escapeHtml(m.id_med)}</span></td>
        <td class="cm-secundario">${escapeHtml(m.ci_med || 'N/A')}</td>
        <td class="cm-nombre">${nombreVisible}${etiquetaCuenta}</td>
        <td class="cm-secundario">${etiquetaEsp}</td>
        <td class="text-center">
            <button type="button" class="cm-accion" data-accion="editar" data-id="${escapeHtml(m.id_med)}"
                    title="Ver ficha del médico" aria-label="Ver ficha del médico">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    </tr>`;
    }).join('');
}

// ---------- Inicialización ----------
document.addEventListener('DOMContentLoaded', async function () {
    await Moneda.cargarTasa();
    cargarKpis();
    cargarServicios();

    const add = [
        ['btn_add_servicio', abrirModalCrearServicio],
        ['btn_add_especialidad', abrirModalCrearEspecialidad],
        ['btn_add_medico', abrirModalAsignarMedico],
        ['btn_refrescar', function () { cargarKpis(); cargarServicios(); }]
    ];
    add.forEach(([id, fn]) => {
        const nodo = el(id);
        if (nodo) nodo.addEventListener('click', fn);
    });

    // Búsquedas
    [['busc_servicio', renderServicios],
     ['busc_especialidad', renderEspecialidades],
     ['busc_medico', renderMedicos],
     ['busc_medico_asignar', renderMedicosAsignar]].forEach(([id, fn]) => {
        const nodo = el(id);
        if (nodo) nodo.addEventListener('input', fn);
    });

    // Delegación de eventos en las tablas (seguro ante re-render)
    delegarTabla('tbody_servicios', function (item, fila) {
        if (item) abrirModalEditarServicio(item.dataset.id);
        else if (fila) seleccionarServicio(fila.dataset.id);
    });
    delegarTabla('tbody_especialidades', function (item) {
        if (item) abrirModalEditarEspecialidad(item.dataset.id);
    });
    delegarTabla('tbody_medicos', function (item, fila) {
        if (item) abrirModalEditarMedico(item.dataset.id);
        else if (fila) abrirModalEditarMedico(fila.dataset.id);
    });

    // Lista de médicos de la modal de asignación (clic en botón o en la fila).
    delegarTabla('lista_medicos_asignar', function (item, fila) {
        const id = (item && item.dataset.id) || (fila && fila.dataset.id);
        if (id) asignarMedico(id);
    });

    // Pasos en pantallas pequeñas
    document.querySelectorAll('[data-paso-btn]').forEach(btn => {
        btn.addEventListener('click', () => irAPaso(btn.dataset.pasoBtn));
    });
});
function abrirModalCrearServicio() {
    const form = el('form_servicio');
    if (form) form.reset();
    el('id_servicio_input').value = '';
    el('btn_eliminar_servicio').classList.add('d-none');
    el('modalServicioLabel').textContent = 'Crear Servicio';
    mostrarModal('modalServicio');
}

function abrirModalEditarServicio(id) {
    const s = serviciosCache.find(x => parseInt(x.id_servicio) === parseInt(id));
    if (!s) return;
    const form = el('form_servicio');
    if (form) form.reset();
    el('id_servicio_input').value = s.id_servicio;
    el('nombre_servicio_input').value = s.servicio;
    el('btn_eliminar_servicio').classList.remove('d-none');
    el('modalServicioLabel').textContent = 'Editar Servicio';
    mostrarModal('modalServicio');
}

function abrirModalCrearEspecialidad() {
    if (!servicioSeleccionadoId) {
        alert('Seleccione primero un servicio en la primera columna.');
        if (esPantallaChica()) irAPaso('servicios');
        return;
    }
    const form = el('form_especialidad');
    if (form) form.reset();
    el('id_especialidad_input').value = '';
    el('btn_eliminar_especialidad').classList.add('d-none');
    el('modalEspecialidadLabel').textContent = 'Crear Especialidad';
    const serv = serviciosCache.find(s => parseInt(s.id_servicio) === parseInt(servicioSeleccionadoId));
    el('hint_especialidad').textContent = serv
        ? `Se creará dentro del servicio "${serv.servicio}".`
        : 'Se creará dentro del servicio seleccionado en la primera columna.';
    mostrarModal('modalEspecialidad');
}

function abrirModalEditarEspecialidad(id) {
    const e = especialidadesCache.find(x => parseInt(x.id_especialidad) === parseInt(id));
    if (!e) return;
    const form = el('form_especialidad');
    if (form) form.reset();
    el('id_especialidad_input').value = e.id_especialidad;
    el('nombre_especialidad_input').value = e.especialidad;
    el('precio_input').value = e.precio;
    el('btn_eliminar_especialidad').classList.remove('d-none');
    el('modalEspecialidadLabel').textContent = 'Editar Especialidad';
    el('hint_especialidad').textContent = e.nombre_servicio
        ? `Actualmente pertenece a "${e.nombre_servicio}".`
        : 'Edite los datos de esta especialidad.';
    mostrarModal('modalEspecialidad');
}

// Vincula un médico YA REGISTRADO al servicio seleccionado.
// La modal muestra la LISTA de médicos (con buscador) y se asigna haciendo clic
// en la fila o en el botón "Asignar".
// El alta de médicos no ocurre aquí: se crean como cuenta con rol "Médico".
let medicosDisponiblesCache = [];

async function abrirModalAsignarMedico() {
    if (!servicioSeleccionadoId) {
        alert('Seleccione primero un servicio en la primera columna.');
        if (esPantallaChica()) irAPaso('servicios');
        return;
    }

    const cuerpo = el('lista_medicos_asignar');
    const aviso = el('aviso_sin_medicos');
    cuerpo.innerHTML = esqueleto(4, 5);
    texto('count_disponibles', '…');
    texto('seleccion_pendiente', 'Sin selección');
    aviso.style.display = 'none';

    const serv = serviciosCache.find(s => parseInt(s.id_servicio) === parseInt(servicioSeleccionadoId));
    el('hint_asignar_medico').textContent = serv
        ? `Se vinculará al servicio "${serv.servicio}".`
        : 'Se vinculará al servicio seleccionado en la primera columna.';

    mostrarModal('modalAsignarMedico');

    // Todos los médicos registrados, para poder elegir cuáles entran al servicio.
    let todos = [];
    try {
        const res = await fetch('../controllers/get_medicos.php');
        const data = await res.json();
        todos = Array.isArray(data) ? data : [];
    } catch (err) {
        console.error('Error al cargar médicos:', err);
        medicosDisponiblesCache = [];
        cuerpo.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar los médicos', 'Verifique su conexión e intente de nuevo', 5);
        texto('count_disponibles', '0');
        return;
    }

    // Se excluyen los que ya están en este servicio (evita INSERT IGNORE silencioso).
    const yaAsignados = new Set(
        medicosCache.map(m => parseInt(m.id_med))
    );
    medicosDisponiblesCache = todos.filter(m => !yaAsignados.has(parseInt(m.id_med)));

    if (medicosDisponiblesCache.length === 0) {
        cuerpo.innerHTML = estadoVacio('bi-person-check', 'Sin médicos para vincular', 'Todos los médicos registrados ya están en este servicio', 5);
        aviso.style.display = 'block';
    }

    renderMedicosAsignar();
}

function renderMedicosAsignar() {
    const cuerpo = el('lista_medicos_asignar');
    if (!cuerpo) return;

    const q = (el('busc_medico_asignar')?.value || '').trim().toLowerCase();
    const filas = medicosDisponiblesCache.filter(m =>
        !q ||
        String(m.nombre_med || '').toLowerCase().includes(q) ||
        String(m.ci_med || '').toLowerCase().includes(q) ||
        String(m.especialidad || '').toLowerCase().includes(q)
    );

    texto('count_disponibles', fmtNum(filas.length === medicosDisponiblesCache.length
        ? medicosDisponiblesCache.length
        : filas.length + ' / ' + medicosDisponiblesCache.length));

    if (medicosDisponiblesCache.length > 0 && filas.length === 0) {
        cuerpo.innerHTML = estadoVacio('bi-search', 'Sin coincidencias', 'Pruebe con otro término de búsqueda', 5);
        return;
    }

    cuerpo.innerHTML = filas.map(m => {
        const nombre = m.nombre_med
            ? escapeHtml(m.nombre_med)
            : '<span class="text-muted fst-italic">Sin nombre (sin cuenta)</span>';
        const esp = (m.especialidad || '').trim();
        return `<tr class="cm-fila" data-id="${escapeHtml(m.id_med)}" tabindex="0" role="button">
            <td><span class="cm-id">${escapeHtml(m.id_med)}</span></td>
            <td class="cm-secundario">${escapeHtml(m.ci_med || 'N/A')}</td>
            <td class="cm-nombre">${nombre}</td>
            <td class="cm-secundario">${esp ? escapeHtml(esp) : '<span class="text-muted fst-italic">Sin especialidad</span>'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                        data-accion="asignar" data-id="${escapeHtml(m.id_med)}"
                        title="Asignar al servicio" aria-label="Asignar al servicio">
                    <i class="bi bi-link-45deg"></i> Asignar
                </button>
            </td>
        </tr>`;
    }).join('');
}

function asignarMedico(idMedico) {
    if (!idMedico || !servicioSeleccionadoId) {
        alert('No se identificó al médico o el servicio.');
        return;
    }
    const m = medicosDisponiblesCache.find(x => parseInt(x.id_med) === parseInt(idMedico));
    texto('seleccion_pendiente', m
        ? `Asignando a: ${m.nombre_med || 'médico sin nombre'}`
        : 'Asignando médico…');

    const formData = new FormData();
    formData.append('id_servicio', servicioSeleccionadoId);
    formData.append('id_medico', idMedico);
    formData.append('accion', 'asignar');

    const cuerpo = el('lista_medicos_asignar');
    if (cuerpo) {
        cuerpo.querySelectorAll('button[data-accion="asignar"]').forEach(b => { b.disabled = true; });
    }

    // La modal de re-autenticación se apila encima: se cierra la de médicos.
    const modalInstancia = bootstrap.Modal.getInstance(el('modalAsignarMedico'));
    if (modalInstancia) modalInstancia.hide();

    const recargar = function () {
        cargarKpis();
        cargarMedicos(servicioSeleccionadoId);
        medicosDisponiblesCache = medicosDisponiblesCache.filter(x => parseInt(x.id_med) !== parseInt(idMedico));
        abrirModalAsignarMedico();
    };

    solicitarConfirmacionAdmin(formData, '../controllers/procesarAsignacionMedicoServicio.php', recargar);
}

function abrirModalEditarMedico(id) {
    const m = medicosCache.find(x => parseInt(x.id_med) === parseInt(id));
    if (!m) return;
    const form = el('form_medico');
    if (form) form.reset();

    el('id_med_input').value = m.id_med;

    // Identidad: SOLO LECTURA, viene de la cuenta de usuario.
    el('identidad_medico_nombre').textContent = m.nombre_med || 'Sin nombre (sin cuenta de usuario)';
    el('identidad_medico_ci').textContent = m.ci_med || 'N/A';

    el('especialidad_input').value = m.especialidad || '';
    el('estatus_medico_input').value = m.estatus_medico || 'activo';

    const nombres = String(m.servicios || m.servicio || '')
        .split(',').map(x => x.trim()).filter(Boolean);
    el('servicios_medico_actuales').innerHTML =
        '<i class="bi bi-hospital me-1"></i>Servicios: ' +
        (nombres.length ? escapeHtml(nombres.join(', ')) : '<span class="text-muted">Ninguno</span>');

    el('modalMedicoLabel').textContent = 'Ficha del Médico';
    el('hint_medico').textContent = servNombre(servicioSeleccionadoId)
        ? `Servicio abierto: ${servNombre(servicioSeleccionadoId)}.`
        : 'Edite los datos profesionales de este médico.';
    mostrarModal('modalMedico');
}

function servNombre(id) {
    const s = serviciosCache.find(x => parseInt(x.id_servicio) === parseInt(id));
    return s ? s.servicio : '';
}

// ==========================================
// CONFIRMACIÓN DE ADMIN, GUARDADO Y ELIMINACIÓN
// ==========================================
function guardarServicio(event) {
    event.preventDefault();
    const formData = new FormData(el('form_servicio'));
    const modalInstance = bootstrap.Modal.getInstance(el('modalServicio'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, CTRL_SERVICIO, () => {
        cargarKpis();
        cargarServicios();
    });
}

function guardarEspecialidad(event) {
    event.preventDefault();
    if (!servicioSeleccionadoId) {
        alert('Seleccione un servicio antes de guardar la especialidad.');
        return;
    }
    const formData = new FormData(el('form_especialidad'));
    formData.append('id_servicio', servicioSeleccionadoId);
    const modalInstance = bootstrap.Modal.getInstance(el('modalEspecialidad'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, CTRL_ESPECIALIDAD, () => {
        cargarKpis();
        cargarEspecialidades(servicioSeleccionadoId);
    });
}

function guardarMedico(event) {
    event.preventDefault();
    if (!el('id_med_input').value) {
        alert('No se identificó al médico.');
        return;
    }
    // NO se envía id_servicio: esta vista solo edita la ficha profesional.
    // Cambiar los vínculos con servicios se hace con los botones de asignación.
    const formData = new FormData(el('form_medico'));
    const modalInstance = bootstrap.Modal.getInstance(el('modalMedico'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, CTRL_MEDICO, () => {
        cargarKpis();
        cargarMedicos(servicioSeleccionadoId);
    });
}

function confirmarEliminarServicio() {
    const formData = new FormData();
    formData.append('id_servicio', el('id_servicio_input').value);
    formData.append('action', 'delete');

    const modalInstance = bootstrap.Modal.getInstance(el('modalServicio'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, CTRL_SERVICIO, () => {
        servicioSeleccionadoId = null;
        cargarKpis();
        cargarServicios();
    });
}

function confirmarEliminarEspecialidad() {
    const formData = new FormData();
    formData.append('id_especialidad', el('id_especialidad_input').value);
    formData.append('action', 'delete');

    const modalInstance = bootstrap.Modal.getInstance(el('modalEspecialidad'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, CTRL_ESPECIALIDAD, () => {
        cargarKpis();
        cargarEspecialidades(servicioSeleccionadoId);
    });
}

// Desvincula al médico de ESTE servicio. No borra la ficha ni la cuenta:
// el médico sigue registrado y puede volver a vincularse a otro servicio.
function confirmarQuitarDelServicio() {
    const idMedico = el('id_med_input').value;
    if (!idMedico || !servicioSeleccionadoId) {
        alert('No se identificó el médico o el servicio.');
        return;
    }
    const formData = new FormData();
    formData.append('id_servicio', servicioSeleccionadoId);
    formData.append('id_medico', idMedico);
    formData.append('accion', 'quitar');

    const modalInstance = bootstrap.Modal.getInstance(el('modalMedico'));
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarAsignacionMedicoServicio.php', () => {
        cargarKpis();
        cargarMedicos(servicioSeleccionadoId);
    });
}
