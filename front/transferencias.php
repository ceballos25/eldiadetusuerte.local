<?php
require_once "../config/config.php";
$page_title = "Gestión de Transferencias";
include_once ROOT_PATH . "/includes/head.php";
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid transfer-page py-3">

                <div class="transfer-page-header mb-3">
                    <div>
                        <h2 class="mb-1 fw-bold"><i class="ti ti-building-bank me-1"></i>Revisión de transferencias</h2>
                        <p class="text-muted small mb-0">Comprobante visible en cada tarjeta · 10 por página · más recientes primero.</p>
                    </div>
                    <span id="transferPendingBadge" class="badge transfer-pending-badge d-none">0 pendientes</span>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Buscar</label>
                                <input type="text" id="searchTransfer" class="form-control form-control-sm"
                                    placeholder="Código, cliente, teléfono…">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Desde / Hasta</label>
                                <div class="input-group input-group-sm">
                                    <input type="date" id="fecha_inicio" class="form-control">
                                    <input type="date" id="fecha_fin" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Estado</label>
                                <select id="filterEstado" class="form-select form-select-sm">
                                    <option value="1" selected>Pendiente</option>
                                    <option value="">Todos</option>
                                    <option value="2">Aprobado</option>
                                    <option value="3">Rechazado</option>
                                    <option value="4">Error</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltrosTransfer()">
                                    <i class="ti ti-refresh me-1"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="transferCola" class="transfer-cola">
                    <div class="text-center py-5 text-muted">Cargando…</div>
                </div>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-footer bg-white border-top py-3 rounded-bottom">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted" id="infoPaginacionTransfer"></small>
                            <nav><ul class="pagination pagination-sm mb-0" id="contenedorPaginacionTransfer"></ul></nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalComprobante" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-photo me-1"></i> Comprobante de pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2 bg-light" id="cuerpoComprobante"></div>
            <div class="modal-footer d-none" id="modalComprobanteActions"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecibo" tabindex="-1">
    <div class="modal-dialog modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recibo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoRecibo"></div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=16"></script><script src="' . ASSETS_URL . '/js/transferencias.js?v=14"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>
