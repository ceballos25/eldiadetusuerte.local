<?php
require_once '../config/config.php';
$page_title = 'Webhooks OpenPay';
include_once ROOT_PATH . '/includes/head.php';
?>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . '/includes/sidebar.php'; ?>
    <div class="body-wrapper">
        <?php include_once ROOT_PATH . '/includes/header.php'; ?>
        <div class="body-wrapper-inner">
            <div class="container-fluid py-3">

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h2 class="fw-bold mb-0"><i class="ti ti-webhook me-2"></i>Webhooks OpenPay</h2>
                    <button class="btn btn-outline-secondary btn-sm" onclick="cargarPendientes()">
                        <i class="ti ti-refresh"></i> Actualizar cola
                    </button>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Registro en OpenPay</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            URL de webhook en OpenPay (producción: microservicio
                            <strong>pagos.eldiadetusuerte.com</strong>):
                            <code id="openpayWebhookUrl" class="user-select-all"><?= htmlspecialchars(OPENPAY_WEBHOOK_URL, ENT_QUOTES, 'UTF-8') ?></code>
                        </p>
                        <p class="text-muted small mb-2">
                            Return URL PSE (success):
                            <code class="user-select-all"><?= htmlspecialchars(OPENPAY_RETURN_URL, ENT_QUOTES, 'UTF-8') ?></code>
                        </p>
                        <p class="text-muted small mb-3">
                            Eventos estándar: <strong>charge.succeeded</strong> (aprueba venta),
                            <strong>charge.failed</strong> / <strong>charge.cancelled</strong> / <strong>charge.refunded</strong> (rechazan y liberan nros).
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-primary btn-sm" onclick="registrarWebhookOpenPay()">
                                <i class="ti ti-plus"></i> Registrar webhook en OpenPay
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="listarWebhooksOpenPay()">
                                <i class="ti ti-list"></i> Ver registrados
                            </button>
                        </div>
                        <div id="openpayWebhooksList" class="small"></div>
                    </div>
                </div>

                <h5 class="fw-bold mb-2">Cola local (pendientes / error)</h5>
                <p class="text-muted small">Eventos guardados en servidor. Puede reprocesarlos si falló la venta automática.</p>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive admin-table-desktop">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>UUID</th>
                                        <th>Evento</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="bodyWebhooks"></tbody>
                            </table>
                        </div>
                        <div class="admin-cards-mobile p-3" id="webhooksMobile"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once ROOT_PATH . '/includes/footer.php'; ?>
<script src="<?= ASSETS_URL ?>/js/admin-mobile.js?v=16"></script>
<script src="<?= ASSETS_URL ?>/js/webhooks.js?v=7"></script>
