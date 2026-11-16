<?php
// models/EstadisticasCuadroModel.php
// Estadísticas de Servicios / Especialidades / Médicos para las pantallas de gestión.
//
// Fuentes:
//   - tabla cita (estatus_cita: programada | completada | cancelada | reprogramada)
//   - tabla solicitud: el monto NO es columna; viaja embebido en `descripcion`
//     con el formato: "Servicio: {id}, Especialidad: {id}, Medico: {id}, Monto: X,XX, ..."
//   - tabla especialidad (precio)
class EstadisticasCuadroModel {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // ---------------------------------------------------------------
    // Parseo de solicitudes (mejor esfuerzo sobre descripcion)
    // ---------------------------------------------------------------
    private function solicitudesParseadas() {
        $items = [];
        $res = @$this->conexion->query(
            "SELECT id_solicitud, estatus_solicitud, descripcion
             FROM solicitud
             WHERE descripcion LIKE 'Servicio:%'"
        );
        if (!$res) {
            return $items;
        }
        while ($row = $res->fetch_assoc()) {
            $d = (string)($row['descripcion'] ?? '');
            if (!preg_match('/Servicio:\s*(\d+)/', $d, $mServ)) {
                continue;
            }
            $item = [
                'id_solicitud'    => (int)$row['id_solicitud'],
                'estatus_solicitud'=> $row['estatus_solicitud'] ?? '',
                'id_servicio'     => (int)$mServ[1],
                'id_especialidad' => 0,
                'id_medico'       => 0,
                'monto'           => 0.0,
            ];
            if (preg_match('/Especialidad:\s*(\d+)/', $d, $mEsp)) {
                $item['id_especialidad'] = (int)$mEsp[1];
            }
            if (preg_match('/Medico:\s*(\d+)/', $d, $mMed)) {
                $item['id_medico'] = (int)$mMed[1];
            }
            // number_format(x, 2) usa ',' de millares y '.' decimal: 1,000.50
            if (preg_match('/Monto:\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]+)?)/', $d, $mMon)) {
                $item['monto'] = (float)str_replace(',', '', $mMon[1]);
            }
            $items[] = $item;
        }
        return $items;
    }

    // ---------------------------------------------------------------
    // Agregados de citas
    // ---------------------------------------------------------------
    private function mapaCitas($columna) {
        // $columna: 'id_servicio' | 'id_medico'
        $sql = "SELECT {$columna} AS clave,
                       COUNT(*) AS total,
                       COUNT(*) FILTER (WHERE estatus_cita = 'completada') AS completadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada') AS programadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'cancelada') AS canceladas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'reprogramada') AS reprogramadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada' AND fecha_cita = CURRENT_DATE) AS hoy,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada' AND fecha_cita >= CURRENT_DATE) AS proximas
                FROM cita
                WHERE {$columna} IS NOT NULL
                GROUP BY {$columna}";
        $mapa = [];
        $res = @$this->conexion->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $mapa[(int)$row['clave']] = $row;
            }
        }
        return $mapa;
    }

    private function totalesCitas() {
        $sql = "SELECT COUNT(*) AS total,
                       COUNT(*) FILTER (WHERE estatus_cita = 'completada') AS completadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada') AS programadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'cancelada') AS canceladas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'reprogramada') AS reprogramadas,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada' AND fecha_cita = CURRENT_DATE) AS hoy,
                       COUNT(*) FILTER (WHERE estatus_cita = 'programada' AND fecha_cita >= CURRENT_DATE) AS proximas
                FROM cita";
        $res = @$this->conexion->query($sql);
        $row = $res ? $res->fetch_assoc() : null;
        return $row ?: [
            'total' => 0, 'completadas' => 0, 'programadas' => 0,
            'canceladas' => 0, 'reprogramadas' => 0, 'hoy' => 0, 'proximas' => 0,
        ];
    }

    private function mapaEspecialidadesPorServicio() {
        $mapa = [];
        $res = @$this->conexion->query("SELECT id_servicio, COUNT(*) AS total FROM especialidad GROUP BY id_servicio");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $mapa[(int)$row['id_servicio']] = (int)$row['total'];
            }
        }
        return $mapa;
    }

    // ---------------------------------------------------------------
    // Resumen por tipo
    // ---------------------------------------------------------------
    public function resumen($tipo) {
        switch ($tipo) {
            case 'servicios':
                return $this->resumenServicios();
            case 'especialidades':
                return $this->resumenEspecialidades();
            case 'medicos':
                return $this->resumenMedicos();
            case 'patologias':
                return $this->resumenPatologias();
            default:
                return ['filas' => [], 'kpi' => []];
        }
    }

    private function resumenServicios() {
        $citas = $this->mapaCitas('id_servicio');
        $espPorServ = $this->mapaEspecialidadesPorServicio();
        $sols = $this->solicitudesParseadas();

        $filas = [];
        // Filas base: todos los servicios existentes (aunque tengan 0 citas)
        $res = @$this->conexion->query("SELECT id_servicio FROM servicio");
        $ids = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['id_servicio'];
            }
        }
        foreach ($ids as $id) {
            $filas[$id] = [
                'especialidades' => isset($espPorServ[$id]) ? (int)$espPorServ[$id] : 0,
                'citas' => isset($citas[$id]) ? (int)$citas[$id]['total'] : 0,
                'atendidas' => isset($citas[$id]) ? (int)$citas[$id]['completadas'] : 0,
                'programadas' => isset($citas[$id]) ? (int)$citas[$id]['programadas'] : 0,
                'canceladas' => isset($citas[$id]) ? (int)$citas[$id]['canceladas'] : 0,
                'solicitudes' => 0,
                'ingresos' => 0.0,
            ];
        }
        foreach ($sols as $s) {
            $id = $s['id_servicio'];
            if (!isset($filas[$id])) {
                $filas[$id] = [
                    'especialidades' => 0, 'citas' => 0, 'atendidas' => 0,
                    'programadas' => 0, 'canceladas' => 0,
                    'solicitudes' => 0, 'ingresos' => 0.0,
                ];
            }
            $filas[$id]['solicitudes']++;
            $filas[$id]['ingresos'] += $s['monto'];
        }

        $tot = $this->totalesCitas();
        $ingresosTotal = 0.0;
        $solicitudesTotal = 0;
        foreach ($sols as $s) {
            $ingresosTotal += $s['monto'];
            $solicitudesTotal++;
        }

        return [
            'filas' => $filas,
            'kpi' => [
                'servicios'    => count($ids),
                'citas'        => (int)$tot['total'],
                'atendidas'    => (int)$tot['completadas'],
                'programadas'  => (int)$tot['programadas'],
                'canceladas'   => (int)$tot['canceladas'],
                'hoy'          => (int)$tot['hoy'],
                'solicitudes'  => $solicitudesTotal,
                'ingresos'     => round($ingresosTotal, 2),
            ],
        ];
    }

    private function resumenEspecialidades() {
        $sols = $this->solicitudesParseadas();

        $filas = [];
        $res = @$this->conexion->query("SELECT id_especialidad, precio FROM especialidad");
        $precios = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['id_especialidad'];
                $precios[$id] = (float)$row['precio'];
                $filas[$id] = ['solicitudes' => 0, 'ingresos' => 0.0];
            }
        }
        foreach ($sols as $s) {
            $id = $s['id_especialidad'];
            if ($id <= 0) {
                continue;
            }
            if (!isset($filas[$id])) {
                $filas[$id] = ['solicitudes' => 0, 'ingresos' => 0.0];
            }
            $filas[$id]['solicitudes']++;
            $filas[$id]['ingresos'] += $s['monto'];
        }

        $ingresosTotal = 0.0;
        $solicitudesTotal = 0;
        foreach ($sols as $s) {
            if ($s['id_especialidad'] > 0) {
                $ingresosTotal += $s['monto'];
                $solicitudesTotal++;
            }
        }

        $n = count($precios);
        $precioProm = 0.0;
        $precioMin = null;
        $precioMax = null;
        if ($n > 0) {
            $suma = array_sum($precios);
            $precioProm = $suma / $n;
            $precioMin = min($precios);
            $precioMax = max($precios);
        }

        return [
            'filas' => $filas,
            'kpi' => [
                'especialidades' => $n,
                'precio_promedio'=> round($precioProm, 2),
                'precio_min'     => $precioMin !== null ? round($precioMin, 2) : 0.0,
                'precio_max'     => $precioMax !== null ? round($precioMax, 2) : 0.0,
                'solicitudes'    => $solicitudesTotal,
                'ingresos'       => round($ingresosTotal, 2),
            ],
        ];
    }

    private function resumenMedicos() {
        $citas = $this->mapaCitas('id_medico');

        $filas = [];
        $res = @$this->conexion->query("SELECT id_medico, estatus_medico FROM medico");
        $estatus = [];
        $ids = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['id_medico'];
                $ids[] = $id;
                $estatus[$id] = $row['estatus_medico'] ?? 'activo';
            }
        }
        foreach ($ids as $id) {
            $c = isset($citas[$id]) ? $citas[$id] : null;
            $filas[$id] = [
                'citas'        => $c ? (int)$c['total'] : 0,
                'atendidas'    => $c ? (int)$c['completadas'] : 0,
                'programadas'  => $c ? (int)$c['programadas'] : 0,
                'canceladas'   => $c ? (int)$c['canceladas'] : 0,
                'proximas'     => $c ? (int)$c['proximas'] : 0,
                'hoy'          => $c ? (int)$c['hoy'] : 0,
            ];
        }

        $tot = $this->totalesCitas();
        $activos = 0;
        $inactivos = 0;
        foreach ($estatus as $e) {
            if ($e === 'inactivo') {
                $inactivos++;
            } else {
                $activos++;
            }
        }

        return [
            'filas' => $filas,
            'kpi' => [
                'medicos'      => count($ids),
                'activos'      => $activos,
                'inactivos'    => $inactivos,
                'citas'        => (int)$tot['total'],
                'atendidas'    => (int)$tot['completadas'],
                'programadas'  => (int)$tot['programadas'],
                'canceladas'   => (int)$tot['canceladas'],
                'hoy'          => (int)$tot['hoy'],
                'proximas'     => (int)$tot['proximas'],
            ],
        ];
    }

    /**
     * Catalogo de patologias CIE-10 y su uso real en los historicos clinicos.
     * `filas` indexa por id_patologia: { usos, funcionarios, beneficiarios }.
     * `kpi` agrega todo el catalogo, no solo la pagina visible.
     */
    private function resumenPatologias() {
        $filas = [];
        $ids = [];
        $res = @$this->conexion->query(
            "SELECT p.id_patologia,
                    COUNT(pp.id_persona) AS usos,
                    COUNT(*) FILTER (WHERE pp.id_persona IS NOT NULL AND b.id_beneficiario IS NULL) AS funcionarios,
                    COUNT(*) FILTER (WHERE b.id_beneficiario IS NOT NULL) AS beneficiarios
               FROM patologia p
               LEFT JOIN persona_patologia pp ON pp.id_patologia = p.id_patologia
               LEFT JOIN beneficiario b ON b.id_persona = pp.id_persona
              GROUP BY p.id_patologia"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['id_patologia'];
                $ids[] = $id;
                $filas[$id] = [
                    'usos'         => (int)$row['usos'],
                    'funcionarios' => (int)$row['funcionarios'],
                    'beneficiarios'=> (int)$row['beneficiarios'],
                ];
            }
        }

        $totales = @$this->conexion->query(
            "SELECT COUNT(*) AS patologias,
                    COUNT(*) FILTER (WHERE codigo_cie10 IS NOT NULL AND codigo_cie10 <> '') AS con_codigo,
                    COUNT(*) FILTER (WHERE codigo_cie10 IS NULL OR codigo_cie10 = '') AS sin_codigo,
                    COUNT(*) FILTER (WHERE LENGTH(patologia) > 255) AS exceden_longitud
               FROM patologia"
        );
        $t = $totales ? $totales->fetch_assoc() : null;

        $asignaciones = 0;
        $enUso = 0;
        foreach ($filas as $f) {
            $asignaciones += $f['usos'];
            if ($f['usos'] > 0) {
                $enUso++;
            }
        }

        return [
            'filas' => $filas,
            'kpi' => [
                'patologias'    => $t ? (int)$t['patologias'] : 0,
                'con_codigo'   => $t ? (int)$t['con_codigo'] : 0,
                'sin_codigo'   => $t ? (int)$t['sin_codigo'] : 0,
                'en_uso'       => $enUso,
                'asignaciones' => $asignaciones,
            ],
        ];
    }
}
?>
