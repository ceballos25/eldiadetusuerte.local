<?php
require_once "../config/config.php";
$page_title = "Gestión de Nros";
include_once ROOT_PATH . "/includes/head.php";
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php"; ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php"; ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid" style="padding-top: 20px;">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 fw-bold"><i class="ti ti-list-numbers me-1"></i>Gestión de Nros</h2>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Rifa</label>
                                <select id="filterRifa" class="form-select form-select-sm">
                                    </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Estado</label>
                                <select id="filterEstado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="0">Disponibles</option>
                                    <option value="1">Vendidos</option>
                                    <option value="2">Reservados</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Buscar Nro</label>
                                <input type="text" id="searchNumeros" class="form-control form-control-sm" placeholder="Ej: 15...">
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltrosNumeros()">
                                    <i class="ti ti-refresh"></i> Recargar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive admin-table-cards-wrap admin-table-numeros-wrap">
                            <table class="table table-hover align-middle mb-0 table-admin-cards table-numeros">
                                <thead class="table-light sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th class="ps-3 text-start">Nro</th>
                                        <th class="d-none d-lg-table-cell text-center">Estado</th>
                                        <th class="d-none d-lg-table-cell text-center">Premium</th>
                                        <th class="text-end pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyTablaNumeros">
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-white border-top py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" id="infoPaginacion"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="contenedorPaginacion"></ul></nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=21"></script><script src="' . ASSETS_URL . '/js/numeros.js?v=9"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>