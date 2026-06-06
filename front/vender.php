<?php
require_once "../config/config.php";
$page_title = "Nueva Venta";
$extra_css = '';
include_once ROOT_PATH . "/includes/head.php";
?>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>
    
    <div class="body-wrapper bg-light min-vh-100">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>
        
        <div class="body-wrapper-inner">
            <div class="container-xxl p-2 p-lg-4 pb-5 mb-5"> 

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0 fw-bold text-dark">Registrar Venta</h4>
                    <button class="btn btn-light border shadow-sm px-3 text-danger fw-bold rounded-pill" onclick="location.reload()">
                        <i class="ti ti-refresh"></i>
                    </button>
                </div>

                <div class="row g-3">
                    
                    <div class="col-lg-8">
                        
                        <div class="card border-0 shadow-sm rounded-4 mb-3">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 fw-bold text-primary"><span class="badge bg-primary rounded-pill me-2">1</span>Cliente</h6>
                            </div>
                            <div class="card-body p-3 p-lg-4">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">BUSCAR (Opcional)</label>
                                    <select id="buscadorCliente" class="form-control w-100"></select>
                                </div>

                                <div class="bg-white p-1 rounded-3">
                                    <form id="formClienteVenta">
                                        <input type="hidden" id="idCliente" name="id_customer">
                                        
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Celular <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control shadow-sm" id="celularCliente" required>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Nombre <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control shadow-sm text-capitalize" id="nombreCliente" required>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Apellido <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control shadow-sm text-capitalize" id="apellidoCliente" required>
                                            </div>

                                            <div class="col-12 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control shadow-sm text-lowercase" id="emailCliente">
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Depto <span class="text-danger">*</span></label>
                                                <select class="form-select shadow-sm select2-ubicacion" id="departamento"></select>
                                            </div>
                                            <div class="col-6 col-md-4">
                                                <label class="small fw-bold text-dark mb-1">Ciudad <span class="text-danger">*</span></label>
                                                <select class="form-select shadow-sm select2-ubicacion" id="ciudad" disabled></select>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" id="btnLimpiarCliente" onclick="resetClienteForm()">
                                                <i class="ti ti-eraser me-1"></i> Limpiar campos
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4 vender-numeros-card">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-primary"><span class="badge bg-primary rounded-pill me-2">2</span>Nros</h6>
                                <small class="text-muted d-lg-none">Toca los libres</small>
                            </div>
                            
                            <div class="card-body p-0 p-lg-4 cr-theme">
                                
                                <div class="px-3 pt-3 px-lg-0 pt-lg-0">
                                    <select class="form-select form-select fw-bold shadow-sm text-dark border-secondary" id="selectRifa">
                                    </select>
                                </div>

                                <div id="bloquePaquetesVenta" class="bg-light px-3 px-lg-3 pt-3 pb-2">
                                    <?php include ROOT_PATH . '/includes/components/cr-paquetes-grid.php'; ?>

                                    <div class="mt-3" id="bloqueNumeroPremiado">
                                        <label class="form-label fw-bold small text-muted">
                                            Escoger nro (opcional)
                                        </label>
                                        <input
                                            type="tel"
                                            class="form-control shadow-sm"
                                            id="numeroPremiado"
                                            placeholder="Ej: 12345"
                                            maxlength="5"
                                        >
                                        <small class="text-muted">
                                            Si lo defines, se asigna dentro de la venta (solo rifa automática)
                                        </small>
                                    </div>
                                </div>

                                <?php
                                $crGrillaWrapperId = 'selectorManualVenta';
                                $crGrillaGridId = 'gridNumerosVenta';
                                $crGrillaSearchId = 'buscarNumeroVenta';
                                $crGrillaCountId = 'manualVentaSeleccionCount';
                                $crGrillaStatsId = 'gridNumerosVentaStats';
                                $crGrillaPagerId = 'gridNumerosVentaPager';
                                $crGrillaFilterFn = 'filtrarNumerosVenta';
                                $crGrillaWrapperClass = 'd-none mt-2';
                                include ROOT_PATH . '/includes/components/cr-grilla-manual.php';
                                ?>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 d-none d-lg-block">
                        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 90px;">
                            <div class="card-header text-white py-3 rounded-top-4">
                                <h6 class="mb-0 fw-bold"><i class="ti ti-receipt-2 me-2"></i>Resumen</h6>
                            </div>
                            <div class="card-body p-0 bg-white">
                                <ul class="list-group list-group-flush" id="listaCarritoDesktop" style="max-height: 300px; overflow-y: auto;">
                                    <li class="list-group-item text-center text-muted py-5 border-0"><small>Sin selección</small></li>
                                </ul>
                            </div>
                                <div class="card-footer bg-light p-4 border-top">
                                    <div class="d-flex justify-content-between align-items-end mb-3">
                                        <span class="h6 mb-0 text-muted">
                                            Total a Pagar 
                                            <small class="ms-1 text-muted">(<span id="lblCantidadDesktop">0</span> nros)</small>
                                        </span>
                                        <span class="h2 mb-0 fw-bolder text-primary" id="lblTotalDesktop">$0</span>
                                    </div>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <input type="radio" class="btn-check d-none" checked name="metodoPago" cheked id="pagoEfecDesk" value="Venta Manual">
                                            <label class="btn btn-outline-primary w-100 fw-bold py-2 d-none" for="pagoEfecDesk"></label>
                                        </div>
                                    </div>
                                    <button class="btn btn-success w-100 py-2 fw-bold rounded-3 shadow" id="btnCompletarVenta" onclick="procesarVenta()">CONFIRMAR VENTA</button>
                                </div>
                        </div>
                    </div>

                </div> 
                
                <div class="d-lg-none" style="height:20px"></div>

            </div>
        </div>
    </div>
</div>

<div class="vender-mobile-bar d-lg-none" id="venderMobileBar" aria-hidden="true">
    <div class="vender-mobile-bar__info">
        <span class="vender-mobile-bar__label">Total</span>
        <div class="d-flex align-items-baseline gap-2 flex-wrap">
            <span class="vender-mobile-bar__price" id="lblTotalMobile">$0</span>
            <span class="vender-mobile-bar__qty"><span id="lblCantidadMobile">0</span> nros</span>
        </div>
    </div>
    <button type="button" class="btn btn-success btn-lg fw-bold rounded-pill vender-mobile-bar__btn" id="btnAbrirCheckoutMobile" onclick="abrirCheckoutVentaMobile()">
        Continuar
    </button>
</div>

<div class="vender-sheet-backdrop d-lg-none" id="venderSheetBackdrop" hidden onclick="cerrarCheckoutVentaMobile()"></div>
<aside class="vender-sheet d-lg-none" id="venderCheckoutSheet" aria-hidden="true" role="dialog" aria-labelledby="venderSheetTitle">
    <div class="vender-sheet__handle" aria-hidden="true"></div>
    <div class="vender-sheet__header">
        <h6 class="mb-0 fw-bold" id="venderSheetTitle">Confirmar venta</h6>
        <button type="button" class="btn btn-link btn-sm text-muted p-0 vender-sheet__close" onclick="cerrarCheckoutVentaMobile()" aria-label="Cerrar">&times;</button>
    </div>
    <div class="vender-sheet__body">
        <div class="vender-sheet__row">
            <span class="text-muted">Cantidad</span>
            <span class="fw-bold" id="sheetCantidad">0 nros</span>
        </div>
        <div class="vender-sheet__numbers" id="sheetNumeros"></div>
        <div class="vender-sheet__total">
            <span class="text-muted small">Total a registrar</span>
            <span class="vender-sheet__total-value" id="sheetTotal">$0</span>
        </div>
    </div>
    <div class="vender-sheet__footer">
        <div class="vender-sheet__status" id="venderSheetStatus" hidden role="status" aria-live="polite">
            <span class="spinner-border spinner-border-sm text-success" aria-hidden="true"></span>
            <span>Registrando venta, espera un momento…</span>
        </div>
        <input type="radio" class="btn-check d-none" checked name="metodoPagoMobile" id="pagoEfecMob" value="Venta Manual">
        <button type="button" class="btn btn-success btn-lg w-100 fw-bold rounded-pill vender-sheet__confirm" id="btnCompletarVentaMobile" onclick="confirmarVentaDesdeSheet()">
            CONFIRMAR VENTA
        </button>
    </div>
</aside>


<?php
require_once ROOT_PATH . '/bootstrap/container.php';

use App\Application\Pricing\RaffleQuantityPricing;

$venderPricingJson = json_encode(
    RaffleQuantityPricing::fromConfig(AppContainer::get()->config())->toPublicArray(),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);

$extra_js = '
<link href="' . ASSETS_URL . '/libs/select2/css/select2.min.css" rel="stylesheet" />
<link href="' . ASSETS_URL . '/libs/select2/css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.VENDER_PRICING = ' . $venderPricingJson . ';</script>
<script src="' . ASSETS_URL . '/js/money-cop.js?v=1"></script>
<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=18"></script>
<script src="' . ASSETS_URL . '/libs/select2/js/select2.min.js"></script>
<script src="' . ASSETS_URL . '/js/departamentos-ciudades.js"></script>
<script src="' . ASSETS_URL . '/js/cr-grilla-numeros.js?v=2"></script>
<script src="' . ASSETS_URL . '/js/vender.js?v=20"></script>
';
include_once ROOT_PATH . "/includes/footer.php";
?>