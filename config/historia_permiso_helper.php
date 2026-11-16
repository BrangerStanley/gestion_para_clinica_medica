<?php
// config/historia_permiso_helper.php
//
// Reglas de edición de las historias clínicas del médico.
//
// Una historia guardada deja de ser editable para el médico cuando la cita
// ya no es del día actual. A partir de ese momento solo el administrador
// (rol 5) puede corregirla; supervisores y demás perfiles la ven en modo
// solo lectura. El formulario y el controlador que guarda usan esta misma
// regla, de modo que no se puede saltar desde el navegador.

if (!function_exists('historia_puede_editar')) {
    /**
     * @param string|null $fecha_cita Fecha de la cita (Y-m-d).
     * @param int|null    $rol        Rol del usuario; por defecto el de sesión.
     */
    function historia_puede_editar($fecha_cita, $rol = null, $estatus_cita = null)
    {
        if ($rol === null) {
            $rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;
        }
        $rol = (int)$rol;

        // Una cita cancelada o con inasistencia no se atiende: solo se consulta.
        if ($estatus_cita !== null) {
            $est = strtolower(trim((string)$estatus_cita));
            if ($est === 'cancelada' || $est === 'no_asistio') {
                return false;
            }
        }

        // El administrador puede corregir cualquier historia.
        if ($rol === 5) {
            return true;
        }

        // Solo el médico (rol 6) y únicamente el mismo día de la cita.
        if ($rol !== 6) {
            return false;
        }

        $fecha = substr(trim((string)$fecha_cita), 0, 10);
        if ($fecha === '' || $fecha === '0000-00-00') {
            return false;
        }

        return $fecha === date('Y-m-d');
    }
}

if (!function_exists('historia_motivo_bloqueo')) {
    /**
     * Texto que se muestra al usuario cuando el formulario es solo lectura.
     */
    function historia_motivo_bloqueo($fecha_cita, $rol = null, $estatus_cita = null)
    {
        if ($rol === null) {
            $rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;
        }
        $rol = (int)$rol;

        // Una cita cancelada o con inasistencia no se atiende: solo se consulta,
        // incluso para el administrador.
        if ($estatus_cita !== null) {
            $est = strtolower(trim((string)$estatus_cita));
            if ($est === 'cancelada') {
                return 'La cita fue cancelada; la historia queda solo para consulta.';
            }
            if ($est === 'no_asistio') {
                return 'El paciente no asistió a la cita; la historia queda solo para consulta.';
            }
        }

        // El administrador puede corregir cualquier otra historia.
        if ($rol === 5) {
            return '';
        }

        if ($rol !== 6) {
            return 'Su perfil solo puede consultar las historias clínicas.';
        }

        return 'La cita no es de hoy. La historia ya fue guardada y queda como seguimiento de solo lectura.';
    }
}
