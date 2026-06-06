<?php
/**
 * Imágenes del combo principal.
 * Para agregar más: añade otra línea al array; con 2+ imágenes se activa el carrusel solo.
 */
$heroSlides = [
    ['src' => edts_cdn('principal/principal.png'), 'alt' => 'Combo Extremo — MT 15, XTZ 150 y 10 regalos extra de $500.000'],
    // ['src' => edts_cdn('images/principal/otra-imagen.jpg'), 'alt' => 'Descripción de la imagen'],
];
$heroUseCarousel = count($heroSlides) > 1;
?>
<section class="cr-hero-section py-3">
    <div class="container">
        <div class="cr-hero-row">

            <!-- 1. Imagen primero en mobile -->
            <div class="cr-hero-image">
                <div class="cr-hero-image-inner text-center">
                    <?php if ($heroUseCarousel): ?>
                        <section id="main-carousel" class="splide cr-hero-splide">
                            <div class="splide__track">
                                <ul class="splide__list" id="heroMainSlides">
                                    <?php foreach ($heroSlides as $i => $slide): ?>
                                    <li class="splide__slide">
                                        <img class="cr-hero-media-img"
                                             src="<?= htmlspecialchars($slide['src'], ENT_QUOTES, 'UTF-8') ?>"
                                             alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>"
                                             loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                                             decoding="async"
                                             <?= $i === 0 ? 'fetchpriority="high"' : '' ?>>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </section>
                        <section id="thumbnail-carousel" class="splide cr-hero-splide-thumbs mt-2 mx-auto">
                            <div class="splide__track">
                                <ul class="splide__list" id="heroThumbSlides">
                                    <?php foreach ($heroSlides as $slide): ?>
                                    <li class="splide__slide">
                                        <img src="<?= htmlspecialchars($slide['src'], ENT_QUOTES, 'UTF-8') ?>"
                                             alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>"
                                             loading="lazy"
                                             decoding="async">
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </section>
                    <?php else: ?>
                        <?php $slide = $heroSlides[0]; ?>
                        <img class="cr-hero-media-img"
                             src="<?= htmlspecialchars($slide['src'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>"
                             loading="eager"
                             decoding="async"
                             fetchpriority="high">
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Título -->
            <div class="cr-hero-head">
                <h2 class="hero-title mb-1" id="landingHeroTitle">
                    🛵⚡️ COMBO <span class="millonario">EXTREMO</span>
                </h2>
                <p class="cr-hero-fecha mb-0 text-center fw-semibold" id="landingHeroFecha">
                    🗓️ Juega 10 de Julio con la de Medellín
                </p>
            </div>

            <!-- 3. Premios + barra -->
            <div class="cr-hero-side">
                <div class="card border-0 shadow-sm premio-mayor-card mb-3">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="premio-mayor-icon">
                            <i class="ti ti-trophy fs-4"></i>
                        </div>
                        <div>
                            <p class="premio-mayor-label mb-1" id="landingMayorTitulo">🏆 Nro principal</p>
                            <p class="premio-mayor-desc premio-mayor-desc--product mb-0" id="landingMayorDesc">Yamaha MT 15</p>
                            <p class="premio-mayor-hook mb-0">¡Nueva, cero kilómetros y lista para rodar a tu nombre!</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm text-center w-100 cr-puesto-card cr-puesto-card--invertido">
                            <div class="card-body py-3">
                                <p class="cr-puesto-label mb-1">🔥 Nro invertido</p>
                                <h3 class="cr-puesto-titulo mb-0">
                                    <span class="color-dinero-premio">Yamaha XTZ 150</span>
                                </h3>
                                <p class="cr-puesto-hook mb-0">Si tu nro sale al revés… ¡te la llevas!</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm text-center w-100 cr-puesto-card cr-puesto-card--extra">
                            <div class="card-body py-3">
                                <p class="cr-puesto-label mb-1">💰 10 regalos extra</p>
                                <h3 class="cr-puesto-titulo mb-0">
                                    <span class="color-dinero-premio">$500.000</span>
                                    <span class="cr-puesto-titulo__suffix"> c/u</span>
                                </h3>
                                <p class="cr-puesto-hook mb-2">Diez personas se van con medio millón en el bolsillo 🙌</p>
                                <div id="bendecidosCardsContainer" class="cr-bendecidos-chips d-flex flex-wrap justify-content-center gap-1">
                                    <?php if (!empty($bendecidosCards)): ?>
                                        <?php foreach ($bendecidosCards as $c): ?>
                                            <div class="bendecidos-numeros<?= !empty($c['premiado_vendido']) ? ' premiado' : '' ?>"><?= htmlspecialchars((string)$c['number'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="small text-muted mb-0 w-100">Los nros extra se publican aquí al marcarlos premium en admin.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm text-center w-100 cr-puesto-card cr-puesto-card--info">
                            <div class="card-body py-3">
                                <p class="cr-puesto-label mb-2">⚡ Entra ya — valores</p>
                                <p class="mb-1 small fw-semibold">Cada nro a $1.200 · Mínimo 15 nros</p>
                                <p class="mb-0 small fw-semibold cr-valor-destacado">🔥 Desde 40 nros: solo $1.000 c/u</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm cr-progress-card">
                    <div class="card-body py-3">
                        <div class="cr-progress-label">
                            <span>Ventas realizadas</span>
                            <span id="porcentajeTexto">0%</span>
                        </div>
                        <div class="cr-progress-race my-1">
                            <div class="progress">
                                <div id="barraProgreso" class="progress-bar" style="width: 0%" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span id="caballoProgreso" class="cr-progress-caballo" aria-hidden="true">🛵</span>
                            <span class="cr-progress-meta" aria-hidden="true">🏁</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
