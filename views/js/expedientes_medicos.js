(function () {
    'use strict';

    const urlListado = '../controllers/get_expedientes_medicos.php';
    const controladorModulo = {
        medicina_interna: 'obtener_historia_medicina_interna.php',
        ginecologia: 'obtener_historia_ginecologia.php',
        odontologia: 'obtener_historia_odontologia.php',
        pediatria: 'obtener_historia_pediatria.php',
        psicologia: 'obtener_historia_psicologia.php',
        traumatologia: 'obtener_historia_traumatologia.php',
        laboratorio: 'obtener_historia_laboratorio.php'
    };
    const estado = { pagina: 1, total: 0, porPagina: 50, periodo: 'semana' };
    const ignorarDetalle = new Set([
        'cita', 'paciente', 'puede_editar', 'solo_lectura_motivo',
        'consultas_anteriores', 'evoluciones_anteriores', 'sesiones',
        'odontogramas', 'tiene_historia_previa', 'tiene_patologias',
        'sin_patologias', 'perfil'
    ]);
    const etiquetas = {
        nombre: 'Nombre', ci_usuario: 'Cédula', fecha_nacimiento: 'Fecha de nacimiento',
        edad: 'Edad', sexo: 'Sexo', telefono: 'Teléfono', direccion: 'Dirección',
        fecha_ingreso: 'Fecha de ingreso', nombre_servicio: 'Servicio',
        nro_solicitud: 'Solicitud', fecha_cita: 'Fecha de la cita', hora_cita: 'Hora',
        estatus_cita: 'Estado de la cita', motivo_consulta: 'Motivo de consulta',
        antecedentes: 'Antecedentes', antecedentes_personales: 'Antecedentes personales',
        antecedentes_patologicos: 'Antecedentes patológicos',
        antecedentes_familiares: 'Antecedentes familiares',
        antecedentes_prenatales: 'Antecedentes prenatales',
        antecedentes_neonatales: 'Antecedentes neonatales',
        antecedentes_alimentarios: 'Antecedentes alimentarios',
        desarrollo_psicomotor: 'Desarrollo psicomotor', inmunizaciones: 'Inmunizaciones',
        enfermedad_actual: 'Enfermedad actual', examen_fisico: 'Examen físico',
        examen_medico: 'Examen médico', examen: 'Examen', examen_boca: 'Examen de boca',
        diagnostico: 'Diagnóstico', tratamiento: 'Tratamiento', plan: 'Plan',
        idx_texto: 'Impresión diagnóstica', resultados_laboratorio: 'Resultados de laboratorio',
        observaciones: 'Observaciones', estudios: 'Estudios complementarios',
        ecosonograma: 'Ecosonograma', laboratorio: 'Laboratorio',
        menarquia: 'Menarquia', fur: 'Última menstruación', fpp: 'Fecha probable de parto',
        gestas: 'Gestas', paras: 'Partos', cesareas: 'Cesáreas', abortos: 'Abortos',
        peso: 'Peso', estatura: 'Estatura', nombre_madre: 'Nombre de la madre',
        medicamentos: 'Medicamentos', intensidad: 'Intensidad',
        estatus_resultado: 'Estado de resultados', observaciones_generales: 'Observaciones generales',
        firma_bioanalista: 'Firma del bioanalista', fecha_validacion: 'Fecha de validación',
        fecha_registro: 'Fecha de registro', piezas_afectadas: 'Piezas afectadas',
        numero_fdi: 'Pieza dental', estado: 'Estado', alergias: 'Alergias',
        diabetes: 'Diabetes', anemia: 'Anemia', artritis: 'Artritis',
        varices: 'Várices', nausea: 'Náuseas', problemas_cardiacos: 'Problemas cardíacos',
        toma_medicamentos: 'Toma medicamentos', ptisis: 'Ptisis',
        gingivitis: 'Gingivitis', gingivorragia: 'Gingivorragia',
        epitelio_alterado: 'Epitelio alterado', sangrado: 'Sangrado',
        patologias: 'Patologías registradas', antecedentes_compartidos: 'Antecedentes del paciente',
        antecedentes_documento: 'Antecedentes del área', piezas: 'Odontograma de la cita',
        orden: 'Orden de laboratorio', historia: 'Historia de laboratorio',
        hematologia: 'Hematología', orina: 'Examen de orina', heces: 'Examen de heces',
        contenido: 'Detalle', tipo: 'Tipo'
    };

    function nodo(id) {
        return document.getElementById(id);
    }

    function estadoCita(estado) {
        const etiquetasEstado = {
            programada: 'Programada',
            completada: 'Atendida',
            cancelada: 'Cancelada',
            no_asistio: 'No asistió'
        };
        return etiquetasEstado[estado] || estado || 'Sin estado';
    }

    function escapar(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (caracter) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[caracter];
        });
    }

    function textoCampo(clave) {
        if (etiquetas[clave]) return etiquetas[clave];
        return clave.replace(/_/g, ' ').replace(/\b\w/g, function (letra) { return letra.toUpperCase(); });
    }

    function fechaLegible(fecha) {
        if (!fecha) return '—';
        const partes = String(fecha).slice(0, 10).split('-');
        return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : String(fecha);
    }

    function fechaSemana() {
        const ahora = new Date();
        const lunes = new Date(ahora);
        lunes.setDate(ahora.getDate() - ((ahora.getDay() + 6) % 7));
        const domingo = new Date(lunes);
        domingo.setDate(lunes.getDate() + 6);
        return { desde: isoFecha(lunes), hasta: isoFecha(domingo) };
    }

    function isoFecha(fecha) {
        return [fecha.getFullYear(), String(fecha.getMonth() + 1).padStart(2, '0'), String(fecha.getDate()).padStart(2, '0')].join('-');
    }

    function actualizarFiltros(data) {
        const servicioActual = nodo('filtro_servicio').value;
        const medicoActual = nodo('filtro_medico').value;
        const especialidadActual = nodo('filtro_especialidad').value;
        nodo('filtro_servicio').innerHTML = '<option value="">Todos</option>' + data.servicios.map(function (servicio) {
            return '<option value="' + escapar(servicio.id_servicio) + '">' + escapar(servicio.nombre_servicio) + '</option>';
        }).join('');
        nodo('filtro_medico').innerHTML = '<option value="">Todos</option>' + data.medicos.map(function (medico) {
            return '<option value="' + escapar(medico.id_medico) + '">' + escapar(medico.nombre) + '</option>';
        }).join('');
        nodo('filtro_especialidad').innerHTML = '<option value="">Todas</option>' + data.especialidades.map(function (especialidad) {
            return '<option value="' + escapar(especialidad) + '">' + escapar(especialidad) + '</option>';
        }).join('');
        nodo('filtro_servicio').value = servicioActual;
        nodo('filtro_medico').value = medicoActual;
        nodo('filtro_especialidad').value = especialidadActual;
    }

    async function cargarExpedientes() {
        const desde = nodo('filtro_desde').value;
        const hasta = nodo('filtro_hasta').value;
        if (estado.periodo === 'personalizado' && (!desde || !hasta || desde > hasta)) {
            nodo('resumen_expedientes').textContent = 'Indique un rango de fechas válido.';
            nodo('lista_expedientes').innerHTML = '';
            return;
        }

        const parametros = new URLSearchParams({
            periodo: estado.periodo,
            pagina: String(estado.pagina),
            desde: desde,
            hasta: hasta,
            id_servicio: nodo('filtro_servicio').value,
            id_medico: nodo('filtro_medico').value,
            especialidad: nodo('filtro_especialidad').value
        });
        nodo('lista_expedientes').innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Cargando expedientes…</div>';
        nodo('resumen_expedientes').textContent = 'Consultando…';

        try {
            const respuesta = await fetch(urlListado + '?' + parametros.toString(), { headers: { Accept: 'application/json' } });
            const json = await respuesta.json();
            if (!respuesta.ok || !json.success) throw new Error(json.message || 'No se pudieron cargar los expedientes.');
            const data = json.data;
            estado.total = Number(data.total || 0);
            estado.porPagina = Number(data.por_pagina || 50);
            actualizarFiltros(data);
            nodo('filtro_desde').value = data.desde;
            nodo('filtro_hasta').value = data.hasta;
            dibujarExpedientes(data.expedientes || []);
            actualizarPaginacion(data);
            nodo('resumen_expedientes').textContent = estado.total + (estado.total === 1 ? ' expediente encontrado' : ' expedientes encontrados')
                + ' · ' + fechaLegible(data.desde) + ' al ' + fechaLegible(data.hasta);
        } catch (error) {
            nodo('lista_expedientes').innerHTML = '<div class="alert alert-danger" role="alert">' + escapar(error.message) + '</div>';
            nodo('resumen_expedientes').textContent = 'No fue posible consultar los expedientes.';
        }
    }

    function actualizarPaginacion(data) {
        const paginas = Math.max(1, Math.ceil(estado.total / estado.porPagina));
        nodo('pagina_actual').textContent = 'Página ' + data.pagina + ' de ' + paginas;
        nodo('pagina_anterior').disabled = Number(data.pagina) <= 1;
        nodo('pagina_siguiente').disabled = Number(data.pagina) >= paginas;
    }

    function dibujarExpedientes(expedientes) {
        if (!expedientes.length) {
            nodo('lista_expedientes').innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-folder2 fs-2 d-block mb-2"></i>No hay expedientes guardados para los filtros seleccionados.</div></div>';
            return;
        }

        const grupos = new Map();
        expedientes.forEach(function (expediente) {
            const clave = [expediente.id_servicio, expediente.id_medico, expediente.especialidad].join('|');
            if (!grupos.has(clave)) grupos.set(clave, []);
            grupos.get(clave).push(expediente);
        });

        nodo('lista_expedientes').innerHTML = Array.from(grupos.values()).map(function (grupo) {
            const primero = grupo[0];
            return '<section class="card expedientes-grupo shadow-sm mb-3">' +
                '<div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">' +
                '<div><strong>' + escapar(primero.servicio) + '</strong><span class="text-muted"> · ' +
                escapar(primero.especialidad) + ' · ' + escapar(primero.medico) + '</span></div>' +
                '<span class="badge text-bg-light">' + grupo.length + (grupo.length === 1 ? ' expediente' : ' expedientes') + '</span></div>' +
                '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>' +
                '<th>Fecha</th><th>Paciente</th><th>Estado de cita</th><th class="text-end">Acción</th></tr></thead><tbody>' +
                grupo.map(function (expediente) {
                    return '<tr class="expediente-fila"><td>' + escapar(fechaLegible(expediente.fecha_cita)) +
                        '<div class="small text-muted">' + escapar(String(expediente.hora_cita || '').slice(0, 5)) + '</div></td>' +
                        '<td><div class="fw-semibold">' + escapar(expediente.paciente) + '</div><div class="small text-muted">Cédula: ' + escapar(expediente.cedula_paciente) + '</div></td>' +
                        '<td>' + escapar(estadoCita(expediente.estatus_cita)) + '</td>' +
                        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-ver-expediente="' +
                        escapar(expediente.id_cita) + '" data-modulo="' + escapar(expediente.modulo) + '" data-paciente="' +
                        escapar(expediente.paciente) + '"><i class="bi bi-eye me-1"></i>Ver expediente</button></td></tr>';
                }).join('') + '</tbody></table></div></section>';
        }).join('');
    }

    function valorPresentable(valor) {
        if (valor === null || valor === undefined || valor === '') return '—';
        if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
        if (typeof valor === 'string') {
            const json = valor.trim();
            if ((json.startsWith('{') && json.endsWith('}')) || (json.startsWith('[') && json.endsWith(']'))) {
                try { return valorPresentable(JSON.parse(json)); } catch (error) { return valor; }
            }
            return valor;
        }
        return valor;
    }

    function dibujarValor(clave, valor) {
        valor = valorPresentable(valor);
        if (valor === '—' || valor === null || valor === undefined) {
            return '<div class="col-md-6"><div class="small text-muted">' + escapar(textoCampo(clave)) + '</div><div>—</div></div>';
        }
        if (Array.isArray(valor)) {
            if (!valor.length) return '';
            return '<div class="col-12"><div class="small text-muted mb-1">' + escapar(textoCampo(clave)) + '</div>' +
                '<div class="row g-2">' + valor.map(function (elemento, indice) {
                    if (elemento && typeof elemento === 'object') {
                        return '<div class="col-12"><div class="border rounded p-2"><strong>' + escapar(textoCampo(clave)) + ' ' + (indice + 1) +
                            '</strong><div class="row g-2 mt-1">' + dibujarObjeto(elemento, true) + '</div></div></div>';
                    }
                    return '<div class="col-12 expediente-dato">' + escapar(elemento) + '</div>';
                }).join('') + '</div></div>';
        }
        if (valor && typeof valor === 'object') {
            const detalle = dibujarObjeto(valor, true);
            return detalle ? '<div class="col-12"><div class="small text-muted mb-1">' + escapar(textoCampo(clave)) +
                '</div><div class="row g-2">' + detalle + '</div></div>' : '';
        }
        return '<div class="col-md-6"><div class="small text-muted">' + escapar(textoCampo(clave)) +
            '</div><div class="expediente-dato">' + escapar(valor) + '</div></div>';
    }

    function dibujarObjeto(objeto, anidado) {
        if (!objeto || typeof objeto !== 'object') return '';
        return Object.keys(objeto).filter(function (clave) {
            return !clave.startsWith('id_') && clave !== 'id' && clave !== 'puede_editar';
        }).map(function (clave) {
            return dibujarValor(clave, objeto[clave]);
        }).join('');
    }

    function cajaDatos(titulo, objeto) {
        if (!objeto || typeof objeto !== 'object') return '';
        const contenido = dibujarObjeto(objeto, false);
        return contenido ? '<div class="col-12"><section class="expediente-seccion"><h3>' + escapar(titulo) +
            '</h3><div class="row g-3">' + contenido + '</div></section></div>' : '';
    }

    function mostrarDetalle(data, nombrePaciente) {
        const paciente = data.paciente || {};
        const cita = data.cita || {};
        const camposPaciente = Object.assign({}, paciente);
        const camposCita = Object.assign({}, cita);
        delete camposPaciente.id_persona;
        delete camposCita.id_cita;
        delete camposCita.id_servicio;
        nodo('modalExpedienteLabel').textContent = 'Expediente de ' + (paciente.nombre || nombrePaciente || 'paciente');
        nodo('modalExpedienteSubtitulo').textContent = [
            cita.nombre_servicio,
            fechaLegible(cita.fecha_cita),
            cita.hora_cita ? String(cita.hora_cita).slice(0, 5) : ''
        ].filter(Boolean).join(' · ');

        let html = '<div class="row g-3">' +
            cajaDatos('Datos del paciente', camposPaciente) +
            cajaDatos('Datos de la consulta', camposCita);
        Object.keys(data).forEach(function (clave) {
            if (ignorarDetalle.has(clave) || clave === 'cita' || clave === 'paciente') return;
            html += cajaDatos(textoCampo(clave), data[clave]);
        });
        nodo('detalle_expediente').innerHTML = html + '</div>';
    }

    async function abrirExpediente(boton) {
        const modulo = boton.dataset.modulo;
        const controlador = controladorModulo[modulo];
        if (!controlador) {
            nodo('detalle_expediente').innerHTML = '<div class="alert alert-warning">No se reconoce el tipo de expediente.</div>';
            bootstrap.Modal.getOrCreateInstance(nodo('modalExpediente')).show();
            return;
        }
        nodo('detalle_expediente').innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Cargando expediente…</div>';
        bootstrap.Modal.getOrCreateInstance(nodo('modalExpediente')).show();
        try {
            const respuesta = await fetch('../controllers/' + controlador + '?id_cita=' + encodeURIComponent(boton.dataset.verExpediente), {
                headers: { Accept: 'application/json' }
            });
            const json = await respuesta.json();
            if (!respuesta.ok || !json.success || !json.data) throw new Error(json.message || 'No se pudo cargar el expediente.');
            mostrarDetalle(json.data, boton.dataset.paciente);
        } catch (error) {
            nodo('detalle_expediente').innerHTML = '<div class="alert alert-danger" role="alert">' + escapar(error.message) + '</div>';
        }
    }

    function aplicarPeriodo(periodo) {
        estado.periodo = periodo;
        estado.pagina = 1;
        if (periodo === 'hoy') {
            const hoy = isoFecha(new Date());
            nodo('filtro_desde').value = hoy;
            nodo('filtro_hasta').value = hoy;
        } else if (periodo === 'semana') {
            const semana = fechaSemana();
            nodo('filtro_desde').value = semana.desde;
            nodo('filtro_hasta').value = semana.hasta;
        }
        cargarExpedientes();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const semana = fechaSemana();
        nodo('filtro_desde').value = semana.desde;
        nodo('filtro_hasta').value = semana.hasta;
        document.querySelectorAll('[data-periodo]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                aplicarPeriodo(boton.dataset.periodo);
            });
        });
        nodo('btn_buscar_expedientes').addEventListener('click', function () {
            estado.periodo = 'personalizado';
            estado.pagina = 1;
            cargarExpedientes();
        });
        nodo('btn_actualizar_expedientes').addEventListener('click', cargarExpedientes);
        ['filtro_servicio', 'filtro_especialidad', 'filtro_medico'].forEach(function (id) {
            nodo(id).addEventListener('change', function () {
                estado.pagina = 1;
                cargarExpedientes();
            });
        });
        nodo('pagina_anterior').addEventListener('click', function () {
            if (estado.pagina > 1) {
                estado.pagina--;
                cargarExpedientes();
            }
        });
        nodo('pagina_siguiente').addEventListener('click', function () {
            if (estado.pagina * estado.porPagina < estado.total) {
                estado.pagina++;
                cargarExpedientes();
            }
        });
        nodo('lista_expedientes').addEventListener('click', function (evento) {
            const boton = evento.target.closest('[data-ver-expediente]');
            if (boton) abrirExpediente(boton);
        });
        cargarExpedientes();
    });
})();
