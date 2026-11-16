// views/js/gestion_patologias.js
// Administracion del catalogo de patologias CIE-10.
// Depende de: js/csrf.js (escapeHtml, escapeJs, csrftoken en fetch),
//             js/reauth_admin.js (reauthAdminActivo), bootstrap.bundle.min.js.

const PAGINA_POR_DEFECTO = 50;

let patPaginaActual = 1;
let patTotalPaginas = 1;

document.addEventListener('DOMContentLoaded', function () {
    cargarPatologias();
    cargarEstadisticasPatologias();

    const btnAdd = document.getElementById('btn_add_patologia');
    if (btnAdd) btnAdd.addEventListener('click', abrirModalCrearPatologia);

    const inputBuscar = document.getElementById('buscar_patologia_input');
    if (inputBuscar) {
        let temporizador = null;
        inputBuscar.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                patPaginaActual = 1;
                cargarPatologias();
            }, 350);
        });
    }

    const btnAnterior = document.getElementById('btn_pagina_anterior');
    if (btnAnterior) {
        btnAnterior.addEventListener('click', function () {
            if (patPaginaActual > 1) {
                patPaginaActual--;
                cargarPatologias();
            }
        });
    }

    const btnSiguiente = document.getElementById('btn_pagina_siguiente');
    if (btnSiguiente) {
        btnSiguiente.addEventListener('click', function () {
            if (patPaginaActual < patTotalPaginas) {
                patPaginaActual++;
                cargarPatologias();
            }
        });
    }
});

// ===================== UTILIDADES =====================

function patSetKpi(id, valor) {
    const el = document.getElementById(id);
    if (el) el.textContent = valor;
}

function patActualizarPaginacion(total, pagina, totalPaginas) {
    const info = document.getElementById('info_paginacion');
    if (info) {
        const desde = total === 0 ? 0 : ((pagina - 1) * PAGINA_POR_DEFECTO) + 1;
        const hasta = Math.min(pagina * PAGINA_POR_DEFECTO, total);
        info.textContent = 'Mostrando ' + desde + '–' + hasta + ' de ' + total + ' Patologías';
    }

    const btnActual = document.getElementById('btn_pagina_actual');
    if (btnActual) btnActual.textContent = pagina + ' / ' + totalPaginas;

    const btnAnterior = document.getElementById('btn_pagina_anterior');
    if (btnAnterior) btnAnterior.disabled = (pagina <= 1);

    const btnSiguiente = document.getElementById('btn_pagina_siguiente');
    if (btnSiguiente) btnSiguiente.disabled = (pagina >= totalPaginas);
}

// ===================== CARGA =====================

function cargarPatologias() {
    const tbody = document.getElementById('tbody_patologias');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted small py-3">Cargando catálogo...</td></tr>';

    const inputBuscar = document.getElementById('buscar_patologia_input');
    const buscar = inputBuscar ? inputBuscar.value.trim() : '';

    const params = new URLSearchParams({
        pagina: patPaginaActual,
        por_pagina: PAGINA_POR_DEFECTO
    });
    if (buscar) params.set('buscar', buscar);

    fetch('../controllers/get_patologias.php?' + params.toString())
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || resp.success !== true) {
                throw new Error((resp && resp.message) || 'Respuesta invalida del servidor');
            }

            const filas = resp.data || [];
            patTotalPaginas = resp.total_paginas || 1;
            patPaginaActual = resp.pagina || 1;

            patSetKpi('kpi_total', Number(resp.total || 0).toLocaleString('es-VE'));
            patActualizarPaginacion(resp.total || 0, patPaginaActual, patTotalPaginas);

            tbody.innerHTML = '';

            if (filas.length > 0) {
                filas.forEach(function (p) {
                    const usos = Number(p.usos || 0);
                    const badgeUsos = usos > 0
                        ? '<span class="badge bg-success">' + escapeHtml(usos) + '</span>'
                        : '<span class="text-muted small">0</span>';

                    const celdaCodigo = p.codigo_cie10
                        ? '<span class="badge bg-light text-dark border">' + escapeHtml(p.codigo_cie10) + '</span>'
                        : '<span class="text-muted small">—</span>';

                    tbody.innerHTML +=
                        '<tr>' +
                            '<td class="fw-bold text-secondary px-2 py-2">' + escapeHtml(p.id_patologia) + '</td>' +
                            '<td class="px-2 py-2">' + celdaCodigo + '</td>' +
                            '<td class="px-2 py-2 fw-semibold text-dark">' + escapeHtml(p.patologia) + '</td>' +
                            '<td class="text-center px-2 py-2">' + badgeUsos + '</td>' +
                            '<td class="text-center px-1 py-2">' +
                                '<button class="btn btn-sm btn-primary rounded-pill px-2 py-0" ' +
                                        'onclick="abrirModalEditarPatologia(' +
                                            Number(p.id_patologia) + ', \'' + escapeJs(p.patologia) + '\', \'' +
                                            escapeJs(p.codigo_cie10) + '\', ' + usos + ')" ' +
                                        'title="Editar / Gestionar">' +
                                    '<i class="bi bi-pencil-square text-white"></i>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                });
            } else {
                const mensaje = buscar
                    ? 'No se encontraron patologías para «' + buscar + '»'
                    : 'El catálogo de patologías está vacío';
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted small py-3">' +
                    escapeHtml(mensaje) + '</td></tr>';
            }
        })
        .catch(function (err) {
            console.error('Error al cargar patologias:', err);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger small py-3">' +
                escapeHtml(err.message || 'Error al cargar el catálogo de patologías') + '</td></tr>';
        });
}

// KPIs globales del catálogo (no dependen de la página visible).
function cargarEstadisticasPatologias() {
    fetch('../controllers/get_estadisticas_cuadro.php?tipo=patologias')
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || resp.success !== true) return;
            const k = resp.data && resp.data.kpi ? resp.data.kpi : {};
            patSetKpi('kpi_con_codigo', Number(k.con_codigo || 0).toLocaleString('es-VE'));
            patSetKpi('kpi_sin_codigo', Number(k.sin_codigo || 0).toLocaleString('es-VE'));
            patSetKpi('kpi_en_uso', Number(k.en_uso || 0).toLocaleString('es-VE'));
            patSetKpi('kpi_asignaciones', Number(k.asignaciones || 0).toLocaleString('es-VE'));
        })
        .catch(function (err) { console.error('Error al cargar estadísticas:', err); });
}

function exportarCatalogoPatologias(formato) {
    const params = new URLSearchParams({ modo: 'patologias', formato: formato });
    const url = '/administracion_medica/controllers/exportar_estadisticas_gestion.php?' + params.toString();
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
window.exportarCatalogoPatologias = exportarCatalogoPatologias;

// ===================== MODAL =====================

function abrirModalCrearPatologia() {
    const form = document.getElementById('form_patologia');
    if (form) form.reset();

    const inputId = document.getElementById('id_patologia_input');
    if (inputId) inputId.value = '';

    const btnEliminar = document.getElementById('btn_eliminar_patologia');
    if (btnEliminar) btnEliminar.classList.add('d-none');

    const infoUsos = document.getElementById('info_usos_patologia');
    if (infoUsos) infoUsos.classList.add('d-none');

    const label = document.getElementById('modalPatologiaLabel');
    if (label) label.textContent = 'Crear Patología';

    const inputNombre = document.getElementById('patologia_input');
    if (inputNombre) setTimeout(function () { inputNombre.focus(); }, 400);

    new bootstrap.Modal(document.getElementById('modalPatologia')).show();
}

function abrirModalEditarPatologia(id, nombre, codigo, usos) {
    const form = document.getElementById('form_patologia');
    if (form) form.reset();

    const inputId = document.getElementById('id_patologia_input');
    if (inputId) inputId.value = id;

    const inputNombre = document.getElementById('patologia_input');
    if (inputNombre) inputNombre.value = nombre;

    const inputCodigo = document.getElementById('codigo_cie10_input');
    if (inputCodigo) inputCodigo.value = codigo;

    const btnEliminar = document.getElementById('btn_eliminar_patologia');
    if (btnEliminar) btnEliminar.classList.remove('d-none');

    // Una patología ya asignada a algún historial clínico no se puede borrar
    // (persona_patologia tiene ON DELETE CASCADE). Se explica en el modal
    // para que el motivo del rechazo no sorprenda al administrador.
    const cantidadUsos = Number(usos || 0);
    const infoUsos = document.getElementById('info_usos_patologia');
    if (infoUsos) {
        if (cantidadUsos > 0) {
            const cantidad = document.getElementById('info_usos_cantidad');
            if (cantidad) cantidad.textContent = cantidadUsos;
            infoUsos.classList.remove('d-none');
        } else {
            infoUsos.classList.add('d-none');
        }
    }

    const label = document.getElementById('modalPatologiaLabel');
    if (label) label.textContent = 'Editar Patología';

    new bootstrap.Modal(document.getElementById('modalPatologia')).show();
}

// ===================== CONFIRMACIÓN DE ADMIN =====================
// Flujo idéntico al de gestion_servicios.js / gestion_medicos.js.

let datosPendientesEnvio = null;
let urlControladorPendiente = null;
let callbackExito = null;

async function solicitarConfirmacionAdmin(formData, urlControlador, successCallback) {
    datosPendientesEnvio = formData;
    urlControladorPendiente = urlControlador;
    callbackExito = successCallback;

    if (await reauthAdminActivo()) {
        enviarAccionPendiente();
        return;
    }

    const inputPass = document.getElementById('password_admin_input');
    if (inputPass) inputPass.value = '';

    const errorPass = document.getElementById('error_admin_pass');
    if (errorPass) errorPass.classList.add('d-none');

    new bootstrap.Modal(document.getElementById('modalConfirmarAdmin')).show();
}

function enviarAccionPendiente() {
    fetch(urlControladorPendiente, {
        method: 'POST',
        body: datosPendientesEnvio
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data && data.success) {
            if (callbackExito) callbackExito();
        } else {
            alert((data && data.message) || 'No se pudo completar la operación');
        }
    })
    .catch(function (err) { console.error('Error en la petición:', err); });
}

document.addEventListener('DOMContentLoaded', function () {
    const btnConfirmarAccion = document.getElementById('btn_confirmar_accion');
    if (!btnConfirmarAccion) return;

    btnConfirmarAccion.addEventListener('click', function () {
        const input = document.getElementById('password_admin_input');
        const passwordAdmin = input ? input.value.trim() : '';

        if (passwordAdmin === '') {
            const errorPass = document.getElementById('error_admin_pass');
            if (errorPass) errorPass.classList.remove('d-none');
            return;
        }

        datosPendientesEnvio.set('password_admin', passwordAdmin);

        const modalAdminEl = document.getElementById('modalConfirmarAdmin');
        const modalInstance = bootstrap.Modal.getInstance(modalAdminEl);
        if (modalInstance) modalInstance.hide();

        enviarAccionPendiente();
    });
});

// ===================== GUARDAR / ELIMINAR =====================

function guardarPatologia(event) {
    event.preventDefault();

    const form = document.getElementById('form_patologia');
    if (!form) return;

    const nombre = document.getElementById('patologia_input');
    if (nombre && nombre.value.trim() === '') {
        alert('El nombre de la patología es obligatorio');
        return;
    }

    const formData = new FormData(form);

    const modalEl = document.getElementById('modalPatologia');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarPatologiaController.php', function () {
        cargarPatologias();
    });
}

function confirmarEliminarPatologia() {
    const inputId = document.getElementById('id_patologia_input');
    if (!inputId || !inputId.value) return;

    if (!confirm('¿Confirma que desea eliminar esta patología del catálogo?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id_patologia', inputId.value);
    formData.append('action', 'delete');

    const modalEl = document.getElementById('modalPatologia');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();

    solicitarConfirmacionAdmin(formData, '../controllers/procesarPatologiaController.php', function () {
        cargarPatologias();
    });
}
