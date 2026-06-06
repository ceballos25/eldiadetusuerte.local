<?php

require_once '../config/config.php';
$page_title = 'Reportería y consultas';
$extra_css = '
<link href="https://unpkg.com/tabulator-tables@6.2.5/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
  .report-builder-card { border-radius: 12px; }
  .chip-field { font-size: 0.75rem; }
  #reportTable { min-height: 420px; }
  .report-table-wrap { position: relative; min-height: 520px; }
  .report-loader {
    position: absolute;
    inset: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(1px);
  }
  .builder-section-title { font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase; color: #6c757d; font-weight: 700; }
</style>';
include_once ROOT_PATH . '/includes/head.php';
?>

<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">
    <?php include_once ROOT_PATH . '/includes/sidebar.php'; ?>

    <div class="body-wrapper">
        <?php include_once ROOT_PATH . '/includes/header.php'; ?>

        <div class="body-wrapper-inner">
            <div class="container-fluid py-3">

                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-3">
                    <div>
                        <h2 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="ti ti-chart-infographic text-primary"></i> Reportería visual
                        </h2>
                        <p class="text-muted mb-0 small">
                            Arma tablas dinámicas, agrupaciones y totales de manera libre: solo campos permitidos por seguridad.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i> Dashboard</a>
                        <button type="button" class="btn btn-dark btn-sm" id="btnExportCsv"><i class="ti ti-file-download me-1"></i> CSV</button>
                        <button type="button" class="btn btn-success btn-sm" id="btnExportExcel"><i class="ti ti-file-spreadsheet me-1"></i> Excel</button>
                        <button type="button" class="btn btn-danger btn-sm" id="btnExportPdf"><i class="ti ti-file-type-pdf me-1"></i> PDF</button>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <div class="card border-0 shadow-sm report-builder-card h-100">
                            <div class="card-body">
                                <div class="builder-section-title mb-2">Conjunto de datos</div>
                                <select id="datasetSelect" class="form-select form-select-sm mb-3"></select>

                                <div class="builder-section-title mb-2">Rango rápido (opcional)</div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Desde</label>
                                        <input type="date" id="dateFrom" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Hasta</label>
                                        <input type="date" id="dateTo" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="builder-section-title mb-2">Plantillas</div>
                                <div class="input-group input-group-sm mb-3">
                                    <select id="presetSelect" class="form-select"><option value="">— Cargar plantilla —</option></select>
                                    <button class="btn btn-outline-primary" type="button" id="btnApplyPreset" title="Aplicar"><i class="ti ti-check"></i></button>
                                </div>

                                <div class="builder-section-title mb-2">Dimensiones (agrupar por)</div>
                                <div class="input-group input-group-sm mb-2">
                                    <select id="dimField" class="form-select"></select>
                                    <input type="text" id="dimAlias" class="form-control" placeholder="alias" style="max-width:110px">
                                    <button class="btn btn-outline-secondary" type="button" id="btnAddDim"><i class="ti ti-plus"></i></button>
                                </div>
                                <div id="dimList" class="d-flex flex-wrap gap-1 mb-3"></div>

                                <div class="builder-section-title mb-2">Medidas (agregar)</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-5">
                                        <select id="measureFn" class="form-select form-select-sm">
                                            <option value="SUM">SUM</option>
                                            <option value="COUNT">COUNT</option>
                                            <option value="AVG">AVG</option>
                                            <option value="MIN">MIN</option>
                                            <option value="MAX">MAX</option>
                                        </select>
                                    </div>
                                    <div class="col-7">
                                        <select id="measureField" class="form-select form-select-sm"></select>
                                    </div>
                                </div>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" id="measureAlias" class="form-control form-control-sm" placeholder="alias medida">
                                    <button class="btn btn-outline-secondary" type="button" id="btnAddMeasure"><i class="ti ti-plus"></i></button>
                                </div>
                                <div id="measureList" class="d-flex flex-wrap gap-1 mb-3"></div>

                                <div class="builder-section-title mb-2">Filtros</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <select id="filterField" class="form-select form-select-sm"></select>
                                    </div>
                                    <div class="col-6">
                                        <select id="filterOp" class="form-select form-select-sm">
                                            <option value="eq">=</option>
                                            <option value="ne">≠</option>
                                            <option value="gt">&gt;</option>
                                            <option value="gte">≥</option>
                                            <option value="lt">&lt;</option>
                                            <option value="lte">≤</option>
                                            <option value="like">LIKE</option>
                                            <option value="between">Entre (a|b)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="input-group input-group-sm mb-2">
                                    <input type="text" id="filterValue" class="form-control form-control-sm" placeholder="Valor o a|b para entre">
                                    <button class="btn btn-outline-secondary" type="button" id="btnAddFilter"><i class="ti ti-plus"></i></button>
                                </div>
                                <div id="filterList" class="small text-muted mb-3"></div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Ordenar por</label>
                                        <input type="text" id="orderBy" class="form-control form-control-sm" placeholder="alias columna">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Dirección</label>
                                        <select id="orderDir" class="form-select form-select-sm">
                                            <option value="DESC">DESC</option>
                                            <option value="ASC">ASC</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small mb-0">Límite filas</label>
                                    <input type="number" id="rowLimit" class="form-control form-control-sm" value="2000" min="1" max="10000">
                                </div>

                                <button type="button" class="btn btn-primary w-100 mb-2" id="btnRun">
                                    <i class="ti ti-player-play me-1"></i> Ejecutar consulta
                                </button>

                                <hr>
                                <div class="builder-section-title mb-2">Guardar / cargar</div>
                                <div class="input-group input-group-sm mb-2">
                                    <select id="savedSelect" class="form-select"><option value="">— Reportes guardados —</option></select>
                                    <button class="btn btn-outline-secondary" type="button" id="btnLoadSaved" title="Cargar"><i class="ti ti-download"></i></button>
                                    <button class="btn btn-outline-danger" type="button" id="btnDeleteSaved" title="Eliminar"><i class="ti ti-trash"></i></button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="saveName" class="form-control form-control-sm" placeholder="Nombre del reporte">
                                    <button class="btn btn-success" type="button" id="btnSave"><i class="ti ti-device-floppy"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <div class="card border-0 shadow-sm report-builder-card">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="fw-bold mb-0"><i class="ti ti-table me-1"></i> Resultado</span>
                                <span class="small text-muted" id="resultMeta"></span>
                            </div>
                            <div class="card-body pt-0">
                                <div class="report-table-wrap admin-table-desktop">
                                    <div id="reportTableLoader" class="report-loader d-none" aria-live="polite" aria-busy="true">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
                                            <div class="small text-muted">Generando reporte, por favor espera...</div>
                                        </div>
                                    </div>
                                    <div id="reportTable"></div>
                                </div>
                                <div class="admin-cards-mobile p-3" id="reportesMobile"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.2.5/dist/js/tabulator.min.js"></script>
<?php
$extra_js = '<script src="' . ASSETS_URL . '/js/admin-mobile.js?v=16"></script><script src="' . ASSETS_URL . '/js/reportes.js?v=7"></script>';
include_once ROOT_PATH . '/includes/footer.php';
?>
