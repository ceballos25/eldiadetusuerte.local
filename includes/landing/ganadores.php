<?php
$ganadores = [
    ['img' => edts_cdn('images/profile/dinamica-flash.jpg'), 'label' => 'Dinámica Flash'],
    ['img' => edts_cdn('images/profile/cali.jpg'), 'label' => 'Entrega en Cali'],
    ['img' => edts_cdn('images/profile/bogota.png'), 'label' => 'Evento Todo Terreno XTZ + 3 Palitos en Bogotá'],
    ['img' => edts_cdn('images/profile/ganador-girardota.png'), 'label' => 'MT15 + 2 Palitos en Girardota'],
    ['img' => edts_cdn('images/profile/ganador-combo-navideno-V3.png'), 'label' => 'Combo navideño recompensa #1'],
    ['img' => edts_cdn('images/profile/dinamica-twoo-combo.png'), 'label' => 'Combo navideño recompensa #2'],
    ['img' => edts_cdn('images/profile/mt-15-nov2.png'), 'label' => 'Noviembre MT-15'],
    ['img' => edts_cdn('images/profile/ganadora_mt15.png'), 'label' => 'Noviembre MT-15'],
    ['img' => edts_cdn('images/profile/nmx-oct3.png'), 'label' => 'Octubre NMAX V3'],
    ['img' => edts_cdn('images/profile/ganador-mazda.png'), 'label' => 'Agosto Mazda'],
    ['img' => edts_cdn('images/profile/ganadora-mt-2025-mayo.jpg'), 'label' => 'Mayo MT-15'],
    ['img' => edts_cdn('images/profile/ganadorfz.png'), 'label' => 'Octubre FZ 3.0'],
    ['img' => edts_cdn('images/profile/ganadormt.png'), 'label' => 'Julio MT-15'],
    ['img' => edts_cdn('images/profile/ganadornmax.png'), 'label' => 'Agosto NMAX 2025'],
];
?>
<section class="texto-ganadores">
    <div>
        <h2 class="title-ganadores text-center title-premios">¡Quienes ya vivieron su suerte! 🥳</h2>
    </div>
</section>

<section class="container-ganadores position-relative overflow-hidden mb-5">

    <canvas id="confetti-canvas"></canvas>

    <div style="max-width: 600px; margin: 0 auto;">

    <div id="ganadores-carousel" class="splide">
        <div class="splide__track">
            <ul class="splide__list" id="ganadoresMainSlides">
                <?php foreach ($ganadores as $g): ?>
                <li class="splide__slide text-center">
                    <p class="ganador-one"><?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <img src="<?= htmlspecialchars($g['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div id="ganadores-thumbnails" class="splide mt-2">
        <div class="splide__track">
            <ul class="splide__list" id="ganadoresThumbSlides">
                <?php foreach ($ganadores as $g): ?>
                <li class="splide__slide">
                    <img src="<?= htmlspecialchars($g['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    </div>
</section>
