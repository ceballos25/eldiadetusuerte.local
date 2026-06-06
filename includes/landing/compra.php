<section id="compra" class="py-4 border-top">
        <div class="container">

            <h2 class="cr-section-title mb-3">Paquetes</h2>

            <div class="row g-4">

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white py-3 px-3">
                            <p class="fw-semibold mb-0 text-dark">
                                <i class="ti ti-grid-dots me-2" style="color: var(--cr-gold);"></i>
                                Selecciona la cantidad
                            </p>
                        </div>

                        <div class="card-body bg-light cr-theme">
                            <?php include ROOT_PATH . '/includes/components/cr-paquetes-grid.php'; ?>
                        </div>
                    </div>

                    <?php include ROOT_PATH . '/includes/components/cr-grilla-manual.php'; ?>
                </div>

                <div class="col-lg-4 d-none d-lg-block">
                    <div class="card border-0 shadow-sm cr-checkout-sidebar sticky-top">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Tu compra</h5>

                            <div id="listaTicketsDesktop" class="cr-checkout-sidebar__nums mb-3"></div>

                            <div class="cr-checkout-sidebar__summary">
                                <div class="cr-checkout-sidebar__row">
                                    <span class="text-muted">Cantidad</span>
                                    <strong id="cantTicketsDesktop" class="cr-checkout-sidebar__qty">0 nros</strong>
                                </div>
                                <div class="cr-checkout-sidebar__row cr-checkout-sidebar__row--total">
                                    <span>Total</span>
                                    <strong class="cr-checkout-sidebar__total" id="totalDineroDesktop">$0</strong>
                                </div>
                                <p class="cr-checkout-promo-note cr-checkout-sidebar__promo d-none mb-0" id="sidebarPromoNote"></p>
                            </div>

                            <button type="button" class="btn btn-warning btn-cta-pago w-100 fw-bold mt-3" onclick="abrirCheckout()" id="btnPagarDesktop" disabled>
                                Continuar al pago
                            </button>

                            <p class="cr-checkout-sidebar__trust small text-muted text-center mt-3 mb-0">
                                <i class="ti ti-lock-square-rounded text-success"></i>
                                Pago seguro · Confirmación inmediata
                            </p>
                            <div class="text-center mt-2">
                                <img src="" alt="PSE" class="cr-checkout-sidebar__pse" data-site-pse loading="lazy">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
