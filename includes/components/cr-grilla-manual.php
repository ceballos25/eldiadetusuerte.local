<?php
declare(strict_types=1);

/**
 * Grilla de números manual (landing + vender) — móvil first.
 */
$crGrillaWrapperId = $crGrillaWrapperId ?? 'selectorManualNumeros';
$crGrillaGridId = $crGrillaGridId ?? 'gridNumerosManual';
$crGrillaSearchId = $crGrillaSearchId ?? 'buscarNumeroManual';
$crGrillaCountId = $crGrillaCountId ?? 'manualSeleccionCount';
$crGrillaStatsId = $crGrillaStatsId ?? $crGrillaGridId . 'Stats';
$crGrillaPagerId = $crGrillaPagerId ?? $crGrillaGridId . 'Pager';
$crGrillaFilterFn = $crGrillaFilterFn ?? 'filtrarNumerosManual';
$crGrillaWrapperClass = $crGrillaWrapperClass ?? 'card border-0 shadow-sm d-none mt-3';
$crGrillaAlertHtml = $crGrillaAlertHtml ?? '';
?>
<div class="<?= htmlspecialchars($crGrillaWrapperClass, ENT_QUOTES, 'UTF-8') ?> cr-grilla-panel" id="<?= htmlspecialchars($crGrillaWrapperId, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($crGrillaAlertHtml !== ''): ?>
        <?= $crGrillaAlertHtml ?>
    <?php endif; ?>

    <div class="cr-grilla-panel__head">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <p class="cr-grilla-panel__title mb-0">Elige tus nros</p>
            <span class="cr-grilla-count-badge">
                <strong id="<?= htmlspecialchars($crGrillaCountId, ENT_QUOTES, 'UTF-8') ?>">0</strong> / 1 mín.
            </span>
        </div>
    </div>

    <div id="numerosSeleccionadosBar" class="cr-seleccionados-bar" aria-live="polite"></div>

    <div class="cr-grilla-search-wrap">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-end-0"><i class="ti ti-search text-muted"></i></span>
            <input type="tel"
                id="<?= htmlspecialchars($crGrillaSearchId, ENT_QUOTES, 'UTF-8') ?>"
                class="form-control border-start-0 ps-0"
                placeholder="Buscar nro…"
                inputmode="numeric"
                maxlength="6"
                autocomplete="off"
                oninput="<?= htmlspecialchars($crGrillaFilterFn, ENT_QUOTES, 'UTF-8') ?>()">
        </div>
    </div>

    <div id="<?= htmlspecialchars($crGrillaGridId, ENT_QUOTES, 'UTF-8') ?>" class="cr-grilla-numeros cr-grilla-numeros--grid" role="listbox" aria-label="Nros de la dinámica"></div>

    <div class="cr-grilla-stats d-none" id="<?= htmlspecialchars($crGrillaStatsId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>

    <div class="cr-grilla-pager d-none" id="<?= htmlspecialchars($crGrillaPagerId, ENT_QUOTES, 'UTF-8') ?>"></div>
</div>
