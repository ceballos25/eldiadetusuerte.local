    <div class="modal fade" id="modalBuscarTickets" tabindex="-1" aria-labelledby="modalBuscarTicketsLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalBuscarTicketsLabel">🔍 Consultar nros</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small text-center mb-3 px-1">
                        Ingresa el <strong>celular con el que realizaste la compra</strong>.                        
                    </p>

                    <label for="inputBuscarTickets" class="form-label fw-semibold small mb-1">Celular de la compra</label>
                    <input
                        type="tel"
                        id="inputBuscarTickets"
                        class="form-control form-control-lg text-center fw-semibold"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        maxlength="10"
                        pattern="[0-9]{10}"
                        aria-describedby="buscarTicketsHint buscarTicketsContador"
                        required
                    >
                    <div class="d-flex justify-content-between align-items-center mt-1 mb-3">
                        <span id="buscarTicketsHint" class="form-text mb-0">Solo dígitos, sin espacios ni guiones</span>
                        <span id="buscarTicketsContador" class="form-text mb-0 text-muted" aria-live="polite">0/10</span>
                    </div>

                    <button type="button" id="btnBuscarTickets" class="btn btn-warning w-100 fw-bold py-2" onclick="buscarTickets()" disabled>
                        Consultar nros
                    </button>
                    <div id="resultadoBusqueda" class="mt-3 text-start"></div>
                </div>
            </div>
        </div>
    </div>
