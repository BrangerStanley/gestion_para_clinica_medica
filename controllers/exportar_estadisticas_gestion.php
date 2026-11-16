<?php
// controllers/exportar_estadisticas_gestion.php
// Exportación de estadísticas de las pantallas de gestión
// (Servicios / Especialidades / Médicos) a CSV / Excel (HTML xls) / PDF simple.
// Incluye los KPIs y el desglose por fila. Patrón idéntico a exportar_solicitudes.php.
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/seguridad.php';
requireRol([5]);

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../config/bitacora_helper.php';
require_once __DIR__ . '/../models/EstadisticasCuadroModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$modo = strtolower(trim((string)($_GET['modo'] ?? '')));
if (!in_array($modo, ['servicios', 'especialidades', 'medicos', 'patologias'], true)) {
    echo json_encode(['success' => false, 'message' => 'Modo de exportación no válido']);
    exit;
}

$formato = strtolower(trim((string)($_GET['formato'] ?? 'csv')));
if (!in_array($formato, ['csv', 'excel', 'pdf'], true)) {
    echo json_encode(['success' => false, 'message' => 'Formato de exportación no válido']);
    exit;
}

// ---------------------------------------------------------------
// Datos: KPIs globales + detalle por fila (mismas columnas que la UI)
// ---------------------------------------------------------------
$model = new EstadisticasCuadroModel($conexion);
$resumen = $model->resumen($modo);
$filasStats = $resumen['filas'];
$kpi = $resumen['kpi'];

$etiquetasKpi = etiquetasKpi($modo);
$filasExport = [];
$filasExport[] = ['Indicador', 'Valor'];
foreach ($etiquetasKpi as $clave => $etiqueta) {
    $filasExport[] = [$etiqueta, formatearValor($kpi[$clave] ?? 0, $clave)];
}
$filasExport[] = ['', ''];

switch ($modo) {
    case 'servicios':
        [$encabezados, $datos] = detalleServicios($conexion, $filasStats);
        $titulo = 'Estadísticas de Servicios';
        break;
    case 'especialidades':
        $idServicio = (int)($_GET['id_servicio'] ?? 0);
        [$encabezados, $datos] = detalleEspecialidades($conexion, $filasStats, $idServicio);
        $titulo = 'Estadísticas de Especialidades';
        break;
    case 'medicos':
        $filtros = [
            'id_servicio' => (int)($_GET['id_servicio'] ?? 0),
            'estatus'     => trim((string)($_GET['estatus'] ?? '')),
            'buscar'      => trim((string)($_GET['buscar'] ?? '')),
        ];
        [$encabezados, $datos] = detalleMedicos($conexion, $filasStats, $filtros);
        $titulo = 'Estadísticas de Médicos';
        break;
    case 'patologias':
        [$encabezados, $datos] = detallePatologias($conexion, $filasStats);
        $titulo = 'Estadísticas del Catálogo de Patologías';
        break;
}

$filasExport[] = $encabezados;
foreach ($datos as $fila) {
    $filasExport[] = $fila;
}

$fecha = date('Y-m-d');
$nombreBase = 'estadisticas_' . $modo . '_' . $fecha;
$nFilas = count($datos);

registrarBitacora(
    $conexion,
    'exportar',
    "Exportación estadísticas $modo " . strtoupper($formato) . " $fecha ($nFilas filas)",
    'exportacion'
);

if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($filasExport as $fila) {
        fputcsv($out, $fila, ';');
    }
    fclose($out);
    exit;
}

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.xls"');
    echo "<html><head><meta charset=\"utf-8\"></head><body>";
    echo '<h3>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . ' — ' . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') . '</h3>';
    echo '<table border="1">';
    foreach ($filasExport as $fila) {
        echo '<tr>';
        foreach ($fila as $celda) {
            echo '<td>' . htmlspecialchars((string)$celda, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// pdf: PDF mínimo de una página generado a mano (sin librerías externas)
$lines = [$titulo . ' — ' . $fecha, ''];
foreach ($filasExport as $fila) {
    $lines[] = implode(' | ', array_map(function ($c) {
        return preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', (string)$c);
    }, $fila));
}
$content = "BT /F1 9 Tf 20 780 Td 14 TL\n";
foreach (explode("\n", implode("\n", $lines)) as $ln) {
    $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ln);
    $content .= '(' . $safe . ") Tj T*\n";
}
$content .= "ET";
$objects = [
    1 => "<< /Type /Catalog /Pages 2 0 R >>",
    2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
    3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
    4 => "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
    5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
];
$pdf = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $n => $body) {
    $offsets[$n] = strlen($pdf);
    $pdf .= "$n 0 obj\n$body\nendobj\n";
}
$xrefPos = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
foreach ($offsets as $off) {
    $pdf .= sprintf("%010d 00000 n \n", $off);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
header('Content-Type: application/pdf; charset=utf-8');
header('Content-Disposition: inline; filename="' . $nombreBase . '.pdf"');
echo $pdf;
exit;

// ---------------------------------------------------------------
// Helpers de construcción de filas
// ---------------------------------------------------------------
function etiquetasKpi(string $modo): array
{
    if ($modo === 'servicios') {
        return [
            'servicios'   => 'Servicios',
            'citas'       => 'Citas',
            'atendidas'   => 'Atendidas',
            'programadas' => 'Programadas',
            'canceladas'  => 'Canceladas',
            'hoy'         => 'Programadas hoy',
            'solicitudes' => 'Solicitudes',
            'ingresos'    => 'Ingresos ($)',
        ];
    }
    if ($modo === 'especialidades') {
        return [
            'especialidades'  => 'Especialidades',
            'precio_promedio' => 'Precio promedio ($)',
            'precio_min'      => 'Precio mínimo ($)',
            'precio_max'      => 'Precio máximo ($)',
            'solicitudes'     => 'Solicitudes',
            'ingresos'        => 'Ingresos ($)',
        ];
    }
    if ($modo === 'patologias') {
        return [
            'patologias'    => 'Patologías en catálogo',
            'con_codigo'   => 'Con código CIE-10',
            'sin_codigo'   => 'Sin código CIE-10',
            'en_uso'       => 'Patologías en uso',
            'asignaciones' => 'Asignaciones en historiales',
        ];
    }
    return [
        'medicos'    => 'Médicos',
        'activos'    => 'Activos',
        'inactivos'  => 'Inactivos',
        'citas'      => 'Citas',
        'atendidas'  => 'Atendidas',
        'programadas'=> 'Programadas',
        'canceladas' => 'Canceladas',
        'hoy'        => 'Programadas hoy',
        'proximas'   => 'Próximas',
    ];
}

function formatearValor($valor, string $clave)
{
    if (in_array($clave, ['ingresos', 'precio_promedio', 'precio_min', 'precio_max'], true)) {
        return number_format((float)$valor, 2, '.', '');
    }
    return (string)(int)$valor;
}

function detalleServicios($conexion, array $filasStats): array
{
    $encabezados = [
        'ID', 'Servicio', 'Especialidades', 'Citas', 'Atendidas',
        'Programadas', 'Canceladas', 'Solicitudes', 'Ingresos',
    ];
    $datos = [];
    $res = @$conexion->query("SELECT id_servicio, nombre_servicio FROM servicio ORDER BY nombre_servicio");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id_servicio'];
            $st = $filasStats[$id] ?? [];
            $datos[] = [
                $id,
                $row['nombre_servicio'],
                (int)($st['especialidades'] ?? 0),
                (int)($st['citas'] ?? 0),
                (int)($st['atendidas'] ?? 0),
                (int)($st['programadas'] ?? 0),
                (int)($st['canceladas'] ?? 0),
                (int)($st['solicitudes'] ?? 0),
                formatearValor($st['ingresos'] ?? 0, 'ingresos'),
            ];
        }
    }
    return [$encabezados, $datos];
}

function detalleEspecialidades($conexion, array $filasStats, int $idServicio): array
{
    $encabezados = ['ID', 'Especialidad', 'Precio', 'Servicio', 'Solicitudes', 'Ingresos'];
    $datos = [];
    $sql = "SELECT e.id_especialidad, e.especialidad, e.precio, s.nombre_servicio
            FROM especialidad e
            LEFT JOIN servicio s ON s.id_servicio = e.id_servicio";
    $params = '';
    if ($idServicio > 0) {
        $sql .= " WHERE e.id_servicio = ?";
        $params = 'i';
    }
    $sql .= " ORDER BY e.especialidad";
    $stmt = $params !== '' ? $conexion->prepare($sql) : @$conexion->query($sql);
    if ($params !== '') {
        $stmt->bind_param($params, $idServicio);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $stmt;
    }
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id_especialidad'];
            $st = $filasStats[$id] ?? [];
            $datos[] = [
                $id,
                $row['especialidad'],
                formatearValor($row['precio'], 'precio_promedio'),
                $row['nombre_servicio'] ?? '—',
                (int)($st['solicitudes'] ?? 0),
                formatearValor($st['ingresos'] ?? 0, 'ingresos'),
            ];
        }
    }
    if ($params !== '') {
        $stmt->close();
    }
    return [$encabezados, $datos];
}

function detalleMedicos($conexion, array $filasStats, array $filtros): array
{
    $encabezados = [
        'ID', 'Cédula', 'Médico', 'Servicio', 'Citas', 'Atendidas',
        'Programadas', 'Canceladas', 'Próximas', 'Programadas hoy', 'Estatus',
    ];
    $datos = [];
    // El nombre del médico se resuelve desde persona cruzando por la cédula;
    // 'especialidad' es la especialidad real, ya no guarda el nombre.
    $sql = "SELECT m.id_medico, m.nro_colegiado, m.especialidad,
                   p.nombre AS nombre_med, m.estatus_medico,
                   string_agg(s.nombre_servicio, ', ' ORDER BY s.nombre_servicio) AS nombre_servicio
            FROM medico m
            LEFT JOIN usuario u ON u.ci_usuario = m.nro_colegiado
            LEFT JOIN persona p ON p.id_persona = u.id_persona
            LEFT JOIN medico_servicio ms ON ms.id_medico = m.id_medico
            LEFT JOIN servicio s ON s.id_servicio = ms.id_servicio";
    $where = [];
    $params = '';
    $valores = [];
    if (!empty($filtros['id_servicio'])) {
        // Con la pivote el medico puede tener varios servicios: el filtro pasa
        // a ser "pertenece a ESE servicio", no "tiene ese como principal".
        $where[] = 'EXISTS (SELECT 1 FROM medico_servicio msf
                            WHERE msf.id_medico = m.id_medico
                              AND msf.id_servicio = ?)';
        $params .= 'i';
        $valores[] = $filtros['id_servicio'];
    }
    if ($filtros['estatus'] !== '' && in_array($filtros['estatus'], ['activo', 'inactivo'], true)) {
        $where[] = 'm.estatus_medico = ?';
        $params .= 's';
        $valores[] = $filtros['estatus'];
    }
    if ($filtros['buscar'] !== '') {
        // Se busca por cédula, especialidad o nombre (el nombre vive en persona).
        $where[] = '(m.nro_colegiado LIKE ? OR m.especialidad LIKE ? OR p.nombre LIKE ?)';
        $like = '%' . $filtros['buscar'] . '%';
        $params .= 'sss';
        $valores[] = $like;
        $valores[] = $like;
        $valores[] = $like;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    // GROUP BY obligatorio por el GROUP_CONCAT de los servicios.
    $sql .= ' GROUP BY m.id_medico, p.nombre ORDER BY p.nombre, m.nro_colegiado';

    if ($params !== '') {
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param($params, ...$valores);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = @$conexion->query($sql);
    }
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id_medico'];
            $st = $filasStats[$id] ?? [];
            $datos[] = [
                $id,
                $row['nro_colegiado'] !== '' && $row['nro_colegiado'] !== null ? $row['nro_colegiado'] : 'N/A',
                // Sin cuenta no hay nombre; se marca en vez de inventar uno.
                trim((string)($row['nombre_med'] ?? '')) !== ''
                    ? $row['nombre_med']
                    : 'Sin cuenta (CI ' . ($row['nro_colegiado'] ?? '?') . ')',
                $row['nombre_servicio'] ?? 'Sin servicio',
                (int)($st['citas'] ?? 0),
                (int)($st['atendidas'] ?? 0),
                (int)($st['programadas'] ?? 0),
                (int)($st['canceladas'] ?? 0),
                (int)($st['proximas'] ?? 0),
                (int)($st['hoy'] ?? 0),
                $row['estatus_medico'] ?? 'activo',
            ];
        }
    }
    if ($params !== '') {
        $stmt->close();
    }
    return [$encabezados, $datos];
}

function detallePatologias($conexion, array $filasStats): array
{
    $encabezados = [
        'ID', 'Código CIE-10', 'Patología', 'Asignaciones',
        'Funcionarios', 'Beneficiarios',
    ];
    $datos = [];
    $res = @$conexion->query("SELECT id_patologia, patologia, codigo_cie10 FROM patologia ORDER BY patologia");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id_patologia'];
            $st = $filasStats[$id] ?? [];
            $datos[] = [
                $id,
                ($row['codigo_cie10'] !== null && $row['codigo_cie10'] !== '') ? $row['codigo_cie10'] : '—',
                $row['patologia'],
                (int)($st['usos'] ?? 0),
                (int)($st['funcionarios'] ?? 0),
                (int)($st['beneficiarios'] ?? 0),
            ];
        }
    }
    return [$encabezados, $datos];
}
