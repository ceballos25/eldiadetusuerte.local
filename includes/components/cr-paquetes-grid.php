<?php
declare(strict_types=1);

/**
 * Paquetes oficiales COMBO EXTREMO — landing (página principal).
 *
 * @var string $crPaquetesContainerId
 * @var string $crPaquetesName
 * @var string $crCantidadManualId
 * @var string $crPaquetesRowClass
 */
$crPaquetesContainerId = $crPaquetesContainerId ?? 'paquetesNumeros';
$crPaquetesName = $crPaquetesName ?? 'paqueteNumeros';
$crCantidadManualId = $crCantidadManualId ?? 'cantidadManual';
$crPaquetesRowClass = $crPaquetesRowClass ?? 'row g-3';

$paquetesOficiales = [
    ['id' => 'paq15', 'qty' => 15, 'final' => 18000, 'normal' => null, 'badge' => null, 'badge_slug' => null],
    ['id' => 'paq20', 'qty' => 20, 'final' => 24000, 'normal' => null, 'badge' => null, 'badge_slug' => null],
    ['id' => 'paq30', 'qty' => 30, 'final' => 36000, 'normal' => null, 'badge' => 'Popular', 'badge_slug' => 'popular'],
    ['id' => 'paq40', 'qty' => 40, 'final' => 40000, 'normal' => 48000, 'badge' => 'Ahorro', 'badge_slug' => 'ahorro'],
    ['id' => 'paq100', 'qty' => 100, 'final' => 100000, 'normal' => 120000, 'badge' => 'Más vendido', 'badge_slug' => 'mas-vendido'],
    ['id' => 'paq250', 'qty' => 250, 'final' => 250000, 'normal' => 300000, 'badge' => 'VIP', 'badge_slug' => 'vip'],
];
?>
<div class="<?= htmlspecialchars($crPaquetesRowClass, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($crPaquetesContainerId, ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($paquetesOficiales as $paq): ?>
    <div class="col-6 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>"
            id="<?= htmlspecialchars($paq['id'], ENT_QUOTES, 'UTF-8') ?>" value="<?= (int)$paq['qty'] ?>">
        <label class="w-100 py-2 d-flex flex-column align-items-center justify-content-center paquete-card<?= !empty($paq['badge_slug']) ? ' paquete-card--' . $paq['badge_slug'] : '' ?>"
            for="<?= htmlspecialchars($paq['id'], ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($paq['badge']): ?>
            <span class="badge-paquete"><?= htmlspecialchars($paq['badge'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <div class="fw-bold"><?= (int)$paq['qty'] ?> nros</div>
            <?php if ($paq['normal']): ?>
            <div class="fs-6 text-muted text-decoration-line-through paquete-precio-normal">$<?= number_format($paq['normal'], 0, ',', '.') ?></div>
            <?php endif; ?>
            <div class="fs-5 fw-bold paquete-precio-final">$<?= number_format($paq['final'], 0, ',', '.') ?></div>
        </label>
    </div>
    <?php endforeach; ?>

    <div class="col-6 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>" id="paqCustom" value="custom">
        <label class="w-100 py-2 d-flex flex-column align-items-center justify-content-center paquete-card paquete-card--personalizado custom" for="paqCustom">
            <span class="badge-paquete">Personalizado</span>
            <div class="fw-bold">Otro</div>
        </label>
        <input type="tel" id="<?= htmlspecialchars($crCantidadManualId, ENT_QUOTES, 'UTF-8') ?>"
            class="form-control form-control-sm text-center mt-1" min="15" placeholder="Mín. 15 nros" style="display:none;">
    </div>

</div>
<p class="small text-muted text-center mt-2 mb-0 cr-paquetes-hint">Compra mínima 15 nros · Desde 40 nros cada uno a $1.000</p>
