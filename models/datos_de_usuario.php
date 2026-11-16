<?php
include_once __DIR__ . '/../config/Conexion.php';

// Refresca en cada request los permisos del rol actual: cualquier cambio
// hecho en la matriz de roles aplica sin cerrar la sesión.
if (function_exists('cargarPermisosSesion')) {
    cargarPermisosSesion($conexion);
}

if (isset($_SESSION['ci_user']) && $conexion instanceof IaaConnection) {
    $ci_buscar = $_SESSION['ci_user'];

    $sql = "SELECT u.id_usuario, u.ci_usuario AS ci_user, rl.nombre AS rol, u.estado,
                    p.nombre AS nombre_user, p.telefono AS telefono_user, p.correo, p.sexo,
                    p.fecha_nacimiento, p.estado_civil, p.nivel_academico AS nivel_educacion,
                    p.nacionalidad, p.pais_nacimiento,
                    p.estado_nacimiento AS lugar_nacimiento,
                    p.municipio_nacimiento,
                    p.licencia_conducir AS licencia_c,
                    f.fecha_ingreso AS ingreso_fp, f.id_rango AS rango,
                    f.id_ccp AS ccp, f.id_unidades AS unidades_especiales,
                    f.id_organismo AS organismo_institucion, f.estatus_servicio AS estatus,
                    d.direccion_completa AS direccion,
                    r.nombre_rango AS rango_nombre, r.linea_mando AS rango_linea_mando,
                    r.nivel_jerarquico AS rango_nivel,
                    c.nombre_ccp AS ccp_nombre, c.ubicacion_ccp AS ccp_ubicacion,
                    ue.nombre_unidad AS unidad_nombre, ue.sigla AS unidad_sigla,
                    oi.nombre_organismo AS organismo_nombre, oi.siglas AS organismo_siglas
             FROM persona p
             INNER JOIN usuario u ON u.id_persona = p.id_persona
             INNER JOIN rol rl ON rl.id = u.id_rol
             LEFT JOIN funcionario f ON f.id_usuario = u.id_usuario
             LEFT JOIN rango r ON f.id_rango = r.id_rango
             LEFT JOIN ccp c ON f.id_ccp = c.id_ccp
             LEFT JOIN unidades_especiales ue ON f.id_unidades = ue.id_unidades
             LEFT JOIN organismo_institucional oi ON f.id_organismo = oi.id_organismo
             LEFT JOIN direccion d ON p.id_direccion = d.id_direccion
             WHERE u.ci_usuario = ?";
    $stmt = $conexion->prepare($sql);
    
    if ($stmt !== false) {
        $stmt->bind_param("s", $ci_buscar);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows > 0) {
            $datos_usuario = $resultado->fetch_assoc();
            $es_campo_guardado = true;
        } else {
            $datos_usuario = [];
            $es_campo_guardado = false;
        }
        $stmt->close();
    } else {
        error_log('datos_de_usuario prepare falló: ' . $conexion->error);
        $datos_usuario = [];
        $es_campo_guardado = false;
    }
} else {
    $datos_usuario = [];
    $es_campo_guardado = false;
}