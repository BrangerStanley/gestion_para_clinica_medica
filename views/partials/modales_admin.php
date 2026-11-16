<?php
// views/partials/modales_admin.php
// Modal de re-autenticación del administrador (flujo de "Confirmación de Seguridad").
// Este partial reemplaza 10 copias idénticas del mismo bloque HTML.
//
// Uso:
//   include __DIR__ . '/partials/modales_admin.php';
//
// Opcionales (definir ANTES del include):
//   $modalAdminMensaje      Texto del párrafo de instrucción.
//   $modalAdminAviso        HTML extra a mostrar en el cuerpo (alert, etc.).
//   $modalAdminEtiquetaBoton Texto del botón de confirmación.
//   $modalAdminIdBoton      id del botón de confirmación.
//   $modalAdminClaseBoton   Clase de color del botón (por defecto btn-primary).
//   $modalAdminNombreInput  Name del input. Déjalo vacío cuando el envío se
//                           hace por fetch; ponlo si el input debe viajar en
//                           un <form> nativo (ver crear_usuario.php).
//
// Contrato que espera el JS compartido (js/gestion_core.js):
//   #modalConfirmarAdmin · #password_admin_input · #error_admin_pass · #btn_confirmar_accion

$modalAdminMensaje       = $modalAdminMensaje       ?? 'Por favor, ingrese su contraseña de administrador para autorizar esta acción:';
$modalAdminAviso         = $modalAdminAviso         ?? '';
$modalAdminEtiquetaBoton = $modalAdminEtiquetaBoton ?? 'Confirmar y Guardar';
$modalAdminIdBoton       = $modalAdminIdBoton       ?? 'btn_confirmar_accion';
$modalAdminClaseBoton    = $modalAdminClaseBoton    ?? 'btn-primary';
$modalAdminNombreInput   = $modalAdminNombreInput   ?? '';
?>
<!-- MODAL DE CONFIRMACIÓN DE ADMINISTRADOR -->
<div class="modal fade" id="modalConfirmarAdmin" tabindex="-1" aria-labelledby="modalAdminLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title h6" id="modalAdminLabel"><i class="bi bi-shield-lock-fill me-2"></i>Confirmación de Seguridad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
<?php if ($modalAdminAviso !== ''): ?>
                <?php echo $modalAdminAviso; ?>
<?php endif; ?>
                <p class="small text-muted mb-3"><?php echo htmlspecialchars($modalAdminMensaje, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="mb-3">
                    <label for="password_admin_input" class="form-label small fw-bold">Contraseña del Administrador</label>
                    <input type="password" id="password_admin_input" class="form-control" placeholder="******"
                           <?php echo $modalAdminNombreInput !== '' ? 'name="' . htmlspecialchars($modalAdminNombreInput, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                </div>
                <div id="error_admin_pass" class="text-danger small d-none"><i class="bi bi-exclamation-circle me-1"></i> Por favor ingrese su contraseña.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="<?php echo htmlspecialchars($modalAdminIdBoton, ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo htmlspecialchars($modalAdminClaseBoton, ENT_QUOTES, 'UTF-8'); ?> btn-sm px-3">
                    <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($modalAdminEtiquetaBoton, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
