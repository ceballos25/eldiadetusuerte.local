<div class="vender-sheet-backdrop d-lg-none" id="landingSheetBackdrop" hidden onclick="cerrarCheckoutSheetMobile()"></div>
<aside class="vender-sheet d-lg-none" id="landingCheckoutSheet" aria-hidden="true" role="dialog" aria-labelledby="landingSheetTitle">
    <div class="vender-sheet__handle" aria-hidden="true"></div>
    <div class="vender-sheet__header">
        <h6 class="mb-0 fw-bold" id="landingSheetTitle">Confirmar compra</h6>
        <button type="button" class="btn btn-link btn-sm text-muted p-0 vender-sheet__close" onclick="cerrarCheckoutSheetMobile()" aria-label="Cerrar">&times;</button>
    </div>
    <div class="vender-sheet__body">
        <div class="vender-sheet__row">
            <span class="text-muted">Cantidad</span>
            <span class="fw-bold" id="landingSheetCantidad">0 nros</span>
        </div>
        <div class="vender-sheet__numbers" id="landingSheetNumeros"></div>
        <div class="vender-sheet__total">
            <span class="text-muted small">Total a pagar</span>
            <span class="vender-sheet__total-value" id="landingSheetTotal">$0</span>
            <span class="cr-checkout-promo-note d-none mt-2 mx-auto" id="landingSheetPromo"></span>
        </div>
    </div>
    <div class="vender-sheet__footer">
        <button type="button" class="btn btn-warning w-100 fw-bold rounded-pill vender-sheet__confirm" id="btnContinuarCheckoutMobile" onclick="confirmarCheckoutDesdeSheet()">
            CONTINUAR AL PAGO
        </button>
    </div>
</aside>
