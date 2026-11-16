let medicosCache = [];
let buscarDebounce = null;

document.addEventListener('DOMContentLoaded', function() {
    cargarSelectServicios().then(() => cargarMedicos());

    // El botón "+" ahora es un enlace a crear_usuario.php: los médicos se crean
    // como cuenta con rol Médico, no desde esta vista.

    const btnRefrescar = document.getElementById('btn_refrescar');
    if (btnRefrescar) btnRefrescar.addEventListener('click', () => cargarSelectServicios().then(cargarMedicos));

    const selectServicio = document.getElementById('select_filtrar_servicio');
    if (selectServicio) selectServicio.addEventListener('change', cargarMedicos);

    const selectEstatus = document.getElementById('select_filtrar_estatus');
    if (selectEstatus) selectEstatus.addEventListener('change', cargarMedicos);

    // La búsqueda de médicos va al servidor (con debounce), no es de cliente.
    const inputBuscar = document.getElementById('busc_medico');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(buscarDebounce);
            buscarDebounce = setTimeout(cargarMedicos, 300);
        });
    }

    delegarTabla('tbody_medicos', function(accion) {
        if (accion) abrirModalEditarMedico(accion.dataset.id);
    });
});

// Limpia un select dejando solo su <option> placeholder (el primero).
// Evita que las opciones se acumulen en cada refresco.
function limpiarSelect(select) {
    if (!select) return;
    while (select.options.length > 1) {
        select.remove(1);
    }
    select.value = '';
}

// Catálogo de servicios, cacheado para el filtro y para los checks del modal.
let serviciosCatalogo = [];

// Carga los servicios en el filtro y como checkboxes en el modal
async function cargarSelectServicios() {
    let servicios = [];
    try {
        const res = await fetch('../controllers/get_servicios.php');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        servicios = await res.json();
    } catch (err) {
        // Un error de carga NO es lo mismo que "no hay servicios": se avisa.
        console.error('Error cargando servicios:', err);
        const pie = document.getElementById('pie_medicos');
        if (pie) pie.textContent = 'No se pudo cargar el catálogo de servicios';
        return;
    }

    if (!Array.isArray(servicios)) return;

    serviciosCatalogo = servicios;

    const selectFiltro = document.getElementById('select_filtrar_servicio');
    // Acordarse de la selección previa para no perderla al recargar
    const filtroPrevio = selectFiltro ? selectFiltro.value : '';
    limpiarSelect(selectFiltro);

    const contenedorChecks = document.getElementById('servicio_medico_checks');
    // Se reconstruye entero en cada carga: así no se acumulan duplicados.
    if (contenedorChecks) contenedorChecks.innerHTML = '';

    servicios.forEach(s => {
        if (selectFiltro) {
            const opt = document.createElement('option');
            opt.value = s.id_servicio;
            opt.textContent = s.servicio;
            selectFiltro.appendChild(opt);
        }

        if (contenedorChecks) {
            const idChk = 'chk_servicio_' + s.id_servicio;
            const div = document.createElement('div');
            div.className = 'form-check';

            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.className = 'form-check-input chk-servicio-medico';
            chk.id = idChk;
            chk.value = s.id_servicio;
            chk.name = 'id_servicios';

            const lbl = document.createElement('label');
            lbl.className = 'form-check-label';
            lbl.setAttribute('for', idChk);
            lbl.textContent = s.servicio;

            div.appendChild(chk);
            div.appendChild(lbl);
            contenedorChecks.appendChild(div);
        }
    });

    if (contenedorChecks && !servicios.length) {
        contenedorChecks.innerHTML =
            '<div class="text-muted small">No hay servicios en el catálogo. Cree uno primero en Gestión de Servicios.</div>';
    }

    if (selectFiltro && filtroPrevio) selectFiltro.value = filtroPrevio;
}

// Devuelve los ids marcados en el modal
function serviciosMarcados() {
    return Array.from(document.querySelectorAll('.chk-servicio-medico:checked'))
        .map(chk => parseInt(chk.value, 10))
        .filter(id => id > 0);
}

// Marca/desmarca los checks indicados
function marcarServicios(ids) {
    const deseados = new Set((Array.isArray(ids) ? ids : []).map(id => parseInt(id, 10)));
    document.querySelectorAll('.chk-servicio-medico').forEach(chk => {
        chk.checked = deseados.has(parseInt(chk.value, 10));
    });
}
function pintarKpisMedicos(kpi) {
    setKpi('kpi_medicos', fmtNum(kpi.medicos));
    setKpi('kpi_medicos_sub', 'registrados');
    setKpi('kpi_activos', fmtNum(kpi.activos));
    setKpi('kpi_activos_sub', `${fmtNum(kpi.inactivos)} inactivos`);
    setKpi('kpi_citas', fmtNum(kpi.citas));
    setKpi('kpi_citas_sub', `${fmtNum(kpi.hoy)} para hoy`);
    setKpi('kpi_atendidas', fmtNum(kpi.atendidas));
    setKpi('kpi_atendidas_sub', `de ${fmtNum(kpi.citas)} citas`);
    setKpi('kpi_programadas', fmtNum(kpi.programadas));
    setKpi('kpi_programadas_sub', 'pendientes');
    setKpi('kpi_canceladas', fmtNum(kpi.canceladas));
    setKpi('kpi_canceladas_sub', 'descartadas');
}

function cargarMedicos() {
    const tbody = document.getElementById('tbody_medicos');
    if (!tbody) return;
    tbody.innerHTML = esqueleto(6, 9);
    setTexto('pie_medicos', 'Cargando médicos…');
    setContador('count_medicos', '…');

    const params = new URLSearchParams();
    const selectServicio = document.getElementById('select_filtrar_servicio');
    const selectEstatus = document.getElementById('select_filtrar_estatus');
    const inputBuscar = document.getElementById('busc_medico');

    if (selectServicio && selectServicio.value) params.set('id_servicio', selectServicio.value);
    if (selectEstatus && selectEstatus.value) params.set('estatus', selectEstatus.value);
    if (inputBuscar && inputBuscar.value.trim()) params.set('buscar', inputBuscar.value.trim());

    // El subtítulo del panel resume el filtro activo.
    const partes = [];
    if (selectServicio && selectServicio.selectedIndex > 0) {
        partes.push('De: ' + selectServicio.options[selectServicio.selectedIndex].textContent);
    }
    if (selectEstatus && selectEstatus.value) {
        partes.push(selectEstatus.options[selectEstatus.selectedIndex].textContent);
    }
    setTexto('sub_filtro_medicos', partes.length ? partes.join(' · ') : 'Todos los médicos');

    const query = params.toString();
    const url = '../controllers/get_medicos.php' + (query ? '?' + query : '');

    Promise.all([
        fetch(url).then(r => {
            // Un 500 del endpoint ya viene con {success:false}; si no se revisa
            // aquí, se confunde con "no hay médicos" y se muestra vacío.
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        }),
        fetch('../controllers/get_estadisticas_cuadro.php?tipo=medicos').then(r => r.json()).catch(() => ({ success: false }))
    ])
    .then(([data, statsRes]) => {
        if (data && data.success === false) {
            throw new Error(data.error || 'El servidor rechazó la consulta de médicos.');
        }

        const stats = (statsRes && statsRes.success && statsRes.data) ? statsRes.data : { filas: {}, kpi: {} };
        pintarKpisMedicos(stats.kpi || {});
        const filas = stats.filas || {};

        medicosCache = Array.isArray(data) ? data : [];
        setContador('count_medicos', fmtNum(medicosCache.length));
        setTexto('pie_medicos', plural(medicosCache.length, 'médico encontrado', 'médicos encontrados'));

        if (medicosCache.length === 0) {
            tbody.innerHTML = estadoVacio('bi-person-x', 'Sin médicos con estos filtros',
                params.toString() ? 'Pruebe a cambiar el servicio o el estatus'
                                  : 'Los médicos se crean desde Gestión de Usuarios con rol "Médico"', 10);
            return;
        }

        tbody.innerHTML = medicosCache.map(m => {
            const cedulaTexto = m.ci_med ? m.ci_med : 'N/A';
            const estatus = m.estatus_medico || 'activo';
            const badgeEstatus = estatus === 'activo'
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>';
            const st = filas[m.id_med] || {};

            // El nombre viene de la cuenta (persona.nombre). Vacío = sin cuenta:
            // ese médico no puede entrar al sistema.
            const tieneCuenta = m.tiene_cuenta !== false;
            const etiquetaCuenta = tieneCuenta
                ? ''
                : ' <span class="badge bg-warning text-dark" title="Sin cuenta de usuario: no puede iniciar sesión">Sin cuenta</span>';
            const nombreVisible = m.nombre_med
                ? escapeHtml(m.nombre_med)
                : '<span class="text-muted fst-italic">Sin nombre (sin cuenta)</span>';

            const especialidad = (m.especialidad || '').trim();
            const etiquetaEsp = especialidad
                ? escapeHtml(especialidad)
                : '<span class="text-muted fst-italic">Sin especialidad</span>';

            // Con la pivote un médico puede tener varios servicios: se listan todos.
            const nombres = String(m.servicios || m.servicio || '')
                .split(',')
                .map(x => x.trim())
                .filter(Boolean);
            const etiquetaServicios = nombres.length
                ? nombres.map(n => `<span class="badge bg-secondary me-1">${escapeHtml(n)}</span>`).join('')
                : '<span class="text-muted">Sin servicio</span>';

            return `<tr>
                <td><span class="ui-id">${escapeHtml(m.id_med)}</span></td>
                <td class="ui-secundario">${escapeHtml(cedulaTexto)}</td>
                <td class="ui-nombre">${nombreVisible}${etiquetaCuenta}</td>
                <td class="ui-secundario">${etiquetaEsp}</td>
                <td class="ui-secundario">${etiquetaServicios}</td>
                <td class="text-center"><span class="ui-cifra">${escapeHtml(fmtNum(st.citas))}</span></td>
                <td class="text-center"><span class="ui-cifra" style="color:#198754;">${escapeHtml(fmtNum(st.atendidas))}</span></td>
                <td class="text-center"><span class="ui-cifra" style="color:#0d6efd;">${escapeHtml(fmtNum(st.proximas))}</span></td>
                <td>${badgeEstatus}</td>
                <td class="text-center">
                    <button type="button" class="ui-accion" data-accion="editar" data-id="${escapeHtml(m.id_med)}"
                            title="Editar ficha del médico" aria-label="Editar ficha del médico ${escapeHtml(cedulaTexto)}">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    })
    .catch(err => {
        console.error('Error médicos:', err);
        tbody.innerHTML = estadoVacio('bi-exclamation-triangle', 'No se pudieron cargar los médicos', 'Verifique su conexión e intente de nuevo', 10);
        setContador('count_medicos', '0');
        setTexto('pie_medicos', 'Error de carga');
    });
}

// El alta de médicos ya no existe en esta vista: se crean como cuenta con rol
// "Médico" desde gestion_usuarios / crear_usuario. Aquí solo se administra la
// ficha profesional y los servicios asignados.

function abrirModalEditarMedico(id) {
    const m = medicosCache.find(x => parseInt(x.id_med) === parseInt(id));
    if (!m) return;

    const form = document.getElementById('form_medico');
    if (form) form.reset();

    const inputId = document.getElementById('id_med_input');
    if (inputId) inputId.value = m.id_med;

    // Identidad: SOLO LECTURA. Viene de la cuenta (persona.nombre / usuario.ci).
    const nombreIdent = document.getElementById('identidad_medico_nombre');
    if (nombreIdent) {
        nombreIdent.textContent = m.nombre_med || 'Sin nombre (sin cuenta de usuario)';
    }
    const ciIdent = document.getElementById('identidad_medico_ci');
    if (ciIdent) ciIdent.textContent = m.ci_med || 'N/A';

    // Especialidad: ahora es la especialidad real, no el nombre.
    const inputEsp = document.getElementById('especialidad_input');
    if (inputEsp) inputEsp.value = m.especialidad || '';

    // Aviso de médico sin cuenta: no puede iniciar sesión ni ver citas.
    const aviso = document.getElementById('aviso_medico_sin_cuenta');
    if (aviso) {
        aviso.classList.toggle('d-none', m.tiene_cuenta !== false);
    }

    // La lista de servicios del médico viene desde la pivote.
    // 'id_servicios' es la fuente nueva; 'id_servicio' es el respaldo legado.
    const idsServicios = Array.isArray(m.id_servicios) && m.id_servicios.length
        ? m.id_servicios
        : (m.id_servicio ? [m.id_servicio] : []);
    marcarServicios(idsServicios);

    const selectEstatus = document.getElementById('estatus_medico_input');
    if (selectEstatus) selectEstatus.value = m.estatus_medico || 'activo';

    const btnEliminar = document.getElementById('btn_eliminar_medico');
    if (btnEliminar) btnEliminar.classList.remove('d-none');

    const label = document.getElementById('modalMedicoLabel');
    if (label) label.textContent = 'Ficha del Médico';

    mostrarModal('modalMedico');
}

function guardarMedico(event) {
    event.preventDefault();
    const form = document.getElementById('form_medico');
    const formData = new FormData(form);

    // FormData solo toma campos "exitentes". Los checks van como lista
    // id_servicios[], así que se agregan a mano.
    formData.delete('id_servicios');
    const ids = serviciosMarcados();
    if (ids.length === 0) {
        alert('Seleccione al menos un servicio para el médico.');
        return;
    }
    ids.forEach(id => formData.append('id_servicios[]', id));

    const modalEl = document.getElementById('modalMedico');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarMedicoController.php', () => {
        cargarMedicos();
    });
}

function confirmarEliminarMedico() {
    const id = document.getElementById('id_med_input').value;
    const formData = new FormData();
    formData.append('id_med', id);
    formData.append('action', 'delete');

    const modalEl = document.getElementById('modalMedico');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarMedicoController.php', () => {
        cargarMedicos();
    });
}

function exportarEstadisticas(modo, formato) {
    const params = new URLSearchParams({ modo, formato });
    const selServicio = document.getElementById('select_filtrar_servicio');
    const selEstatus = document.getElementById('select_filtrar_estatus');
    const inputBuscar = document.getElementById('busc_medico');
    if (selServicio && selServicio.value) params.set('id_servicio', selServicio.value);
    if (selEstatus && selEstatus.value) params.set('estatus', selEstatus.value);
    if (inputBuscar && inputBuscar.value.trim()) params.set('buscar', inputBuscar.value.trim());
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
