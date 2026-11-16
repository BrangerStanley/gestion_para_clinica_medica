// views/js/reauth_admin.js
// Indica si la ventana de re-autenticación del admin sigue activa
// (para no abrir el modal de contraseña en cada acción).
async function reauthAdminActivo() {
    try {
        const res = await fetch('../controllers/reauthStatus.php', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) return false;
        const data = await res.json();
        return !!data.valid;
    } catch (e) {
        return false;
    }
}
