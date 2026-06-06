<?php
require_once '../config/config.php';
$page_title = 'Reservas pendientes';
include_once ROOT_PATH . '/includes/head.php';
?>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . '/includes/sidebar.php'; ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . '/includes/header.php'; ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid py-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="mb-1 fw-bold"><i class="ti ti-clock-pause me-1"></i>Reservas pendientes</h2>
                        <p class="text-muted small mb-0">PSE y transferencias en espera · nros elegidos o asignación automática al aprobar.</p>
                    </div>
                    <span id="reservasPendingBadge" class="badge bg-warning text-dark d-none">0 pendientes</span>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold mb-1">Buscar</label>
                                <input type="text" id="searchReservas" class="form-control form-control-sm"
                                    placeholder="Código, cliente, teléfono…">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Origen</label>
                                <select id="filterOrigenReserva" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="pse">PSE / OpenPay</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="limpiarFiltrosReservas()">
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="reservasCola"></div>
                <div id="contenedorPaginacionReservas" class="mt-3"></div>
                <p id="infoPaginacionReservas" class="text-muted small text-center mb-0"></p>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=16"></script><script src="' . ASSETS_URL . '/js/reservas.js?v=4"></script>';
include_once ROOT_PATH . '/includes/footer.php';
?>
