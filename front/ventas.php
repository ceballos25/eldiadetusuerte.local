<?php
require_once "../config/config.php";
$page_title = "Gestión de Ventas";
include_once ROOT_PATH . "/includes/head.php";
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid" style="padding-top: 20px;">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 fw-bold"><i class="ti ti-shopping-cart me-1"></i>Gestión de Ventas</h2>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Buscador</label>
                                <input type="text" id="searchVentas" class="form-control form-control-sm" placeholder="Nombre, Apellido, Celular, Correo...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Rango Fechas (Desde - Hasta)</label>
                                <div class="input-group input-group-sm">
                                    <input type="date" id="fecha_inicio" class="form-control">
                                    <input type="date" id="fecha_fin" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Periodo Rápido</label>
                                <select id="filterPeriodo" class="form-select form-select-sm">
                                    <option value="">Seleccionar...</option>
                                    <option value="today">Hoy</option>
                                    <option value="yesterday">Ayer</option>
                                    <option value="week">Esta Semana</option>
                                    <option value="month">Este Mes</option>
                                    <option value="year">Este Año</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Método de Pago</label>
                                    <select id="filterMetodoPago" class="form-select form-select-sm">
                                    <option value="">Todos los pagos</option>
                                    <option value="Pagina Web">Pagina Web</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Venta Manual">Venta Manual</option>
                                    </select>
                            </div>   
                            
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Origen</label>
                                <select id="filterOrigen" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vendedor</label>
                                <select id="filterAdmin" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                </select>
                            </div>                            
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive admin-table-cards-wrap admin-table-ventas-wrap">
                            <table class="table table-hover align-middle mb-0 table-admin-cards table-ventas">
                                <thead class="table-light sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th class="ps-3">Cliente</th>
                                        <th class="d-none d-lg-table-cell">Código</th>
                                        <th class="d-none d-lg-table-cell">Nums/Rifa</th>
                                        <th class="d-none d-lg-table-cell">Total</th>
                                        <th class="d-none d-lg-table-cell">Método</th>
                                        <th class="d-none d-lg-table-cell">Fecha</th>
                                        <th class="text-end pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyTabla">
                                    <tr><td colspan="7" class="text-center py-5">Cargando datos...</td></tr>
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

<div class="modal fade" id="modalRecibo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comprobante de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoRecibo"></div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=22"></script><script src="' . ASSETS_URL . '/js/ventas.js?v=24"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>