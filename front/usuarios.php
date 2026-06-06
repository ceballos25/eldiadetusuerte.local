<?php
require_once "../config/config.php";
$page_title = "Usuarios";
include_once ROOT_PATH . "/includes/head.php";
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical">
<?php include_once ROOT_PATH . "/includes/sidebar.php" ?>
<div class="body-wrapper">
<?php include_once ROOT_PATH . "/includes/header.php" ?>
<div class="body-wrapper-inner"><div class="container-fluid py-3">
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0"><i class="ti ti-users me-1"></i> Usuarios</h2>
  <button class="btn btn-primary" onclick="abrirModalUsuario()"><i class="ti ti-plus"></i> Nuevo</button>
</div>
<div class="card border-0 shadow-sm">
  <div class="table-responsive admin-table-desktop">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>ID</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="bodyUsuarios"><tr><td colspan="5" class="text-center py-4">Cargando...</td></tr></tbody>
    </table>
  </div>
  <div class="admin-cards-mobile p-3" id="usuariosMobile"></div>
</div>
</div></div></div></div>
<div class="modal fade" id="modalUsuario" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="tituloModalUsuario">Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <input type="hidden" id="usuarioId">
  <div class="mb-3"><label class="form-label">Email</label><input type="text" id="usuarioEmail" class="form-control"></div>
  <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" id="usuarioPass" class="form-control" placeholder="Dejar vacío para no cambiar"></div>
  <div class="mb-3"><label class="form-label">Rol</label><select id="usuarioRol" class="form-select"></select></div>
  <div class="mb-3"><label class="form-label">Estado</label><select id="usuarioEstado" class="form-select"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
</div>
<div class="modal-footer"><button class="btn btn-primary" onclick="guardarUsuario()">Guardar</button></div>
</div></div></div>
<?php $extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=4"></script><script src="' . ASSETS_URL . '/js/usuarios.js?v=4"></script>'; include_once ROOT_PATH . "/includes/footer.php"; ?>
