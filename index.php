<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/landing/bootstrap.php';

$landingDir = __DIR__ . '/includes/landing';
?>
<!doctype html>
<html lang="es">
<?php include $landingDir . '/head.php'; ?>
<body class="landing">
<?php
include $landingDir . '/banners.php';
include $landingDir . '/social-rail.php';
include $landingDir . '/navbar.php';
include $landingDir . '/mobile-cart.php';
include $landingDir . '/hero.php';
include $landingDir . '/premios.php';
include $landingDir . '/compra.php';
include $landingDir . '/ganadores.php';
include $landingDir . '/footer.php';
include $landingDir . '/checkout-sheet.php';
include $landingDir . '/modal-buscar-tickets.php';
include $landingDir . '/modal-checkout.php';
include __DIR__ . '/includes/preloader.php';
include __DIR__ . '/includes/btn-share.php';
include $landingDir . '/scripts.php';
?>
</body>
</html>
