    <footer class="site-footer">
        <div class="container py-5">

            <div class="row g-4 text-center text-md-start">

                <div class="col-md-4">
                    <h5 class="fw-bold site-footer-heading"><?= htmlspecialchars(edts_site_name(), ENT_QUOTES, 'UTF-8') ?> 🍀</h5>
                    <p class="small site-footer-text mb-0">
                        Plataforma oficial de El Día de Tu Suerte.
                        Transparencia, respaldo y seguridad en cada dinámica.
                    </p>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold text-uppercase mb-3 site-footer-heading">Enlaces de interés</h6>
                    <ul class="list-unstyled site-footer-list small mb-0">
                        <li>
                            <a href="#compra" class="site-footer-link">
                                <i class="ti ti-shopping-cart" aria-hidden="true"></i>
                                <span>Comprar stickers</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= htmlspecialchars(edts_public_url() . '/assets/doc/' . rawurlencode('politica de proteccion de datos personale.pdf'), ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link" target="_blank" rel="noopener">
                                <i class="ti ti-shield-lock" aria-hidden="true"></i>
                                <span>Política de privacidad</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= htmlspecialchars(edts_public_url() . '/assets/doc/tyc-v4.pdf', ENT_QUOTES, 'UTF-8') ?>" class="site-footer-link" target="_blank" rel="noopener">
                                <i class="ti ti-file-text" aria-hidden="true"></i>
                                <span>Términos y condiciones</span>
                            </a>
                        </li>
                        <li id="consultarNumeros" class="mt-2">
                            <button type="button" class="btn btn-warning btn-sm fw-bold site-footer-cta" data-bs-toggle="modal" data-bs-target="#modalBuscarTickets">
                                <i class="ti ti-ticket me-1" aria-hidden="true"></i>
                                Consultar nros
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold text-uppercase mb-3 site-footer-heading">Contacto</h6>
                    <ul class="list-unstyled site-footer-list small mb-0">
                        <li>
                            <i class="ti ti-phone site-footer-icon" aria-hidden="true"></i>
                            <a href="https://api.whatsapp.com/send/?phone=573171684127" class="site-footer-link" data-whatsapp-link target="_blank" rel="noopener">(+57) 317 168 4127</a>
                        </li>
                        <li>
                            <i class="ti ti-mail site-footer-icon" aria-hidden="true"></i>
                            <a href="mailto:info@eldiadetusuerte.com" class="site-footer-link">info@eldiadetusuerte.com</a>
                        </li>
                        <li>
                            <i class="ti ti-map-pin site-footer-icon" aria-hidden="true"></i>
                            <span>Colombia</span>
                        </li>
                        <li>
                            <i class="ti ti-credit-card site-footer-icon" aria-hidden="true"></i>
                            <span>Pagos procesados vía PSE</span>
                        </li>
                    </ul>
                    <div class="mt-3">
                        <img src="" height="44" class="site-footer-pse" alt="PSE" data-site-pse loading="lazy" decoding="async">
                    </div>
                </div>

            </div>

            <hr class="site-footer-hr my-4">

            <div class="text-center small site-footer-copy pb-2">
                © <?= date('Y'); ?> <?= htmlspecialchars(edts_site_name(), ENT_QUOTES, 'UTF-8') ?> · Todos los derechos reservados
                <br>
                Desarrollado por
                <a href="https://cristianceballos.com/"
                    target="_blank" rel="noopener noreferrer" class="site-footer-dev">
                    Cristian Ceballos
                    <i class="ti ti-external-link site-footer-dev__icon" aria-hidden="true"></i>
                </a>
            </div>

        </div>
    </footer>
