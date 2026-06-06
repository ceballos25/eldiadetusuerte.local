<?php
require_once "../config/config.php";
$page_title = "Auditoría";
include_once ROOT_PATH . "/includes/head.php";
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical">
<?php include_once ROOT_PATH . "/includes/sidebar.php" ?>
<div class="body-wrapper">
<?php include_once ROOT_PATH . "/includes/header.php" ?>
<div class="body-wrapper-inner"><div class="container-fluid py-3">
<h2 class="fw-bold mb-4"><i class="ti ti-history me-1"></i> Auditoría</h2>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
  <div class="row g-2">
    <div class="col-md-3"><input type="text" id="filtroAccion" class="form-control form-control-sm" placeholder="Acción..."></div>
    <div class="col-md-2"><input type="date" id="filtroDesde" class="form-control form-control-sm"></div>
    <div class="col-md-2"><input type="date" id="filtroHasta" class="form-control form-control-sm"></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" onclick="cargarAuditoria()">Buscar</button></div>
  </div>
</div></div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive admin-table-desktop">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>Fecha</th><th>Admin</th><th>Acción</th><th>Entidad</th><th>Detalle</th></tr></thead>
      <tbody id="bodyAuditoria"></tbody>
    </table>
  </div>
  <div class="admin-cards-mobile p-3" id="cardsAuditoria"></div>
  <div class="card-footer"><small id="infoAuditoria"></small></div>
</div>
</div></div></div></div>
<?php $extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=4"></script><script src="' . ASSETS_URL . '/js/auditoria.js?v=3"></script>'; include_once ROOT_PATH . "/includes/footer.php"; ?>
