<?php
// controllers/obtener_historia_traumatologia.php
// GET ?id_cita=NN
// Carga el estado del formulario de Traumatología y Ortopedia de UNA cita:
//   - datos del paciente (solicitud -> funcionario) + datos de nacimiento
//     y nacionalidad tomados de la ficha (persona, solo lectura)
//   - historia médica ya guardada en esa cita (modo edición)
//   - antecedentes del área para precargar (de la consulta anterior más
//     reciente del paciente en el servicio, si esta cita es la primera vez)
//   - evoluciones anteriores del paciente en el servicio (hoja 2 del papel)
//   - patologías del paciente (bloque compartido entre servicios)
// Salida: { success, data:{...} }
//
// Ver: docs/PLAN_TRAUMATOLOGIA.md

ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/seguridad.php';
require_once __DIR__ . '/../config/nota_clinica_helper.php';
require_once __DIR__ . '/../config/historia_permiso_helper.php';
requireSesion();

require_once __DIR__ . '/../config/Conexion.php';

if (!($conexion instanceof IaaConnection)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

// Solo médicos, supervisores y administradores.
$rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;
if (!in_array($rol, [4, 5, 6], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$id_cita = isset($_GET['id_cita']) ? intval($_GET['id_cita']) : 0;
if ($id_cita <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cita no válida.']);
    exit;
}

try {
    // ------------------------------------------------------------------
    // 1. Datos de la cita y del paciente
    // ------------------------------------------------------------------
    $stmt = $conexion->prepare(
        "SELECT c.id_cita, c.id_solicitud, c.id_servicio, c.id_medico,
                c.fecha_cita, c.hora_cita, c.estatus_cita,
                s.nro_solicitud,
                u.ci_usuario,
                p.id_persona, p.nombre, p.fecha_nacimiento, p.sexo, p.telefono,
                p.nacionalidad, p.pais_nacimiento,
                enac.estado AS estado_nacimiento,
                mnac.municipio AS municipio_nacimiento,
                d.direccion_completa,
                sv.nombre_servicio
         FROM cita c
         INNER JOIN solicitud s   ON c.id_solicitud = s.id_solicitud
         INNER JOIN funcionario f  ON s.id_funcionario = f.id_funcionario
         INNER JOIN usuario u      ON f.id_usuario = u.id_usuario
         INNER JOIN persona p      ON u.id_persona = p.id_persona
         LEFT JOIN estado enac     ON p.estado_nacimiento = enac.id_estado
         LEFT JOIN municipio mnac  ON p.municipio_nacimiento = mnac.id_municipio
         LEFT JOIN direccion d     ON p.id_direccion = d.id_direccion
         LEFT JOIN servicio sv     ON c.id_servicio = sv.id_servicio
         WHERE c.id_cita = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontró la cita.']);
        exit;
    }

    $id_persona = (int)$cita['id_persona'];
    $ci_usuario = (string)$cita['ci_usuario'];

    // Un médico (rol 6) solo puede ver sus propias citas.
    if ($rol === 6) {
        $mi_id = 0;
        $stmt = $conexion->prepare("SELECT id_medico FROM medico WHERE nro_colegiado = ? LIMIT 1");
        $stmt->bind_param('s', $_SESSION['ci_user']);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $mi_id = $fila ? (int)$fila['id_medico'] : 0;
        if ($mi_id <= 0 || $cita['id_medico'] === null || (int)$cita['id_medico'] !== $mi_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Esta cita no está asignada a su consultorio.']);
            exit;
        }
    }

    // ------------------------------------------------------------------
    // 2. Historia médica de ESTA cita (vacía si es la primera vez)
    // ------------------------------------------------------------------
    $historia = null;
    $stmt = $conexion->prepare(
        "SELECT motivo_consulta, enfermedad_actual, antecedentes,
                examen_medico, diagnostico, tratamiento
         FROM traumatologia_historia_medica
         WHERE id_cita = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $historia = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ------------------------------------------------------------------
    // 3. Antecedentes del área para precargar: si esta cita aún no tiene
    //    historia, se toman de la consulta anterior más reciente del
    //    paciente en el servicio (se quedan en el área, no se comparten).
    // ------------------------------------------------------------------
    $antecedentes = '';
    if ($historia !== null) {
        $antecedentes = (string)($historia['antecedentes'] ?? '');
    } else {
        $stmt = $conexion->prepare(
            "SELECT h.antecedentes
             FROM traumatologia_historia_medica h
             INNER JOIN cita c ON c.id_cita = h.id_cita
             WHERE h.id_persona = ? AND h.id_cita <> ?
               AND COALESCE(h.antecedentes, '') <> ''
             ORDER BY c.fecha_cita DESC, c.hora_cita DESC
             LIMIT 1"
        );
        $stmt->bind_param('ii', $id_persona, $id_cita);
        $stmt->execute();
        $fuente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($fuente)) {
            $antecedentes = (string)($fuente['antecedentes'] ?? '');
        }
    }

    // ------------------------------------------------------------------
    // 4. Evoluciones anteriores del paciente en el servicio (hoja 2)
    // ------------------------------------------------------------------
    $evoluciones_anteriores = [];
    $stmt = $conexion->prepare(
        "SELECT h.id_cita, c.fecha_cita, h.motivo_consulta, h.diagnostico,
                m.nro_colegiado, p.nombre AS nombre_persona
         FROM traumatologia_historia_medica h
         INNER JOIN cita c   ON c.id_cita = h.id_cita
         LEFT JOIN medico m  ON c.id_medico = m.id_medico
         LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
         LEFT JOIN persona p ON p.id_persona = u.id_persona
         WHERE h.id_persona = ? AND h.id_cita <> ?
         ORDER BY c.fecha_cita DESC, c.hora_cita DESC"
    );
    $stmt->bind_param('ii', $id_persona, $id_cita);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $evoluciones_anteriores[] = [
            'id_cita'    => (int)$fila['id_cita'],
            'fecha'      => $fila['fecha_cita'],
            'motivo'     => (string)($fila['motivo_consulta'] ?? ''),
            'diagnostico'=> (string)($fila['diagnostico'] ?? ''),
            'medico'     => trim(($fila['nombre_persona'] ?? '') . ' · ' . ($fila['nro_colegiado'] ?? ''), ' ·'),
        ];
    }
    $res->free();
    $stmt->close();

    // ------------------------------------------------------------------
    // 5. Patologías del paciente (bloque compartido entre servicios) y
    //    marca "el paciente no presenta patologías".
    // ------------------------------------------------------------------
    $stmt = $conexion->prepare(
        "SELECT contenido FROM nota_clinica WHERE ci_user = ? AND tipo = 'sin_patologias' LIMIT 1"
    );
    $stmt->bind_param('s', $ci_usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sin_patologias = $fila && trim((string)$fila['contenido']) !== '';

    $patologias = [];
    $stmt = $conexion->prepare(
        "SELECT pa.id_patologia, pa.patologia, pa.codigo_cie10
         FROM persona_patologia pp
         INNER JOIN patologia pa ON pp.id_patologia = pa.id_patologia
         WHERE pp.id_persona = ?
         ORDER BY pa.patologia ASC"
    );
    $stmt->bind_param('i', $id_persona);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $patologias[] = [
            'id_patologia' => (int)$fila['id_patologia'],
            'patologia'    => $fila['patologia'],
            'codigo_cie10' => (string)($fila['codigo_cie10'] ?? ''),
        ];
    }
    $res->free();
    $stmt->close();

    // ------------------------------------------------------------------
    // 6. Datos de nacimiento y nacionalidad (ficha del paciente, solo lectura)
    // ------------------------------------------------------------------
    $nacimiento = (string)($cita['fecha_nacimiento'] ?? '');
    $edad = '';
    if ($nacimiento !== '' && $nacimiento !== '0000-00-00') {
        try {
            $edad = (string)\DateTime::createFromFormat('Y-m-d', $nacimiento)->diff(new \DateTime())->y;
        } catch (\Throwable $e) {
            $edad = '';
        }
    }

    $pais_nacimiento = (string)($cita['pais_nacimiento'] ?? '');
    $nacionalidad    = (string)($cita['nacionalidad'] ?? '');
    if ($nacionalidad === '') {
        // La ficha aún no lo tiene: se deriva del prefijo de la cédula
        // (E-… ⇒ Extranjero) como respaldo de solo lectura.
        $nacionalidad = preg_match('/^\s*e/i', $ci_usuario) ? 'Extranjero' : 'Venezolano';
    }

    // ------------------------------------------------------------------
    // 6b. Antecedentes compartidos del paciente (Bloque 2): quirúrgicos,
    //     patológicos familiares y situación clínica. Los registra cualquier
    //     médico y quedan disponibles para todos los servicios.
    // ------------------------------------------------------------------
    $antecedentes_compartidos = [
        'quirurgico' => cargarNotaClinica($conexion, $ci_usuario, 'quirurgico'),
        'familiar'   => cargarNotaClinica($conexion, $ci_usuario, 'familiar'),
        'situacion'  => cargarNotaClinica($conexion, $ci_usuario, 'situacion'),
    ];

    // Permisos: el médico solo edita el día de la cita; el administrador
    // (rol 5) puede corregir después. El resto es solo lectura.
    $puede_editar = historia_puede_editar($cita['fecha_cita'] ?? null, $rol, $cita['estatus_cita'] ?? null);
    $motivo_bloqueo = $puede_editar ? '' : historia_motivo_bloqueo($cita['fecha_cita'] ?? null, $rol, $cita['estatus_cita'] ?? null);

    $data = [
        'cita' => [
            'id_cita'         => (int)$cita['id_cita'],
            'id_servicio'     => $cita['id_servicio'] !== null ? (int)$cita['id_servicio'] : null,
            'nro_solicitud'   => (string)($cita['nro_solicitud'] ?? ''),
            'fecha_cita'      => $cita['fecha_cita'],
            'hora_cita'       => $cita['hora_cita'],
            'nombre_servicio' => (string)($cita['nombre_servicio'] ?? ''),
            'estatus_cita'    => (string)$cita['estatus_cita'],
        ],
        'paciente' => [
            'id_persona'       => $id_persona,
            'ci_usuario'       => $ci_usuario,
            'nombre'           => (string)$cita['nombre'],
            'fecha_nacimiento' => $nacimiento,
            'edad'             => $edad,
            'sexo'             => (string)($cita['sexo'] ?? ''),
            'telefono'         => (string)($cita['telefono'] ?? ''),
            'direccion'        => (string)($cita['direccion_completa'] ?? ''),
            // Datos de nacimiento y nacionalidad: fuente única = ficha del
            // paciente (persona). Se muestran solo lectura en el formulario.
            'pais_nacimiento'      => $pais_nacimiento,
            'estado_nacimiento'    => (string)($cita['estado_nacimiento'] ?? ''),
            'municipio_nacimiento' => (string)($cita['municipio_nacimiento'] ?? ''),
            'nacionalidad'         => $nacionalidad,
        ],
        'historia_medica'       => $historia,             // null = primera vez en esta cita
        'antecedentes'          => $antecedentes,         // propios del área (o de la consulta anterior)
        'antecedentes_compartidos' => $antecedentes_compartidos, // Bloque 2 global
        'evoluciones_anteriores'=> $evoluciones_anteriores,
        'patologias'            => $patologias,           // bloque compartido entre servicios
        'tiene_patologias'      => (count($patologias) > 0),
        'sin_patologias'        => $sin_patologias,
        'tiene_historia_previa' => ($historia !== null),
        'puede_editar'         => $puede_editar,
        'solo_lectura_motivo'  => $motivo_bloqueo,
    ];

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    error_log('obtener_historia_traumatologia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar la historia de traumatología.']);
}
