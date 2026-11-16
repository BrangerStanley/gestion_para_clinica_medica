/* ============================================================
   gestion_core.js · núcleo compartido del panel de administración
   ------------------------------------------------------------
   Carga en views/partials/head_admin.php, antes que el JS de cada vista.
   Solo define funciones globales (no toca el DOM al cargarse), por eso
   puede ir en el <head> y garantizar que existan antes de que se parsee
   el script de la vista.

   Reemplaza a copias duplicadas en ~20 ficheros. NO redefinir aquí nada
   que ya exista en csrf.js (escapeHtml / escapeJs).
   ============================================================ */

/* ------------------------------------------------------------
   1. Dropdowns del sidebar
   Acepta las dos firmas que se usaban en el proyecto:
     toggleDropdown('dropdownCuentas', event)   -> moderna
     toggleDropdown(event)                     -> heredada
   Al abrir uno se cierra el resto, que es el comportamiento que ya
   implementaban maintenance.js y reportes.js.
   ------------------------------------------------------------ */
function toggleDropdown(idOrEvent, maybeEvent) {
  var event = maybeEvent || (idOrEvent && idOrEvent.preventDefault ? idOrEvent : null);
  var id = maybeEvent ? idOrEvent : (event && event.currentTarget
    ? (event.currentTarget.getAttribute('onclick') || '').match(/'([^']+)'/)
      ? (event.currentTarget.getAttribute('onclick').match(/'([^']+)'/) || [])[1]
      : null
    : null);

  if (event) event.preventDefault();
  if (!id) return;

  var elemento = document.getElementById(id);
  if (!elemento) return;

  var mostrar = elemento.style.display !== 'block';

  document.querySelectorAll('.dropdown-content').forEach(function(otro) {
    if (otro !== elemento) {
      otro.classList.remove('show');
      otro.style.display = 'none';
    }
  });

  elemento.classList.toggle('show', mostrar);
  elemento.style.display = mostrar ? 'block' : 'none';
}

/* ------------------------------------------------------------
   2. Formato
   ------------------------------------------------------------ */
function fmtNum(valor) {
  return Number(valor || 0).toLocaleString('es-VE');
}

function fmtMoney(valor) {
  return '$' + Number(valor || 0).toFixed(2);
}

function plural(n, uno, varios) {
  return fmtNum(n) + ' ' + (n === 1 ? uno : varios);
}

function setKpi(id, valor) {
  var nodo = document.getElementById(id);
  if (nodo) nodo.textContent = valor;
}

function setTexto(id, valor) {
  var nodo = document.getElementById(id);
  if (nodo) nodo.textContent = valor;
}

function setContador(id, valor) {
  var nodo = document.getElementById(id);
  if (nodo) nodo.textContent = valor;
}

function mostrarModal(modalId) {
  var el = document.getElementById(modalId);
  if (el) new bootstrap.Modal(el).show();
}

/* ------------------------------------------------------------
   3. Estados de tabla
   ------------------------------------------------------------ */
function esqueleto(filas, cols) {
  var html = '';
  for (var i = 0; i < filas; i++) {
    html += '<tr aria-hidden="true"><td colspan="' + cols + '" style="border:0;padding:.2rem .5rem;">' +
      '<span class="ui-skel" style="width:' + (48 + ((i * 17) % 46)) + '%"></span></td></tr>';
  }
  return html;
}

function estadoVacio(icono, titulo, detalle, cols) {
  return '<tr><td colspan="' + cols + '" style="border:0;">' +
    '<div class="ui-vacio"><i class="bi ' + icono + '"></i>' +
    '<p>' + escapeHtml(titulo) + '</p>' +
    '<span>' + escapeHtml(detalle) + '</span></div></td></tr>';
}

/* Pie "Mostrando X de Y" de los paneles filtrados. */
function pieFiltrado(idPie, visibles, total, singular, plural_) {
  var nodo = document.getElementById(idPie);
  if (!nodo) return;
  nodo.textContent = (visibles < total)
    ? 'Mostrando ' + plural(visibles, 'resultado', 'resultados') +
      ' de ' + plural(total, singular, plural_)
    : plural(total, singular, plural_);
}

/* Clic y teclado (Enter / Espacio) sobre filas de tabla, sin onclick en línea. */
function delegarTabla(idTbody, alInteractuar) {
  var tbody = document.getElementById(idTbody);
  if (!tbody) return;

  tbody.addEventListener('click', function(e) {
    var accion = e.target.closest('[data-accion]');
    var fila = e.target.closest('tr[data-id]');
    alInteractuar(accion, fila);
  });

  tbody.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var fila = e.target.closest('tr[data-id]');
    if (!fila || e.target !== fila) return;
    e.preventDefault();
    alInteractuar(null, fila);
  });
}

/* ------------------------------------------------------------
   4. Re-autenticación del administrador
   Flujo único: guarda la acción pendiente, muestra el modal
   (partials/modales_admin.php) y la envía al confirmarla.
   ------------------------------------------------------------ */
var adminAccionPendiente = null;
var adminUrlPendiente = null;
var adminCallbackExito = null;

function solicitarConfirmacionAdmin(formData, urlControlador, successCallback) {
  adminAccionPendiente    = formData;
  adminUrlPendiente       = urlControlador;
  adminCallbackExito      = successCallback;

  var modalEl = document.getElementById('modalConfirmarAdmin');
  var inputPass = document.getElementById('password_admin_input');
  var errorPass = document.getElementById('error_admin_pass');

  if (inputPass) inputPass.value = '';
  if (errorPass) errorPass.classList.add('d-none');

  // Sin modal en esta vista: no hay nada que confirmar.
  if (!modalEl) return false;

  if (typeof reauthAdminActivo === 'function') {
    reauthAdminActivo().then(function(activo) {
      if (activo) {
        enviarAccionPendiente();
      } else {
        new bootstrap.Modal(modalEl).show();
      }
    });
  } else {
    new bootstrap.Modal(modalEl).show();
  }
  return true;
}

function enviarAccionPendiente() {
  if (!adminUrlPendiente || !adminAccionPendiente) return;

  fetch(adminUrlPendiente, { method: 'POST', body: adminAccionPendiente })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data && data.success) {
        var cb = adminCallbackExito;
        adminCallbackExito = null;
        if (cb) cb();
      } else {
        alert((data && data.message) || 'No se pudo completar la acción.');
      }
    })
    .catch(function(err) { console.error('Error en la petición:', err); });
}

// Un único listener para todas las vistas, en vez de uno por fichero.
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('btn_confirmar_accion');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var inputPass = document.getElementById('password_admin_input');
    var errorPass = document.getElementById('error_admin_pass');
    var password = inputPass ? inputPass.value.trim() : '';

    if (password === '') {
      if (errorPass) errorPass.classList.remove('d-none');
      if (inputPass) inputPass.focus();
      return;
    }
    if (!adminAccionPendiente) return;

    adminAccionPendiente.set('password_admin', password);

    var modalEl = document.getElementById('modalConfirmarAdmin');
    var inst = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
    if (inst) inst.hide();

    enviarAccionPendiente();
  });
});
