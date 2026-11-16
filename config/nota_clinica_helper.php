<?php
/**
 * Helper para manejar notas clínicas compartidas entre servicios.
 * Estructura JSON para auditoría: {"valor": "...", "fecha": "..."}
 */

function cargarNotaClinica($conexion, $ci_user, $tipo) {
    $stmt = $conexion->prepare("SELECT contenido, id_medico FROM nota_clinica WHERE ci_user = ? AND tipo = ? LIMIT 1");
    $stmt->bind_param('ss', $ci_user, $tipo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$fila) return ['valor' => '', 'id_medico' => null, 'fecha' => ''];
    
    $json = json_decode($fila['contenido'], true);
    if (json_last_error() === JSON_ERROR_NONE && isset($json['valor'])) {
        return [
            'valor' => $json['valor'] ?? '',
            'id_medico' => $fila['id_medico'],
            'fecha' => $json['fecha'] ?? ''
        ];
    }
    return ['valor' => (string)$fila['contenido'], 'id_medico' => $fila['id_medico'], 'fecha' => ''];
}

function guardarNotaClinica($conexion, $ci_user, $tipo, $contenido, $id_medico = null) {
    $fecha = date('Y-m-d H:i:s');
    $json = json_encode(['valor' => $contenido, 'fecha' => $fecha]);
    
    $stmt = $conexion->prepare("
        INSERT INTO nota_clinica (ci_user, tipo, contenido, id_medico, actualizado_en)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (ci_user, tipo) DO UPDATE SET 
            contenido = EXCLUDED.contenido,
            id_medico = EXCLUDED.id_medico,
            actualizado_en = EXCLUDED.actualizado_en
    ");
    $stmt->bind_param('sssss', $ci_user, $tipo, $json, $id_medico, $fecha);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}
