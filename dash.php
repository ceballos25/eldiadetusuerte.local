<?php
require_once "config/config.php";

$error = $_GET['error'] ?? '';
$detail = $_GET['detail'] ?? '';

$messages = [
    'missing' => '⚠️ Completa usuario y contraseña.',
    'bad_credentials' => '❌ Usuario o contraseña incorrectos.',
    'session_expired' => '⏱️ Tu sesión ha expirado. Ingresa nuevamente.',
];

$msg = $messages[$error] ?? '';
if ($detail) {
    $msg .= "<br><small class='text-muted'>" . htmlspecialchars($detail) . "</small>";
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= SITE_NAME ?></title> 
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(edts_cdn('images/logos/logo.ico'), ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/css/app.css?v=40" />
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/css/styles.min.css" />
</head>
<body>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <div class="position-relative overflow-hidden text-bg-light min-vh-100 d-flex align-items-center justify-content-center">
            <div class="d-flex align-items-center justify-content-center w-100">
                <div class="row justify-content-center w-100">
                    <div class="col-md-8 col-lg-6 col-xxl-3">
                        <div class="card mb-0">
                            <div class="card-body">
                                <div class="text-center py-3 mb-2">
                                    <a href="/" class="logo-img d-inline-block">
                                        <img src="<?= htmlspecialchars(edts_logo_url(), ENT_QUOTES, 'UTF-8') ?>" width="220" alt="<?= SITE_NAME ?>" class="img-fluid">
                                    </a>
                                </div>
                                <?php if ($msg): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?= $msg ?>
                                    </div>
                                <?php endif; ?>
                                <form action="functions/login.php" method="POST" autocomplete="off">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Usuario</label>
                                        <input type="text" value="" class="form-control" id="email" name="email" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <input type="password" value="" class="form-control" id="password" name="password" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2">
                                        Ingresar
                                    </button>
                                </form>

                                <span class="d-flex justify-content-center"><small>Version 6.0.0</small></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/libs/jquery/dist/jquery.min.js"></script>
    <script src="<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES, 'UTF-8') ?>/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>