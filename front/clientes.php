<?php
require_once "../config/config.php";
$page_title = "Gestión de Clientes";
include_once ROOT_PATH . "/includes/head.php";
?>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">

    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0 fw-bold"><i class="ti ti-users me-1"></i>Clientes</h2>                        
                    </div>
                    <button class="btn btn-primary" onclick="abrirModal()">
                        <i class="ti ti-plus"></i> Crear
                    </button>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Buscador</label>
                                <input type="text" id="searchClientes" class="form-control form-control-sm"
                                    placeholder="Buscar por nombre, email o teléfono...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Estado</label>
                                <select id="filterStatus" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="1">Activos</option>
                                    <option value="0">Inactivos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltros()">
                                    <i class="ti ti-refresh"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive admin-table-desktop" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="table-light sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th class="ps-3" width="60">ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Departamento</th>
                                        <th>Ciudad</th>
                                        <th>Estado</th>
                                        <th width="100">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyTabla">
                                    <tr>
                                        <td colspan="8" class="text-center py-5">Cargando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="admin-cards-mobile p-3" id="clientesMobile"></div>
                    </div>
                    <div class="card-footer bg-white border-top py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" id="infoPaginacion"></small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="contenedorPaginacion"></ul>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCliente">
                <div class="modal-body p-4">
                    <input type="hidden" id="clienteId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="telefono">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select select2-departamento" id="departamento" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ciudad <span class="text-danger">*</span></label>
                            <select class="form-select select2-ciudad" id="ciudad" required disabled></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" id="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCliente()">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfirm" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body p-4">
                <i class="ti ti-alert-triangle text-warning fs-1 mb-3"></i>
                <h5>¿Eliminar cliente?</h5>                
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminar()">Sí,
                        eliminar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<link href="' . ASSETS_URL . '/libs/select2/css/select2.min.css" rel="stylesheet" />
<link href="' . ASSETS_URL . '/libs/select2/css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="' . ASSETS_URL . '/libs/select2/js/select2.min.js"></script>
<script src="' . ASSETS_URL . '/js/departamentos-ciudades.js"></script>
<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=16"></script>
<script src="' . ASSETS_URL . '/js/clientes.js?v=6"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>