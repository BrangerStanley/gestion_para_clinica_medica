<?php
// config/rutas.php
// Definiciones globales de rutas del proyecto (IAA - Versión Ligera).

if (!defined('BASE_URL')) {
    $baseUrl = '';
    $raizProyecto = realpath(__DIR__ . '/..');
    $documentRoot = !empty($_SERVER['DOCUMENT_ROOT'])
        ? realpath((string)$_SERVER['DOCUMENT_ROOT'])
        : false;
    $baseUrlResuelta = false;

    if ($raizProyecto !== false && $documentRoot !== false) {
        $raizNormalizada = rtrim(str_replace('\\', '/', $raizProyecto), '/');
        $documentoNormalizado = rtrim(str_replace('\\', '/', $documentRoot), '/');

        if (strcasecmp($raizNormalizada, $documentoNormalizado) === 0) {
            $baseUrlResuelta = true;
        } elseif (
            strncasecmp(
                $raizNormalizada,
                $documentoNormalizado . '/',
                strlen($documentoNormalizado) + 1
            ) === 0
        ) {
            $rutaRelativa = substr($raizNormalizada, strlen($documentoNormalizado) + 1);
            $segmentos = array_map('rawurlencode', explode('/', $rutaRelativa));
            $baseUrl = '/' . implode('/', $segmentos);
            $baseUrlResuelta = true;
        }
    }

    if (!$baseUrlResuelta) {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName !== '') {
            $scriptName = rawurldecode($scriptName);
            $baseUrl = preg_replace('~/(?:views|controllers)(?:/.*)?$~', '', $scriptName);
            if ($baseUrl === $scriptName) {
                $baseUrl = preg_replace('~/[^/]*$~', '', $scriptName);
            }
            $segmentos = array_filter(explode('/', trim((string)$baseUrl, '/')), 'strlen');
            $baseUrl = $segmentos
                ? '/' . implode('/', array_map('rawurlencode', $segmentos))
                : '';
        }
    }

    define('BASE_URL', $baseUrl);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
}

// La capa de compatibilidad (config/Database.php) reproduce la semántica de
// mysqli: devuelve false en error en vez de lanzar excepciones. Ya no se usa
// la extensión mysqli, así que no hace falta configurar su modo de reporte.
?>