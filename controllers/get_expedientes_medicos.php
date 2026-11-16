<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/seguridad.php';
requireSesion();

$rol = (int)($_SESSION['id_rol'] ?? $_SESSION['per_id'] ?? 0);
if ($rol !== 5) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}
exigirPermisoPagina('configurar_servicios');

require_once __DIR__ . '/../config/Conexion.php';
if (!isset($conexion) || !($conexion instanceof IaaConnection)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit;
}

function expedienteFechaValida($fecha)
{
    $fecha = trim((string)$fecha);
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto && $objeto->format('Y-m-d') === $fecha;
}

try {
    $tablasModulo = [
        'medicina_interna_historia_medica' => 'medicina_interna',
        'ginecologia_historia_medica' => 'ginecologia',
        'odontologia_historia_medica' => 'odontologia',
        'pediatria_historia_medica' => 'pediatria',
        'psicologia_historia_medica' => 'psicologia',
        'traumatologia_historia_medica' => 'traumatologia',
        'laboratorio_historia_medica' => 'laboratorio',
    ];

    $resultadoTablas = $conexion->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()"
    );
    if (!$resultadoTablas) {
        throw new RuntimeException('No se pudieron consultar las tablas de expedientes: ' . $conexion->error);
    }

    $tablasDisponibles = [];
    while ($fila = $resultadoTablas->fetch_assoc()) {
        $tablasDisponibles[(string)$fila['table_name']] = true;
    }
    $tablasLaboratorio = array_intersect(
        ['laboratorio_hematologia', 'laboratorio_orina', 'laboratorio_heces'],
        array_keys($tablasDisponibles)
    );

    $modulosDisponibles = array_filter(
        $tablasModulo,
        static function ($modulo, $tabla) use ($tablasDisponibles) {
            return isset($tablasDisponibles[$tabla]);
        },
        ARRAY_FILTER_USE_BOTH
    );

    $periodo = (string)($_GET['periodo'] ?? 'semana');
    $hoy = new DateTimeImmutable('today');
    if ($periodo === 'hoy') {
        $desde = $hoy->format('Y-m-d');
        $hasta = $desde;
    } elseif ($periodo === 'personalizado') {
        $desde = (string)($_GET['desde'] ?? '');
        $hasta = (string)($_GET['hasta'] ?? '');
        if (!expedienteFechaValida($desde) || !expedienteFechaValida($hasta) || $desde > $hasta) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Seleccione un rango de fechas válido.']);
            exit;
        }
    } else {
        $desde = $hoy->modify('monday this week')->format('Y-m-d');
        $hasta = $hoy->modify('sunday this week')->format('Y-m-d');
        $periodo = 'semana';
    }

    $condiciones = ['c.fecha_cita >= CAST(? AS DATE)', 'c.fecha_cita <= CAST(? AS DATE)'];
    $parametros = [$desde, $hasta];
    if (isset($_GET['id_servicio']) && ctype_digit((string)$_GET['id_servicio']) && (int)$_GET['id_servicio'] > 0) {
        $condiciones[] = 'c.id_servicio = CAST(? AS INTEGER)';
        $parametros[] = (string)(int)$_GET['id_servicio'];
    }
    if (isset($_GET['id_medico']) && ctype_digit((string)$_GET['id_medico']) && (int)$_GET['id_medico'] > 0) {
        $condiciones[] = 'c.id_medico = CAST(? AS INTEGER)';
        $parametros[] = (string)(int)$_GET['id_medico'];
    }
    if (isset($_GET['especialidad']) && trim((string)$_GET['especialidad']) !== '') {
        $condiciones[] = "COALESCE(NULLIF(m.especialidad, ''), 'Sin especialidad') = ?";
        $parametros[] = trim((string)$_GET['especialidad']);
    }

    $expresionesModulo = [];
    foreach ($modulosDisponibles as $tabla => $modulo) {
        $resultadosLaboratorio = array_map(
            static function ($tabla, $indice) {
                return "EXISTS (SELECT 1 FROM {$tabla} lab{$indice} WHERE lab{$indice}.id_cita = c.id_cita)";
            },
            $tablasLaboratorio,
            array_keys($tablasLaboratorio)
        );
        $filtroLaboratorio = $modulo === 'laboratorio'
            ? " AND (lh.estatus_resultado IN ('ingresado', 'validado')"
                . ($resultadosLaboratorio ? ' OR ' . implode(' OR ', $resultadosLaboratorio) : '')
                . ')'
            : '';
        $alias = $modulo === 'laboratorio' ? 'lh' : 'hist';
        $expresionesModulo[] = "EXISTS (SELECT 1 FROM {$tabla} {$alias}
            WHERE {$alias}.id_cita = c.id_cita{$filtroLaboratorio})";
    }
    $tieneExpediente = $expresionesModulo ? '(' . implode(' OR ', $expresionesModulo) . ')' : 'FALSE';

    $pagina = isset($_GET['pagina']) && ctype_digit((string)$_GET['pagina'])
        ? min(1000000, max(1, (int)$_GET['pagina']))
        : 1;
    $limite = 50;
    $where = implode(' AND ', array_merge([$tieneExpediente], $condiciones));
    $joins = "
        FROM cita c
        INNER JOIN solicitud s ON s.id_solicitud = c.id_solicitud
        INNER JOIN funcionario f ON f.id_funcionario = s.id_funcionario
        INNER JOIN usuario u ON u.id_usuario = f.id_usuario
        INNER JOIN persona p ON p.id_persona = u.id_persona
        LEFT JOIN servicio sv ON sv.id_servicio = c.id_servicio
        LEFT JOIN medico m ON m.id_medico = c.id_medico
        LEFT JOIN usuario um ON um.ci_usuario = m.nro_colegiado
        LEFT JOIN persona pm ON pm.id_persona = um.id_persona";

    $stmtTotal = $conexion->prepare("SELECT COUNT(*) AS total {$joins} WHERE {$where}");
    if (!$stmtTotal) {
        throw new RuntimeException('No se pudo preparar el conteo de expedientes: ' . $conexion->error);
    }
    if (!$stmtTotal->execute($parametros)) {
        throw new RuntimeException('No se pudo contar los expedientes: ' . $stmtTotal->error);
    }
    $total = (int)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtTotal->close();
    $pagina = min($pagina, max(1, (int)ceil($total / $limite)));
    $desplazamiento = ($pagina - 1) * $limite;

    $casosModulo = [];
    foreach ($expresionesModulo as $indice => $expresion) {
        $modulo = array_values($modulosDisponibles)[$indice];
        $casosModulo[] = "WHEN {$expresion} THEN '{$modulo}'";
    }
    $moduloSql = $casosModulo
        ? 'CASE ' . implode(' ', $casosModulo) . " ELSE 'otro' END"
        : "'otro'";
    $sql = "SELECT c.id_cita, c.id_servicio, c.id_medico, c.fecha_cita, c.hora_cita, c.estatus_cita,
                   p.nombre AS paciente, u.ci_usuario AS cedula_paciente,
                   COALESCE(sv.nombre_servicio, 'Servicio no especificado') AS servicio,
                   COALESCE(NULLIF(m.especialidad, ''), 'Sin especialidad') AS especialidad,
                   COALESCE(NULLIF(pm.nombre, ''), NULLIF(m.nro_colegiado, ''), 'Médico sin nombre') AS medico,
                   {$moduloSql} AS modulo
            {$joins}
            WHERE {$where}
            ORDER BY sv.nombre_servicio NULLS LAST, m.especialidad NULLS LAST,
                     pm.nombre NULLS LAST, c.fecha_cita DESC, c.hora_cita DESC, c.id_cita DESC
            LIMIT {$limite} OFFSET {$desplazamiento}";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de expedientes: ' . $conexion->error);
    }
    if (!$stmt->execute($parametros)) {
        throw new RuntimeException('No se pudieron cargar los expedientes: ' . $stmt->error);
    }
    $expedientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $serviciosResult = $conexion->query(
        "SELECT id_servicio, nombre_servicio FROM servicio ORDER BY nombre_servicio"
    );
    $medicosResult = $conexion->query(
        "SELECT m.id_medico, COALESCE(NULLIF(p.nombre, ''), m.nro_colegiado, 'Médico sin nombre') AS nombre,
                COALESCE(NULLIF(m.especialidad, ''), 'Sin especialidad') AS especialidad
         FROM medico m
         LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
         LEFT JOIN persona p ON p.id_persona = u.id_persona
         ORDER BY p.nombre NULLS LAST, m.nro_colegiado"
    );
    if (!$serviciosResult || !$medicosResult) {
        throw new RuntimeException('No se pudieron cargar los filtros médicos: ' . $conexion->error);
    }
    $servicios = $serviciosResult->fetch_all(MYSQLI_ASSOC);
    $medicos = $medicosResult->fetch_all(MYSQLI_ASSOC);
    $especialidades = [];
    foreach ($medicos as $medico) {
        $especialidades[(string)$medico['especialidad']] = true;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'expedientes' => $expedientes,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $limite,
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $periodo,
            'servicios' => $servicios,
            'medicos' => $medicos,
            'especialidades' => array_keys($especialidades),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_expedientes_medicos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error al consultar los expedientes médicos.']);
}
