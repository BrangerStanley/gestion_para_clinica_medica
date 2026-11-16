<?php
// controllers/obtener_historia_ginecologia.php
// GET ?id_cita=NN
// Carga el estado del formulario ginecológico de UNA cita:
//   - datos del paciente (solicitud -> funcionario)
//   - historia médica y examen ya guardados en esa cita (modo edición)
//   - estudios complementarios de esa cita
//   - TODO el historial ginecológico del paciente (sesiones previas)
//   - nota_clinica (antecedentes de la ficha) y los antecedentes propios del
//     área (ginecologia_historia_medica) para pre-cargar
// Salida: { success, data:{...} }

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
                c.fecha_cita, c.hora_cita, c.diagnostico, c.estatus_cita,
                u.ci_usuario,
                p.id_persona, p.nombre, p.fecha_nacimiento, p.sexo, p.telefono,
                d.direccion_completa,
                sv.nombre_servicio
         FROM cita c
         INNER JOIN solicitud s   ON c.id_solicitud = s.id_solicitud
         INNER JOIN funcionario f  ON s.id_funcionario = f.id_funcionario
         INNER JOIN usuario u      ON f.id_usuario = u.id_usuario
         INNER JOIN persona p      ON u.id_persona = p.id_persona
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
        "SELECT motivo_consulta, antecedentes
         FROM ginecologia_historia_medica
         WHERE id_cita = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $historia = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ------------------------------------------------------------------
    // 3. Examen ginecoobstétrico de ESTA cita
    // ------------------------------------------------------------------
    $examen = null;
    $stmt = $conexion->prepare(
        "SELECT menarquia, fur, fpp, gestas, paras, cesareas, abortos, citologias
         FROM ginecologia_examen_ginecoobstetrico
         WHERE id_cita = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $examen = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ------------------------------------------------------------------
    // 4. Estudios complementarios de ESTA cita
    // ------------------------------------------------------------------
    $estudios = null;
    $stmt = $conexion->prepare(
        "SELECT ecosonograma, laboratorio, observaciones
         FROM ginecologia_estudios
         WHERE id_cita = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $id_cita);
    $stmt->execute();
    $estudios = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ------------------------------------------------------------------
    // 5. HISTORIAL ginecológico del paciente (todas sus citas previas)
    // ------------------------------------------------------------------
    $sesiones = [];
    $stmt = $conexion->prepare(
        "SELECT gs.id AS id_sesion, gs.id_cita, gs.fecha, gs.diagnostico,
                m.nro_colegiado, p.nombre AS nombre_persona
         FROM ginecologia_sesion gs
         LEFT JOIN medico m ON gs.id_medico = m.id_medico
         LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
         LEFT JOIN persona p ON p.id_persona = u.id_persona
         WHERE gs.id_persona = ?
         ORDER BY gs.fecha DESC, gs.id DESC"
    );
    $stmt->bind_param('i', $id_persona);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $sesiones[] = [
            'id_sesion'    => (int)$fila['id_sesion'],
            'id_cita'      => (int)$fila['id_cita'],
            'fecha'        => $fila['fecha'],
            'diagnostico'  => (string)($fila['diagnostico'] ?? ''),
            'medico'       => trim(($fila['nombre_persona'] ?? '') . ' · ' . ($fila['nro_colegiado'] ?? ''), ' ·'),
        ];
    }
    $res->free();
    $stmt->close();

    // ------------------------------------------------------------------
    // 6a. Antecedentes de la FICHA del paciente (compartida entre servicios).
    //     Son los que pide la ficha, sección IV.
    // ------------------------------------------------------------------
    $antecedentes = [
        'quirurgico' => '',
        'familiar'   => '',
        'situacion'  => '',
    ];
    foreach (['quirurgico', 'familiar', 'situacion'] as $tipoAnt) {
        $antecedentes[$tipoAnt] = cargarNotaClinica($conexion, $cita['ci_usuario'], $tipoAnt)['valor'];
    }

    // ------------------------------------------------------------------
    // 6b. Antecedentes PROPIOS de ginecología (documento del área).
    //     Se cargan de ESTA cita; si aún no hay, de la última cita
    //     ginecológica del paciente (se quedan en el área, no se comparten).
    // ------------------------------------------------------------------
    $antecedentes_doc = [
        'personales'    => '',
        'generales'     => '',
        'familiares'    => '',
        'ginecologicos' => '',
    ];

    $gine_decodificar_ant = function ($texto) {
        $texto = trim((string)$texto);
        if ($texto === '' || $texto === 'Array') {
            return null;
        }
        $dec = json_decode($texto, true);
        return is_array($dec) ? $dec : null;
    };

    $doc_antecedentes = null;
    if ($historia !== null) {
        $doc_antecedentes = $gine_decodificar_ant($historia['antecedentes'] ?? '');
    }
    if ($doc_antecedentes === null) {
        $stmt = $conexion->prepare(
            "SELECT gh.antecedentes
             FROM ginecologia_historia_medica gh
             INNER JOIN cita c ON c.id_cita = gh.id_cita
             WHERE gh.id_persona = ? AND gh.id_cita <> ?
               AND gh.antecedentes IS NOT NULL AND gh.antecedentes <> ''
             ORDER BY c.fecha_cita DESC, c.hora_cita DESC
             LIMIT 1"
        );
        $stmt->bind_param('ii', $id_persona, $id_cita);
        $stmt->execute();
        $fila_ant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila_ant) {
            $doc_antecedentes = $gine_decodificar_ant($fila_ant['antecedentes']);
        }
    }
    if (is_array($doc_antecedentes)) {
        foreach ($antecedentes_doc as $clave => $valor) {
            $antecedentes_doc[$clave] = (string)($doc_antecedentes[$clave] ?? '');
        }
    }

    // ------------------------------------------------------------------
    // 6c. Patologías del paciente (CIE-10) - ficha / contexto clínico
    // ------------------------------------------------------------------
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

    // El médico puede haber indicado que el paciente NO presenta patologías.
    // Se guarda para no volver a preguntarlo en las próximas citas.
    $sin_patologias = false;
    $stmt = $conexion->prepare(
        "SELECT contenido FROM nota_clinica WHERE ci_user = ? AND tipo = 'sin_patologias' LIMIT 1"
    );
    $stmt->bind_param('s', $cita['ci_usuario']);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sin_patologias = $fila && trim((string)$fila['contenido']) !== '';

    // ------------------------------------------------------------------
    // Respuesta
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

    // Permisos: el médico solo edita el día de la cita; el administrador
    // (rol 5) puede corregir después. El resto es solo lectura.
    $puede_editar = historia_puede_editar($cita['fecha_cita'] ?? null, $rol, $cita['estatus_cita'] ?? null);
    $motivo_bloqueo = $puede_editar ? '' : historia_motivo_bloqueo($cita['fecha_cita'] ?? null, $rol, $cita['estatus_cita'] ?? null);

    $data = [
        'cita' => [
            'id_cita'         => (int)$cita['id_cita'],
            'id_servicio'     => $cita['id_servicio'] !== null ? (int)$cita['id_servicio'] : null,
            'fecha_cita'      => $cita['fecha_cita'],
            'hora_cita'       => $cita['hora_cita'],
            'nombre_servicio' => (string)($cita['nombre_servicio'] ?? ''),
            'estatus_cita'    => (string)$cita['estatus_cita'],
        ],
        'paciente' => [
            'id_persona'       => $id_persona,
            'ci_usuario'       => (string)$cita['ci_usuario'],
            'nombre'           => (string)$cita['nombre'],
            'fecha_nacimiento' => $nacimiento,
            'edad'             => $edad,
            'sexo'             => (string)($cita['sexo'] ?? ''),
            'telefono'         => (string)($cita['telefono'] ?? ''),
            'direccion'        => (string)($cita['direccion_completa'] ?? ''),
        ],
        'historia_medica'  => $historia,   // null = primera vez en esta cita
        'examen'           => $examen,     // null = primera vez en esta cita
        'estudios'         => $estudios,   // null = primera vez en esta cita
        'sesiones'         => $sesiones,
        'antecedentes'     => $antecedentes,           // ficha (compartida)
        'antecedentes_documento' => $antecedentes_doc, // área de ginecología
        'patologias'       => $patologias,
        'tiene_historia_previa' => ($historia !== null),
        'tiene_patologias'       => (count($patologias) > 0),
        'sin_patologias'         => $sin_patologias,
        'puede_editar'           => $puede_editar,
        'solo_lectura_motivo'    => $motivo_bloqueo,
    ];

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    error_log('obtener_historia_ginecologia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar la historia ginecológica.']);
}
