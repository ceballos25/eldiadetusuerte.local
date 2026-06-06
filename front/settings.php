<?php
require_once "../config/config.php";
$page_title = "Ajustes del sitio";
$extra_css = '
<style>
  .settings-main-card { border-radius: 12px; overflow: hidden; }
  .settings-list .settings-row { padding: 1rem 1.25rem; border-bottom: 1px solid var(--bs-border-color, #dee2e6); }
  .settings-list .settings-row:last-child { border-bottom: none; }
  .settings-list .settings-title { font-size: 0.95rem; font-weight: 600; color: #212529; }
  .settings-list .settings-help { font-size: 0.85rem; color: #6c757d; max-width: 42rem; line-height: 1.45; }
  .settings-list .form-switch .form-check-input { width: 2.75em; height: 1.35rem; cursor: pointer; }
  .settings-list .form-switch .form-check-label { cursor: pointer; user-select: none; padding-top: 0.15rem; }
  .settings-section-header {
    padding: 1rem 1.25rem 0.75rem;
    background: linear-gradient(180deg, #f8f9fb 0%, #fff 100%);
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
  }
  .settings-section-header + .settings-row { border-top: none; }
  .settings-section-title { font-size: 0.9rem; font-weight: 700; color: #4361ee; text-transform: uppercase; letter-spacing: 0.03em; }
  .settings-section-subtitle { font-size: 0.82rem; color: #6c757d; margin-top: 0.2rem; }
  .settings-tip {
    background: #eef4ff;
    border: 1px solid #cfe0ff;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    color: #334155;
  }
  .settings-advanced summary {
    cursor: pointer;
    font-weight: 600;
    color: #64748b;
    list-style: none;
  }
  .settings-advanced summary::-webkit-details-marker { display: none; }
  .settings-advanced summary .ti { transition: transform 0.2s; }
  .settings-advanced[open] summary .ti-chevron-right { transform: rotate(90deg); }
  .settings-row { transition: background-color 0.35s ease; }
  .settings-row--saved {
    background-color: #ecfdf5 !important;
    box-shadow: inset 3px 0 0 #198754;
  }
  .ajs-notifier {
    z-index: 11000 !important;
    width: min(420px, calc(100vw - 2rem));
  }
  .ajs-notifier .ajs-message {
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    border-radius: 10px !important;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18) !important;
    text-align: center;
    padding: 0.85rem 1.1rem !important;
  }
  .ajs-notifier .ajs-message.ajs-success {
    background: #198754 !important;
    color: #fff !important;
    border: 2px solid #146c43 !important;
  }
  .ajs-notifier .ajs-message.ajs-error {
    background: #dc3545 !important;
    color: #fff !important;
    border: 2px solid #b02a37 !important;
  }
</style>';
include_once ROOT_PATH . "/includes/head.php";
?>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical">

    <?php include_once ROOT_PATH . "/includes/sidebar.php" ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . "/includes/header.php" ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid py-3">

                <div class="row mb-4 align-items-center flex-wrap gap-3">
                    <div class="col">
                        <h2 class="mb-1 fw-bold d-flex align-items-center gap-2">
                            <i class="ti ti-settings text-primary"></i> Ajustes del sitio
                        </h2>
                        <p class="text-muted small mb-0">
                            Aquí controlas la página web pública: compras, mensajes, WhatsApp y redes sociales.
                        </p>
                    </div>
                    <div class="col-auto d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-success" onclick="guardarSettings()">
                            <i class="ti ti-device-floppy me-1"></i> Guardar todos los cambios
                        </button>
                    </div>
                </div>

                <div class="settings-tip mb-4 d-flex gap-2 align-items-start">
                    <i class="ti ti-info-circle text-primary fs-5 flex-shrink-0 mt-1"></i>
                    <div>
                        <strong>¿Cómo funciona?</strong>
                        Los interruptores se guardan solos al cambiarlos.
                        Los campos de texto usan el botón <em>Guardar</em> de cada fila, o puedes pulsar
                        <em>Guardar todos los cambios</em> arriba.
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4 settings-main-card">
                    <div class="card-body p-0">
                        <div id="settingsContainer" class="settings-list">
                            <div class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <p class="small mt-2 mb-0">Cargando ajustes…</p>
                            </div>
                        </div>
                    </div>
                </div>

                <details class="card border-0 shadow-sm settings-advanced">
                    <summary class="card-body py-3 d-flex align-items-center gap-2">
                        <i class="ti ti-chevron-right"></i>
                        <span>Opciones avanzadas (solo soporte técnico)</span>
                    </summary>
                    <div class="card-body border-top pt-3">
                        <p class="text-muted small mb-3">
                            Normalmente no necesitas entrar aquí. Sirve para crear parámetros personalizados
                            si soporte técnico te lo indica.
                        </p>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Nombre interno</label>
                                <input type="text" id="newKey" class="form-control form-control-sm"
                                    placeholder="ej: mi_opcion">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">Valor</label>
                                <input type="text" id="newValue" class="form-control form-control-sm"
                                    placeholder="Texto o valor">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-primary w-100" onclick="crearSetting()">
                                    <i class="ti ti-plus me-1"></i> Crear opción
                                </button>
                            </div>
                        </div>
                    </div>
                </details>

            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/settings.js?v=18"></script>';
include_once ROOT_PATH . "/includes/footer.php";
?>
