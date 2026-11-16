<?php
// Punto de entrada del repositorio de demostración: abre el panel de
// administración médica sin pasar por el login.
require_once __DIR__ . '/config/rutas.php';
header('Location: ' . BASE_URL . '/views/dashboard.php', true, 302);
exit;
