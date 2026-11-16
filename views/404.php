<?php
// views/404.php
// Página 404 personalizada (ErrorDocument 404 del .htaccess raíz).
// No inicia sesión, no consulta la BD ni modifica el flujo de otras páginas.
require_once __DIR__ . '/../config/seguridad.php';
http_response_code(404);
enviarCabecerasSeguridad();

$rutaCruda = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '';
$rutaCruda = preg_replace('/[\x00-\x1F\x7F]/', '', (string)$rutaCruda);
$rutaCruda = trim($rutaCruda);
if ($rutaCruda === '') {
    $rutaCruda = '/';
}
$rutaMostrar = function_exists('mb_substr')
    ? (mb_strlen($rutaCruda, 'UTF-8') > 120 ? mb_substr($rutaCruda, 0, 120, 'UTF-8') . '…' : $rutaCruda)
    : (strlen($rutaCruda) > 120 ? substr($rutaCruda, 0, 120) . '…' : $rutaCruda);
$rutaMostrar = htmlspecialchars($rutaMostrar, ENT_QUOTES, 'UTF-8');

$baseUrl = BASE_URL;
$inicioUrl = $baseUrl . '/public/index.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/img/Logo.png">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/icons/bootstrap-icons.css">
    <title>404 - Página no encontrada | IAA</title>
    <style nonce="<?php echo csp_nonce(); ?>">
        :root {
            --brand: #3678a8;
            --brand-dark: #2b4f6f;
            --dark: #213b55;
            --body: #536273;
            --border: #d5dfe8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #edf2f6;
            color: var(--body);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-image: radial-gradient(1200px 420px at 50% -10%, rgba(106, 174, 214, 0.15), transparent 60%);
        }

        .site-header {
            padding: 18px 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .brand img {
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            object-fit: cover;
            object-position: left center;
        }

        .brand-title {
            display: block;
            font-weight: 700;
            font-size: 1.05rem;
            line-height: 1.15;
            color: var(--dark);
        }

        .brand-sub {
            display: block;
            font-size: 0.72rem;
            color: #8a94a6;
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card404 {
            width: 100%;
            max-width: 640px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.08);
            padding: clamp(28px, 5vw, 48px);
            text-align: center;
        }

        .icon-chip {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: rgba(106, 174, 214, 0.15);
            color: var(--brand);
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .code {
            position: relative;
            display: inline-block;
            font-size: clamp(72px, 14vw, 132px);
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1;
            margin: 0 0 6px;
        }

        .code-shadow {
            position: absolute;
            top: 8px;
            left: 8px;
            color: #e1edf5;
            user-select: none;
        }

        .code-main {
            position: relative;
            color: var(--brand);
        }

        h1 {
            margin: 8px 0 12px;
            font-size: clamp(1.35rem, 3.5vw, 1.75rem);
            font-weight: 700;
            color: var(--dark);
        }

        .lead-404 {
            margin: 0 auto 22px;
            max-width: 460px;
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .ruta-label {
            text-align: left;
            margin: 0 0 6px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #9aa3b2;
        }

        .ruta {
            margin: 0 0 24px;
            text-align: left;
            background: #f1f7fb;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.85rem;
            color: #5a6472;
            word-break: break-all;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-404 {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-primary-404 {
            background: var(--brand);
            border: 1px solid var(--brand);
            color: #fff;
        }

        .btn-primary-404:hover,
        .btn-primary-404:focus {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(61, 64, 248, 0.25);
        }

        .btn-outline-404 {
            background: #fff;
            border: 1px solid #d7dbe2;
            color: var(--dark);
        }

        .btn-outline-404:hover,
        .btn-outline-404:focus {
            border-color: var(--brand);
            color: var(--brand);
            transform: translateY(-2px);
        }

        .hint {
            margin: 22px 0 0;
            font-size: 0.82rem;
            color: #9aa3b2;
        }

        .site-footer {
            padding: 18px 24px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.82rem;
            color: #8a94a6;
        }

        .anim {
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
        }

        .d1 { animation-delay: 0.05s; }
        .d2 { animation-delay: 0.13s; }
        .d3 { animation-delay: 0.21s; }
        .d4 { animation-delay: 0.29s; }
        .d5 { animation-delay: 0.37s; }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .anim {
                animation: none;
                opacity: 1;
                transform: none;
            }
            .btn-404 {
                transition: none;
            }
            .btn-404:hover,
            .btn-404:focus {
                transform: none;
            }
        }

        @media (max-width: 575.98px) {
            .actions {
                flex-direction: column;
            }
            .btn-404 {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?php echo $inicioUrl; ?>">
            <img src="<?php echo $baseUrl; ?>/img/Logo%20ex.png" alt="Logo IAA">
            <span>
                <span class="brand-title">IAA</span>
                <span class="brand-sub">Instituto Autónomo de Ayuda a los Funcionarios</span>
            </span>
        </a>
    </header>

    <main>
        <section class="card404" aria-labelledby="titulo-404">
            <div class="icon-chip anim d1" aria-hidden="true"><i class="bi bi-signpost-split"></i></div>

            <p class="code anim d1">
                <span class="code-shadow" aria-hidden="true">404</span>
                <span class="code-main">404</span>
            </p>

            <h1 id="titulo-404" class="anim d2">Página no encontrada</h1>

            <p class="lead-404 anim d2">
                La dirección que intentas visitar no existe o fue movida a otra ubicación.
                Puede que el enlace esté desactualizado o se haya escrito incorrectamente.
            </p>

            <p class="ruta-label anim d3">Ruta solicitada</p>
            <code class="ruta anim d3"><?php echo $rutaMostrar; ?></code>

            <div class="actions anim d4">
                <a class="btn-404 btn-primary-404" href="<?php echo $inicioUrl; ?>">
                    <i class="bi bi-house-door"></i> Ir al inicio
                </a>
                <a class="btn-404 btn-outline-404"
                   href="<?php echo $inicioUrl; ?>"
                   onclick="if (history.length > 1) { history.back(); return false; }">
                    <i class="bi bi-arrow-left"></i> Volver atrás
                </a>
            </div>

            <p class="hint anim d5">Si el problema persiste, contacta al administrador del sistema.</p>
        </section>
    </main>

    <?php include __DIR__ . '/partials/footer_site.php'; ?>
<?php include __DIR__ . '/partials/equipo_modal.php'; ?>
</body>
</html>
