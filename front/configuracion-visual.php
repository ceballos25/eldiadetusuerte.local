<?php
require_once "../config/config.php";
$page_title = "Configuración Visual";
include_once ROOT_PATH . "/includes/head.php";
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical">
<?php include_once ROOT_PATH . "/includes/sidebar.php" ?>
<div class="body-wrapper">
<?php include_once ROOT_PATH . "/includes/header.php" ?>
<div class="body-wrapper-inner"><div class="container-fluid py-3">
<h2 class="fw-bold mb-4"><i class="ti ti-photo me-1"></i> Configuración Visual</h2>
<p class="text-muted small">Todas las imágenes se gestionan por URL (HTTPS). No se almacenan archivos en el servidor.</p>
<div id="visualContainer" class="row g-3"></div>
</div></div></div></div>
<?php $extra_js = '<script src="' . ASSETS_URL . '/js/visual.js?v=1"></script>'; include_once ROOT_PATH . "/includes/footer.php"; ?>
