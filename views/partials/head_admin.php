<?php
// views/partials/head_admin.php
// <head> compartido para vistas de administración (Fase 5).
// Espera: $tituloPagina (opcional) definido antes del include.
$tituloAdmin = $tituloPagina ?? 'IAA - Panel de Administrador';
$cssExtraAdmin = $cssExtraAdmin ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    <script src="js/csrf.js?v=4"></script>
    <script src="js/gestion_core.js?v=1"></script>
    <title><?php echo htmlspecialchars($tituloAdmin, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../public/css/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="css/inicio.css?v=12">
    <link rel="stylesheet" href="css/admin.css?v=10">
    <link rel="stylesheet" href="css/admin_ui.css">
<?php foreach ($cssExtraAdmin as $hojaExtraAdmin): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($hojaExtraAdmin, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
    <link rel="icon" type="image/png" href="../img/Logo.png">
</head>
<body class="iaa-layout">
