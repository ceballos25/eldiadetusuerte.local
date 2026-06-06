    <div class="modal cr-checkout-sheet" id="modalCheckout" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content border-0 overflow-hidden">

                <div class="cr-sheet-handle-wrap">
                    <div class="cr-sheet-handle" aria-hidden="true"></div>
                </div>

                <div class="cr-checkout-head">
                    <div class="cr-checkout-head__top">
                        <h5 class="cr-checkout-head__title">Completa tu compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="cr-checkout-ticket" id="modalCheckoutTicket">
                        <div class="cr-checkout-ticket__row">
                            <div class="cr-checkout-ticket__meta">
                                <div class="cr-checkout-ticket__meta-top">
                                    <span class="cr-checkout-ticket__qty cr-qty-badge" id="modalCheckoutCantidad">0 nros</span>
                                    <span class="cr-checkout-promo-note d-none" id="checkoutPromoNote"></span>
                                </div>
                                <div class="cr-checkout-ticket__nums" id="checkoutNumerosChips"></div>
                            </div>
                            <span class="cr-checkout-ticket__total-value" id="resumenTotal">$0</span>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 flex-grow-1">
                    <form id="formCheckout" class="cr-checkout-form">
                        <input type="hidden" id="totalPagarInput" name="totalPagar">
                        <span class="d-none" id="resumenNumeros" aria-hidden="true"></span>

                        <section class="cr-checkout-panel">
                            <h6 class="cr-checkout-panel__title">
                                <span class="cr-checkout-panel__step">1</span>
                                Tus datos
                            </h6>

                            <div class="form-floating mb-2">
                                <input type="tel" class="form-control" id="celularCliente" required placeholder="Celular" autocomplete="tel">
                                <label for="celularCliente">Celular</label>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nombreCliente" required placeholder="Nombre" autocomplete="given-name">
                                        <label for="nombreCliente">Nombre</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="apellidoCliente" required placeholder="Apellido" autocomplete="family-name">
                                        <label for="apellidoCliente">Apellido</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-2">
                                <input type="email" class="form-control" id="emailCliente" required placeholder="Correo" autocomplete="email">
                                <label for="emailCliente">Correo electrónico</label>
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="select-floating-label-group">
                                        <select class="form-select select2-ubicacion" id="departamento" required>
                                            <option value="">Departamento...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="select-floating-label-group">
                                        <select class="form-select select2-ubicacion" id="ciudad" required>
                                            <option value="">Ciudad...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="cr-checkout-panel">
                            <h6 class="cr-checkout-panel__title">
                                <span class="cr-checkout-panel__step">2</span>
                                Método de pago
                            </h6>

                            <div class="cr-pay-tabs mb-3" role="tablist" aria-label="Método de pago">
                                <button type="button" class="cr-metodo-pago-btn" data-metodo="pse" onclick="seleccionarMetodo('pse')">
                                    <i class="ti ti-building-bank" aria-hidden="true"></i>
                                    PSE
                                </button>
                                <button type="button" class="cr-metodo-pago-btn" data-metodo="transferencia" onclick="seleccionarMetodo('transferencia')">
                                    <i class="ti ti-transfer" aria-hidden="true"></i>
                                    Transferencia
                                </button>
                            </div>

                            <div id="contenedorMetodoPago" class="cr-checkout-pay-details">

                                <div id="metodoPSE" class="metodo-pago d-none">
                                    <p class="cr-checkout-pay-hint mb-0">
                                        <i class="ti ti-shield-check text-success" aria-hidden="true"></i>
                                        Serás redirigido al banco de forma segura.
                                    </p>
                                </div>

                                <div id="metodoTransferencia" class="metodo-pago d-none">
                                    <p class="cr-checkout-pay-hint mb-2">💸 Transfiere el total exacto a una de estas cuentas:</p>
                                    <div class="cr-cuentas-grid">
                                        <div class="cr-cuenta-card">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank">Llave Bre-B 🔑</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="copiarTexto('llave')">Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="llave">@jorge5448</span>
                                            <span class="cr-cuenta-card__name">Jorge Herrera</span>
                                        </div>
                                        <div class="cr-cuenta-card">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank">Ahorros Bancolombia</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="copiarTexto('bancolombia')">Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="bancolombia">43800000923</span>
                                            <span class="cr-cuenta-card__name">Jorge Herrera</span>
                                        </div>
                                        <div class="cr-cuenta-card">
                                            <div class="cr-cuenta-card__head">
                                                <span class="cr-cuenta-card__bank">Nequi / Daviplata</span>
                                                <button type="button" class="btn btn-sm btn-copy-cuenta" onclick="copiarTexto('nequi')">Copiar</button>
                                            </div>
                                            <span class="cr-cuenta-card__num" id="nequi">3105888748</span>
                                            <span class="cr-cuenta-card__name">Jorge Herrera</span>
                                        </div>
                                    </div>
                                    <label class="cr-upload-zone" id="comprobantePagoZone" for="comprobantePago">
                                        <i class="ti ti-cloud-upload" id="comprobantePagoIcon" aria-hidden="true"></i>
                                        <span class="cr-upload-zone__label" id="comprobantePagoLabel">Toca para subir comprobante</span>
                                        <span class="cr-upload-zone__status d-none" id="comprobantePagoStatus">Comprobante cargado</span>
                                        <span class="cr-upload-zone__hint" id="comprobantePagoHint">JPG o PNG</span>
                                    </label>
                                    <input type="file" class="visually-hidden" id="comprobantePago" accept="image/*,application/pdf">
                                </div>

                            </div>
                        </section>
                    </form>
                </div>

                <div class="cr-checkout-footer d-none" id="checkoutFooterActions">
                    <div id="footerMetodoPSE" class="cr-checkout-footer__action d-none">
                        <button type="button" class="btn btn-warning btn-cta-pago w-100 fw-bold" id="btnPagarFinal" onclick="iniciarPagoPSE()">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Ir a pagar con PSE
                        </button>
                    </div>
                    <div id="footerMetodoTransferencia" class="cr-checkout-footer__action d-none">
                        <button type="button" class="btn btn-warning btn-cta-pago w-100 fw-bold" id="btnConfirmarTransferencia" onclick="procesarTransferencia(event)">
                            Confirmar pago
                        </button>
                    </div>
                    <p class="cr-checkout-footer__trust">
                        <i class="ti ti-lock-square-rounded text-success" aria-hidden="true"></i>
                        Pago seguro · Confirmación inmediata
                        <img src="" alt="PSE" class="cr-checkout-footer__pse" data-site-pse loading="lazy">
                    </p>
                </div>

            </div>
        </div>
    </div>
