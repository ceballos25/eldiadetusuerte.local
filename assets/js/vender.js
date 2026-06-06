const estado = {
    rifa: null,
    pricing: window.VENDER_PRICING || null,
    cantidadSeleccionada: 0,
    numerosSeleccionados: [],
    inventarioCompleto: [],
    statsInventario: null,
    disponiblesCount: 0,
    paginaActual: 1,
    itemsPorPagina: 40,
    config: {
        rutas: {
            clientes: '/front/ajax/clientes.ajax.php',
            rifas: '/front/ajax/rifas.ajax.php',
            ventas: '/front/ajax/ventas.ajax.php',
            numeros: '/front/ajax/numeros.ajax.php',
        }
    }
};

function parsePrecioRifaValue(value) {
    if (typeof parsePrecioCOP === 'function') return parsePrecioCOP(value);
    if (typeof adminParseCOP === 'function') return adminParseCOP(value);
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : 0;
}

function isPricingTierEnabled(value) {
    if (value === true || value === 1) return true;
    if (value === false || value === 0 || value == null) return false;
    const v = String(value).trim().toLowerCase();
    return !['0', 'false', 'no', 'off', ''].includes(v);
}

function readPricingInt(value, fallback) {
    const n = parseInt(value, 10);
    return Number.isFinite(n) && n > 0 ? n : fallback;
}

function pricingTierConfigVenta() {
    const p = estado.pricing;
    if (!p || !isPricingTierEnabled(p.enabled)) {
        return { enabled: false, firstUnit: 0, secondUnit: 0, thirdPlusUnit: 0, bulkThreshold: 0 };
    }
    return {
        enabled: true,
        firstUnit: readPricingInt(p.first_unit, 1200),
        secondUnit: readPricingInt(p.second_unit ?? p.tier1_unit, 1200),
        thirdPlusUnit: readPricingInt(p.third_plus_unit ?? p.tier2_unit, 1000),
        bulkThreshold: readPricingInt(p.bulk_threshold, 0),
    };
}

function calcularDesglosePrecioVenta(cantidad) {
    const qty = Math.max(0, parseInt(cantidad, 10) || 0);
    const tier = pricingTierConfigVenta();
    const unit = parsePrecioRifaValue(estado.rifa?.precio || 0);

    if (!tier.enabled || qty <= 0) {
        return {
            total: unit > 0 ? Math.round(qty * unit) : 0,
            promoActive: false,
        };
    }

    if (tier.bulkThreshold > 0) {
        const priceUnit = qty >= tier.bulkThreshold ? tier.thirdPlusUnit : tier.firstUnit;
        return {
            total: Math.round(priceUnit * qty),
            promoActive: qty >= tier.bulkThreshold,
        };
    }

    if (qty === 1) {
        return { total: tier.firstUnit, promoActive: false };
    }

    if (qty === 2) {
        return { total: tier.secondUnit * 2, promoActive: true };
    }

    return { total: Math.round(tier.thirdPlusUnit * qty), promoActive: true };
}

function calcularTotalVenta(cantidad) {
    return calcularDesglosePrecioVenta(cantidad).total;
}

function aplicarPrecioServidor(raw) {
    const p = parsePrecioRifaValue(raw);
    if (p > 0 && estado.rifa) {
        estado.rifa.precio = p;
    }
}

function minCantidadVenta() {
    return Math.max(1, Number(estado.rifa?.minCantidad) || 1);
}

function mensajeCompraMinimaVenta() {
    const min = minCantidadVenta();
    return `La compra mínima es de ${min} sticker${min > 1 ? 's' : ''}.`;
}

function extractApiMessage(json, fallback = 'La operación no se completó.') {
    if (json && typeof json.message === 'string' && json.message.trim()) {
        return json.message.trim();
    }
    return fallback;
}

function venderEscapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function notifySuccess(mensaje) {
    const msg = String(mensaje || 'Operación exitosa').trim();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: msg,
            timer: 2200,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
        });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.success(msg, 5);
        return;
    }
    alert(msg);
}

function notifyError(mensaje, titulo = 'Error') {
    const msg = String(mensaje || 'Ocurrió un error inesperado').trim() || 'Ocurrió un error inesperado';
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: titulo, text: msg, confirmButtonText: 'Entendido' });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.error(msg, 10);
        return;
    }
    alert(`${titulo}: ${msg}`);
}

function notifyWarning(mensaje) {
    const msg = String(mensaje || '').trim();
    if (!msg) return;
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'warning', title: 'Aviso', text: msg, confirmButtonText: 'Entendido' });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.warning(msg, 8);
        return;
    }
    alert(msg);
}

function notifyErrorModal(titulo, mensaje) {
    const msg = String(mensaje || 'Ocurrió un error inesperado').trim() || 'Ocurrió un error inesperado';
    const title = String(titulo || 'Error').trim();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title,
            text: msg,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#212529',
        });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.alert(title, msg);
        return;
    }
    alert(`${title}\n\n${msg}`);
}

async function parseAjaxJson(res) {
    const text = await res.text();
    try {
        return JSON.parse(text.trim());
    } catch (e) {
        const preview = text.slice(0, 200).replace(/\s+/g, ' ').trim();
        const msg = res.ok
            ? 'Respuesta inválida del servidor. Recarga la página e intenta de nuevo.'
            : `Error del servidor (HTTP ${res.status})${preview ? `: ${preview}` : '.'}`;
        notifyError(msg, 'Error de comunicación');
        throw new Error(msg);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    inyectarEstilosMarca();
    initComponentes();
    cargarRifasActivas();
    asignarEventos();
});

function inyectarEstilosMarca() {

    const css = `
    <style>
        .btn-ticket-revelo {
            background-color:#ffffff !important;
            border:2px solid #1a1a1a !important;
            color:#1a1a1a !important;
            border-radius:10px;
            font-weight:700 !important;
            width:55px;
            height:55px;
        }

        .btn-resumen-tickets{
            background:#fff !important;
            border:1px solid #1a1a1a !important;
            border-radius:50px !important;
        }

        .pulse-gold{
            animation:pulse-animation .5s ease-in-out;
        }

        @keyframes pulse-animation{
            0%{transform:scale(1);}
            50%{transform:scale(1.15);box-shadow:0 0 10px #d4af37;}
            100%{transform:scale(1);}
        }

        .vender-confirm-summary { font-size: 0.88rem; }
        .vender-confirm-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.65rem;
            padding: 0.2rem 0;
        }
        .vender-confirm-row > span:last-child {
            text-align: right;
            max-width: 62%;
        }
        .vender-confirm-row--numbers {
            flex-direction: column;
            align-items: stretch;
            gap: 0.3rem;
        }
        .vender-confirm-row--numbers > span:first-child {
            text-align: left;
        }
        .vender-confirm-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: flex-start;
        }
        .vender-confirm-total { text-align: center; }
        .swal2-popup.vender-confirm-popup { border-radius: 14px; padding: 1rem 1rem 0.85rem; }
        .swal2-popup.vender-confirm-popup .swal2-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
            padding-bottom: 0;
        }
        .swal2-popup.vender-confirm-popup .swal2-html-container { margin: 0.5rem 0 0; }
        .swal2-popup.vender-confirm-popup .swal2-actions { margin-top: 0.75rem; }

        
        .vender-mobile-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 72px;
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            background: #fff;
            border-top: 1px solid #e9ecef;
            box-shadow: 0 -6px 24px rgba(0, 0, 0, 0.1);
            transform: translateY(110%);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.28s ease, opacity 0.28s ease;
        }
        .vender-mobile-bar.is-visible {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
        .vender-mobile-bar__info {
            flex: 1;
            min-width: 0;
        }
        .vender-mobile-bar__label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            line-height: 1.2;
            margin-bottom: 2px;
        }
        .vender-mobile-bar__price {
            font-size: 1.75rem;
            font-weight: 800;
            color: #198754;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .vender-mobile-bar__qty {
            font-size: 0.8rem;
            font-weight: 700;
            color: #495057;
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .vender-mobile-bar__btn {
            flex-shrink: 0;
            min-width: 42%;
            max-width: 52%;
            min-height: 48px;
            padding: 0.65rem 1.25rem;
            font-size: 1rem;
            letter-spacing: 0.02em;
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.35);
        }

        
        .vender-sheet-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1045;
            background: rgba(0, 0, 0, 0.45);
            opacity: 0;
            transition: opacity 0.28s ease;
            pointer-events: none;
        }
        .vender-sheet-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .vender-sheet {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1050;
            max-height: min(72vh, 520px);
            background: #fff;
            border-radius: 18px 18px 0 0;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.16);
            transform: translateY(105%);
            transition: transform 0.32s cubic-bezier(0.32, 0.72, 0, 1);
            display: flex;
            flex-direction: column;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .vender-sheet.is-open { transform: translateY(0); }
        .vender-sheet__handle {
            width: 40px;
            height: 4px;
            margin: 10px auto 0;
            border-radius: 999px;
            background: #dee2e6;
        }
        .vender-sheet__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px 8px;
        }
        .vender-sheet__close {
            font-size: 1.5rem;
            line-height: 1;
            text-decoration: none;
        }
        .vender-sheet__body {
            flex: 1;
            overflow-y: auto;
            padding: 0 16px 12px;
        }
        .vender-sheet__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f5;
        }
        .vender-sheet__numbers {
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f5;
        }
        .vender-sheet__auto-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: 10px;
            padding: 8px 12px;
        }
        .vender-sheet__total {
            text-align: center;
            padding: 18px 0 8px;
        }
        .vender-sheet__total .text-muted {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .vender-sheet__total-value {
            display: block;
            font-size: 2rem;
            font-weight: 800;
            color: #198754;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-top: 4px;
        }
        .vender-sheet__footer {
            padding: 12px 16px calc(14px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid #e9ecef;
            background: #fff;
        }
        @media (max-width: 991.98px) {
            .vender-numeros-card .cr-grilla-panel {
                border-radius: 0 0 16px 16px;
                box-shadow: none;
            }
            .vender-numeros-card .cr-grilla-numeros--grid {
                max-height: min(58vh, 460px);
            }
        }
        .vender-sheet__confirm {
            min-height: 52px;
            padding: 0.85rem 1.25rem;
            font-size: 1.05rem;
            letter-spacing: 0.03em;
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.3);
        }
        .vender-sheet__status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
            padding: 0.55rem 0.75rem;
            border-radius: 10px;
            background: #ecfdf3;
            color: #146c43;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .vender-sheet.is-processing .vender-sheet__close {
            opacity: 0.35;
            pointer-events: none;
        }

        @media (max-width: 991.98px) {
            body.vender-mobile-bar-visible .body-wrapper-inner .container-xxl {
                padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px)) !important;
            }
            body.vender-sheet-open { overflow: hidden; }
        }
    </style>`;

    document.head.insertAdjacentHTML('beforeend', css);
}

function asignarEventos() {

    $('#selectRifa').on('change', cambiarRifa);

    $('#cantidadNumeros').on('input', function () {

        estado.cantidadSeleccionada = parseInt(this.value) || 0;

        actualizarCarritoUI();
    });

    $('#departamento').on('change', function () {
        cargarCiudadesVenta(this.value);
    });

    $('#btnLimpiarCliente').on('click', resetClienteForm);

    $('#celularCliente').on('input paste', function () {

        let val = $(this).val().replace(/\D/g, '');

        if (val.startsWith('57') && val.length > 10) val = val.substring(2);

        $(this).val(val);

        if (val.length === 10) buscarClientePorCelular(val);
    });
}

async function buscarClientePorCelular(numero) {

    const fd = new FormData();

    fd.append('action', 'obtener');
    fd.append('search', numero);
    fd.append('status', 1);

    try {

        const res = await fetch(estado.config.rutas.clientes, {
            method: 'POST',
            body: fd
        });

        const json = await parseAjaxJson(res);

        if (json.success && json.data && json.data.length > 0) {

            const clienteEncontrado = json.data[0];

            if (clienteEncontrado.phone_customer === numero) {

                llenarFormulario(clienteEncontrado);
            }
        }

    } catch (e) {
        notifyWarning('No se pudo buscar el cliente automáticamente. Puedes llenar los datos manualmente.');
    }
}

function initComponentes() {

    $('#buscadorCliente').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar cliente...',
        allowClear: true,
        minimumInputLength: 3,
        ajax: {
            url: estado.config.rutas.clientes,
            type: 'POST',
            dataType: 'json',
            delay: 300,
            data: params => {

                let term = params.term ? params.term.trim() : "";

                if (/^[0-9\s+]+$/.test(term)) {

                    let digits = term.replace(/\D/g, '');

                    if (digits.startsWith('57') && digits.length > 10)
                        term = digits.substring(2);
                    else
                        term = digits;
                }

                return {
                    action: 'obtener',
                    search: term,
                    status: 1
                };
            },
            processResults: res => ({
                results: (res.success && res.data)
                    ? res.data.map(c => ({
                        id: c.id_customer,
                        text: `${c.name_customer} ${c.lastname_customer} (${c.phone_customer})`,
                        cliente: c
                    }))
                    : []
            })
        }
    })
        .on('select2:select', e => llenarFormulario(e.params.data.cliente))
        .on('select2:unselecting', resetClienteForm);

    $('.select2-ubicacion').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    if (typeof datosColombia !== 'undefined') {

        const $depto = $('#departamento');

        $depto.empty().append('<option value="">Seleccione...</option>');

        Object.keys(datosColombia)
            .sort()
            .forEach(d => $depto.append(new Option(d, d)));
    }
}

async function cargarRifasActivas() {

    const fd = new FormData();
    fd.append('action', 'obtener_activas');

    try {

        const res = await fetch(estado.config.rutas.rifas, {
            method: 'POST',
            body: fd
        });

        const json = await parseAjaxJson(res);

        if (!json.success) {
            notifyError(extractApiMessage(json, 'No se pudieron cargar las rifas activas.'), 'Rifas');
            return;
        }

        if (!json.data || json.data.length === 0) {
            notifyWarning('No hay rifas activas disponibles para vender.');
            return;
        }

        if (json.data.length > 0) {

            const select = document.getElementById('selectRifa');

            select.innerHTML = '';

            json.data.forEach((r, index) => {

                const opt = new Option(r.title_raffle, r.id_raffle);
                opt.dataset.precio = r.price_raffle;
                opt.dataset.tipo = r.type_raffle || 'automatic';
                opt.dataset.minCantidad = r.min_quantity_raffle ?? 1;

                select.add(opt);

                if (index === 0) {
                    select.value = String(r.id_raffle);
                    syncRifaDesdeSelect();
                }

            });

            aplicarModoVenta();
        }

    } catch (e) {
        notifyError(
            e instanceof Error ? e.message : 'No se pudieron cargar las rifas activas.',
            'Rifas'
        );
    }

}

function syncRifaDesdeSelect() {
    const select = document.getElementById('selectRifa');
    if (!select || !select.value) return;
    const opt = select.selectedOptions[0];
    estado.rifa = {
        id: select.value,
        precio: parsePrecioRifaValue(opt?.dataset.precio || 0),
        type: opt?.dataset.tipo || 'automatic',
        minCantidad: Math.max(1, parseInt(opt?.dataset.minCantidad, 10) || 1),
    };
}

function esRifaManual() {
    return String(estado.rifa?.type || 'automatic') === 'manual';
}

function cambiarRifa() {
    syncRifaDesdeSelect();
    aplicarModoVenta();
}

async function aplicarModoVenta() {
    const manual = esRifaManual();
    const bloquePaquetes = document.getElementById('bloquePaquetesVenta');
    const manualBox = document.getElementById('selectorManualVenta');

    if (bloquePaquetes) bloquePaquetes.classList.toggle('d-none', manual);
    if (manualBox) manualBox.classList.toggle('d-none', !manual);

    estado.numerosSeleccionados = [];
    estado.cantidadSeleccionada = 0;
    document.querySelectorAll('.paquete-radio').forEach(r => { r.checked = false; });
    $('#cantidadManual').hide().val('');
    const np = document.getElementById('numeroPremiado');
    if (np) np.value = '';

    if (manual) {
        await cargarInventarioVenta();
        initGrillaVenta();
        renderGridNumerosVenta();
    } else {
        estado.inventarioCompleto = [];
        estado.statsInventario = null;
        estado.disponiblesCount = 0;
        await refrescarPrecioRifaActiva();
    }

    actualizarCarritoUI();
}

async function refrescarPrecioRifaActiva() {
    if (!estado.rifa?.id) return;
    const fd = new FormData();
    fd.append('action', 'obtener_disponibles');
    fd.append('id_raffle', estado.rifa.id);
    try {
        const res = await fetch(estado.config.rutas.ventas, { method: 'POST', body: fd });
        const json = await parseAjaxJson(res);
        if (json.success) {
            aplicarPrecioServidor(json.price_raffle);
        }
    } catch (_) {
        
    }
}

async function cargarInventarioVenta() {
    if (!estado.rifa?.id) return;
    const fd = new FormData();
    fd.append('action', 'obtener_inventario');
    fd.append('id_raffle', estado.rifa.id);
    fd.append('grilla', '1');
    try {
        const res = await fetch(estado.config.rutas.numeros, { method: 'POST', body: fd });
        const json = await parseAjaxJson(res);
        if (json.success) {
            estado.inventarioCompleto = json.data || [];
            estado.statsInventario = json.stats || null;
            aplicarPrecioServidor(json.price_raffle);
            estado.disponiblesCount = json.stats?.disponibles
                ?? (typeof CrGrillaNumeros !== 'undefined'
                    ? CrGrillaNumeros.contarDisponibles(estado.inventarioCompleto)
                    : estado.inventarioCompleto.filter(t => Number(t.status_ticket) === 0).length);
            if (typeof CrGrillaNumeros !== 'undefined') {
                CrGrillaNumeros.resetUi(VENDER_GRILLA.gridId);
            }
            const search = document.getElementById(VENDER_GRILLA.searchId);
            if (search) search.value = '';
        } else {
            estado.inventarioCompleto = [];
            estado.statsInventario = null;
            estado.disponiblesCount = 0;
            notifyError(extractApiMessage(json, 'No se pudo cargar el inventario de nros.'), 'Inventario');
        }
    } catch (e) {
        estado.inventarioCompleto = [];
        estado.statsInventario = null;
        estado.disponiblesCount = 0;
        notifyError(
            e instanceof Error ? e.message : 'No se pudo cargar el inventario de nros.',
            'Inventario'
        );
    }
}

const VENDER_GRILLA = {
    gridId: 'gridNumerosVenta',
    pagerId: 'gridNumerosVentaPager',
    statsId: 'gridNumerosVentaStats',
    countId: 'manualVentaSeleccionCount',
    searchId: 'buscarNumeroVenta',
};

let grillaVentaInicializada = false;

function initGrillaVenta() {
    if (grillaVentaInicializada || typeof CrGrillaNumeros === 'undefined') return;
    CrGrillaNumeros.bindSearch(VENDER_GRILLA.searchId, VENDER_GRILLA.gridId, renderGridNumerosVenta);
    grillaVentaInicializada = true;
}

function renderGridNumerosVenta() {
    if (typeof CrGrillaNumeros === 'undefined') return;
    CrGrillaNumeros.render({
        ...VENDER_GRILLA,
        items: estado.inventarioCompleto,
        selectedIds: estado.numerosSeleccionados,
        serverStats: estado.statsInventario,
        onToggleName: 'toggleNumeroVenta',
        rerender: renderGridNumerosVenta,
        emptyMessage: 'No hay nros cargados para esta rifa.',
    });
}

window.toggleNumeroVenta = function (idTicket) {
    const id = parseInt(idTicket, 10);
    const ticket = estado.inventarioCompleto.find(t => parseInt(t.id_ticket, 10) === id);
    if (ticket && typeof CrGrillaNumeros !== 'undefined' && !CrGrillaNumeros.isDisponible(ticket)) {
        notifyWarning('Este nro no está disponible (vendido o reservado).');
        return;
    }
    const idx = estado.numerosSeleccionados.indexOf(id);
    if (idx >= 0) {
        estado.numerosSeleccionados.splice(idx, 1);
    } else {
        estado.numerosSeleccionados.push(id);
    }
    estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    renderGridNumerosVenta();
    actualizarCarritoUI();
};

window.filtrarNumerosVenta = function () {
    if (typeof CrGrillaNumeros !== 'undefined') {
        CrGrillaNumeros.resetPage(VENDER_GRILLA.gridId);
    }
    renderGridNumerosVenta();
};

function actualizarCarritoUI() {

    const cantidad = estado.cantidadSeleccionada;

    const total = calcularTotalVenta(cantidad);

    const fmt = n => new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(n);

    $('#lblTotalDesktop, #lblTotalMobile').text(fmt(total));
    $('#lblCantidadMobile, #lblCantidadDesktop').text(cantidad);

    const sheetCant = document.getElementById('sheetCantidad');
    const sheetTotal = document.getElementById('sheetTotal');
    if (sheetCant) {
        sheetCant.textContent = `${cantidad} nro${cantidad !== 1 ? 's' : ''}`;
    }
    if (sheetTotal) {
        sheetTotal.textContent = fmt(total);
    }
    renderSheetNumeros();

    actualizarBarraMobile(cantidad);

    let listaHtml;
    if (cantidad === 0) {
        listaHtml = '<li class="list-group-item text-center text-muted py-4 border-0 small">'
            + (esRifaManual() ? 'Selecciona los nros en la grilla' : 'Selecciona cantidad de nros')
            + '</li>';
    } else if (esRifaManual() && estado.numerosSeleccionados.length) {
        const nums = estado.inventarioCompleto
            .filter(t => estado.numerosSeleccionados.includes(parseInt(t.id_ticket, 10)))
            .map(t => t.number_ticket)
            .join(', ');
        listaHtml = `<li class="list-group-item px-0 border-light">
            <span class="badge bg-dark rounded-pill mb-2">${cantidad} nros</span>
            <div class="small text-break">${nums || '—'}</div>
            <div class="fw-bold small text-primary mt-2">${fmt(total)}</div>
        </li>`;
    } else {
        listaHtml = `<li class="list-group-item d-flex justify-content-between align-items-center px-0 border-light">
            <span class="badge bg-dark rounded-pill">${cantidad} nros</span>
            <span class="fw-bold small text-primary">${fmt(total)}</span>
        </li>`;
    }

    $('#listaCarritoDesktop').html(listaHtml);
}

function obtenerNumerosVentaSeleccionados() {
    if (!esRifaManual() || !estado.numerosSeleccionados.length) {
        return [];
    }
    return estado.inventarioCompleto
        .filter(t => estado.numerosSeleccionados.includes(parseInt(t.id_ticket, 10)))
        .map(t => String(t.number_ticket));
}

function renderSheetNumeros() {
    const el = document.getElementById('sheetNumeros');
    if (!el) return;

    const nums = obtenerNumerosVentaSeleccionados();
    if (esRifaManual() && nums.length) {
        el.innerHTML = `
            <span class="text-muted small d-block mb-2">Nros seleccionados</span>
            <div class="d-flex flex-wrap gap-1">${nums.map(n =>
                crNumeroChipHtml(n, 'selected')
            ).join('')}</div>`;
        return;
    }

    el.innerHTML = `
        <span class="text-muted small d-block mb-2">Nros</span>
        <span class="vender-sheet__auto-badge">
            <i class="ti ti-shuffle"></i> Asignación automática al confirmar
        </span>`;
}

function actualizarBarraMobile(cantidad) {
    const bar = document.getElementById('venderMobileBar');
    const btn = document.getElementById('btnAbrirCheckoutMobile');
    const visible = cantidad > 0;

    if (bar) {
        bar.classList.toggle('is-visible', visible);
        bar.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    if (btn) {
        const min = minCantidadVenta();
        btn.disabled = cantidad < min;
        btn.textContent = cantidad >= min
            ? 'Continuar'
            : (min > 1 ? `Mín. ${min} nums` : 'Elige cantidad');
    }

    document.body.classList.toggle('vender-mobile-bar-visible', visible && window.matchMedia('(max-width: 991.98px)').matches);
}

function abrirCheckoutVentaMobile() {
    if (!estado.rifa?.id) {
        notifyError('No hay sorteo seleccionado.', 'Venta');
        return;
    }

    if (esRifaManual()) {
        if (estado.numerosSeleccionados.length <= 0) {
            notifyError('Selecciona al menos un nro en la grilla.', 'Venta');
            return;
        }
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    }

    if (estado.cantidadSeleccionada < minCantidadVenta()) {
        notifyError(mensajeCompraMinimaVenta(), 'Venta');
        return;
    }

    actualizarCarritoUI();

    const sheet = document.getElementById('venderCheckoutSheet');
    const backdrop = document.getElementById('venderSheetBackdrop');
    if (!sheet || !backdrop) return;

    backdrop.hidden = false;
    requestAnimationFrame(() => {
        backdrop.classList.add('is-open');
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('vender-sheet-open');
    });
}

function setCheckoutSheetProcessing(processing) {
    const sheet = document.getElementById('venderCheckoutSheet');
    const status = document.getElementById('venderSheetStatus');
    const backdrop = document.getElementById('venderSheetBackdrop');

    if (sheet) {
        sheet.classList.toggle('is-processing', processing);
    }
    if (status) {
        status.hidden = !processing;
    }
    if (backdrop) {
        backdrop.style.pointerEvents = processing ? 'none' : '';
    }
}

function cerrarCheckoutVentaMobile() {
    const sheet = document.getElementById('venderCheckoutSheet');
    const backdrop = document.getElementById('venderSheetBackdrop');
    if (!sheet || !backdrop) return;
    if (sheet.classList.contains('is-processing')) return;

    setCheckoutSheetProcessing(false);
    sheet.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    sheet.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('vender-sheet-open');

    setTimeout(() => {
        if (!sheet.classList.contains('is-open')) {
            backdrop.hidden = true;
        }
    }, 320);
}

function obtenerDatosFormularioVenta() {
    return {
        cliente: {
            id: $('#idCliente').val(),
            nombre: $('#nombreCliente').val().trim(),
            apellido: $('#apellidoCliente').val().trim(),
            celular: $('#celularCliente').val().trim(),
            email: $('#emailCliente').val().trim(),
            depto: $('#departamento').val(),
            ciudad: $('#ciudad').val(),
        },
        metodo:
            $('input[name="metodoPago"]:checked').val()
            || $('input[name="metodoPagoMobile"]:checked').val(),
        numeroForzado: ($('#numeroPremiado').val() ?? '').trim(),
    };
}

function validarDatosVenta(form) {
    if (!estado.rifa?.id) {
        return 'No hay sorteo seleccionado.';
    }

    if (esRifaManual()) {
        if (estado.numerosSeleccionados.length <= 0) {
            return 'Selecciona al menos un nro en la grilla.';
        }
        estado.cantidadSeleccionada = estado.numerosSeleccionados.length;
    }

    if (estado.cantidadSeleccionada <= 0) {
        return 'Debes indicar la cantidad de nros.';
    }

    if (estado.cantidadSeleccionada < minCantidadVenta()) {
        return mensajeCompraMinimaVenta();
    }

    const { cliente, metodo, numeroForzado } = form;

    if (!cliente.nombre || !cliente.apellido || !cliente.celular || !cliente.email || !cliente.depto || !cliente.ciudad) {
        return 'Todos los campos obligatorios (*) deben estar llenos.';
    }

    if (!metodo) {
        return 'Debes seleccionar un método de pago.';
    }

    if (esRifaManual() && numeroForzado) {
        return 'En rifa manual elige los nros en la grilla.';
    }

    return null;
}

async function confirmarVentaDesdeSheet() {
    const btnD = document.getElementById('btnCompletarVenta');
    const btnM = document.getElementById('btnCompletarVentaMobile');
    const spinnerHtml = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    if ((btnD && btnD.disabled) || (btnM && btnM.disabled)) return;

    const form = obtenerDatosFormularioVenta();
    const error = validarDatosVenta(form);
    if (error) {
        notifyError(error, 'Venta');
        return;
    }

    const total = calcularTotalVenta(estado.cantidadSeleccionada);

    setCheckoutSheetProcessing(true);
    await ejecutarVentaRegistro({
        btnD,
        btnM,
        spinnerHtml,
        cliente: form.cliente,
        metodo: form.metodo,
        total,
        numeroForzado: form.numeroForzado,
    });
}

function venderFormatMoney(n) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(n);
}

function buildVentaConfirmHtml(cliente, total, metodo, cantidad) {
    const esc = venderEscapeHtml;
    const nombre = `${cliente.nombre} ${cliente.apellido}`.trim();
    const rifaTitle = document.getElementById('selectRifa')?.selectedOptions[0]?.text || '';

    let numerosHtml = '';
    const nums = obtenerNumerosVentaSeleccionados();
    if (esRifaManual() && nums.length) {
        const maxChips = 12;
        const chips = nums.slice(0, maxChips).map(n => crNumeroChipHtml(n, 'selected')).join('');
        const extra = nums.length > maxChips
            ? `<span class="text-muted small align-self-center">+${nums.length - maxChips} más</span>`
            : '';
        numerosHtml = `
        <div class="vender-confirm-row vender-confirm-row--numbers">
            <span class="text-muted">Nros</span>
            <div class="vender-confirm-chips">${chips}${extra}</div>
        </div>`;
    }

    return `
    <div class="vender-confirm-summary">
        <div class="vender-confirm-row">
            <span class="text-muted">Cliente</span>
            <span class="fw-semibold">${esc(nombre)}</span>
        </div>
        <div class="vender-confirm-row">
            <span class="text-muted">Cantidad</span>
            <span class="fw-semibold">${cantidad} nro${cantidad !== 1 ? 's' : ''}</span>
        </div>
        ${numerosHtml}
        <div class="vender-confirm-row">
            <span class="text-muted">Pago</span>
            <span class="fw-semibold">${esc(metodo)}</span>
        </div>
        ${rifaTitle ? `
        <div class="vender-confirm-row">
            <span class="text-muted">Rifa</span>
            <span class="fw-semibold">${esc(rifaTitle)}</span>
        </div>` : ''}
        <div class="vender-confirm-total mt-2 pt-2 border-top">
            <span class="text-muted small d-block mb-0">Total a registrar</span>
            <span class="fs-5 fw-bold text-success">${esc(venderFormatMoney(total))}</span>
        </div>
    </div>`;
}

function resetBotonesVenta(btnD, btnM) {
    setCheckoutSheetProcessing(false);
    if (btnD) {
        btnD.disabled = false;
        btnD.innerHTML = 'CONFIRMAR VENTA';
    }
    if (btnM) {
        btnM.disabled = false;
        btnM.innerHTML = 'CONFIRMAR VENTA';
    }
}

async function ejecutarVentaRegistro(opts) {
    const {
        btnD, btnM, spinnerHtml, cliente, metodo, total, numeroForzado,
    } = opts;

    const codigoVenta = 'AP' + Date.now() + Math.floor(Math.random() * 100);
    const action = (!esRifaManual() && numeroForzado) ? 'crear_venta_mixta' : 'crear_venta';

    if (btnD) {
        btnD.disabled = true;
        btnD.innerHTML = spinnerHtml;
    }
    if (btnM) {
        btnM.disabled = true;
        btnM.innerHTML = spinnerHtml;
    }

    const fd = new FormData();
    fd.append('action', action);
    fd.append('premiado_ticket', esRifaManual() ? '' : numeroForzado);
    fd.append('code_sale', codigoVenta);
    fd.append('quantity_sale', estado.cantidadSeleccionada);
    fd.append('id_customer', cliente.id);
    fd.append('id_raffle', estado.rifa.id);
    fd.append('total_sale', total);
    fd.append('payment_method_sale', metodo);
    fd.append('name_customer', cliente.nombre);
    fd.append('lastname_customer', cliente.apellido);
    fd.append('phone_customer', cliente.celular);
    fd.append('email_customer', cliente.email);
    fd.append('department_customer', cliente.depto);
    fd.append('city_customer', cliente.ciudad);

    if (esRifaManual()) {
        estado.numerosSeleccionados.forEach(id => fd.append('ticket_ids[]', id));
    }

    try {
        const res = await fetch(estado.config.rutas.ventas, { method: 'POST', body: fd });
        const json = await parseAjaxJson(res);

        if (json.success) {
            generarReciboFinal(json.id_sale);
            return;
        }

        notifyErrorModal('Venta no registrada', extractApiMessage(json, 'No se pudo registrar la venta.'));
        resetBotonesVenta(btnD, btnM);
    } catch (e) {
        const msg = e instanceof Error && e.message
            ? e.message
            : 'Error de conexión con el servidor. Intenta de nuevo.';
        notifyErrorModal('Error al procesar la venta', msg);
        resetBotonesVenta(btnD, btnM);
    }
}

async function confirmarVenta(opts) {
    const { cliente, metodo, total, cantidad } = opts;
    const html = buildVentaConfirmHtml(cliente, total, metodo, cantidad);

    if (typeof Swal === 'undefined') {
        const ok = window.confirm(
            `¿Registrar venta de ${cantidad} nros por ${venderFormatMoney(total)}?`
        );
        if (ok) await ejecutarVentaRegistro(opts);
        return;
    }

    const result = await Swal.fire({
        title: 'Confirmar venta',
        html,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#adb5bd',
        reverseButtons: true,
        focusCancel: true,
        buttonsStyling: true,
        customClass: { popup: 'vender-confirm-popup' },
    });

    if (result.isConfirmed) {
        await ejecutarVentaRegistro(opts);
    }
}

async function procesarVenta() {

    const btnD = document.getElementById('btnCompletarVenta');
    const btnM = document.getElementById('btnCompletarVentaMobile');
    const spinnerHtml = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    if ((btnD && btnD.disabled) || (btnM && btnM.disabled)) return;

    const form = obtenerDatosFormularioVenta();
    const error = validarDatosVenta(form);
    if (error) {
        notifyError(error, 'Venta');
        return;
    }

    const total = calcularTotalVenta(estado.cantidadSeleccionada);

    await confirmarVenta({
        btnD,
        btnM,
        spinnerHtml,
        cliente: form.cliente,
        metodo: form.metodo,
        total,
        numeroForzado: form.numeroForzado,
        cantidad: estado.cantidadSeleccionada,
    });
}

async function generarReciboFinal(idVenta) {

    const fd = new FormData();

    fd.append('action', 'detalle_venta');
    fd.append('id_sale', idVenta);

    try {

        const res = await fetch(estado.config.rutas.ventas, {
            method: 'POST',
            body: fd
        });

        const json = await parseAjaxJson(res);

        if (json.success) {
            setCheckoutSheetProcessing(false);
            cerrarCheckoutVentaMobile();
            const bar = document.getElementById('venderMobileBar');
            if (bar) bar.classList.remove('is-visible');

            const container = document.querySelector('.body-wrapper-inner');

            container.innerHTML = `
                <div class="container py-5 animated fadeIn">
                    ${json.html_recibo}
                    <div class="mt-4 text-center no-print">
                        <button class="btn btn-dark fw-bold px-5 rounded-pill shadow" onclick="location.reload()">NUEVA VENTA</button>
                    </div>
                </div>`;

            window.scrollTo(0, 0);
        } else {
            setCheckoutSheetProcessing(false);
            notifyErrorModal(
                'Recibo no disponible',
                extractApiMessage(json, 'La venta se registró pero no se pudo cargar el recibo.')
            );
            setTimeout(() => location.reload(), 4000);
        }

    } catch (e) {
        setCheckoutSheetProcessing(false);
        notifyErrorModal(
            'Error al cargar el recibo',
            e instanceof Error ? e.message : 'No se pudo mostrar el comprobante de venta.'
        );

        setTimeout(() => location.reload(), 4000);
    }
}

window.procesarVentaMobile = () => abrirCheckoutVentaMobile();
window.abrirCheckoutVentaMobile = abrirCheckoutVentaMobile;
window.cerrarCheckoutVentaMobile = cerrarCheckoutVentaMobile;
window.confirmarVentaDesdeSheet = confirmarVentaDesdeSheet;

function llenarFormulario(c) {

    $('#idCliente').val(c.id_customer);
    $('#nombreCliente').val(c.name_customer);
    $('#apellidoCliente').val(c.lastname_customer);
    $('#celularCliente').val(c.phone_customer);
    $('#emailCliente').val(c.email_customer);

    if (c.department_customer) {

        $('#departamento').val(c.department_customer).trigger('change');

        setTimeout(() =>
            $('#ciudad').val(c.city_customer).trigger('change'), 150);
    }

    $('#btnLimpiarCliente').removeClass('d-none');

    toggleInputs(true);
}

function resetClienteForm() {

    $('#idCliente').val('');

    document.getElementById('formClienteVenta').reset();

    $('#departamento, #ciudad, #buscadorCliente')
        .val(null)
        .trigger('change');

    $('#btnLimpiarCliente').addClass('d-none');

    toggleInputs(false);
}

function toggleInputs(bloquear) {

    $('#formClienteVenta input:not([type="hidden"])')
        .prop('readonly', bloquear);
}

function cargarCiudadesVenta(depto) {

    const $ciudad = $('#ciudad');

    $ciudad.empty().append('<option value="">Seleccione...</option>');

    if (depto && datosColombia[depto]) {

        $ciudad.prop('disabled', false);

        datosColombia[depto].forEach(c =>
            $ciudad.append(new Option(c.display, c.value)));

    } else {

        $ciudad.prop('disabled', true);
    }

    $ciudad.trigger('change');
}

$('.paquete-radio').on('change', function () {

    if (this.value === 'custom') {
        $('#cantidadManual').show().focus();
        estado.cantidadSeleccionada = 0;
        actualizarCarritoUI();
        return;
    }

    $('#cantidadManual').hide().val('');
    estado.cantidadSeleccionada = parseInt(this.value, 10) || 0;
    actualizarCarritoUI();
});

$('#cantidadManual').on('input', function(){

    const val = parseInt(this.value) || 0;

    estado.cantidadSeleccionada = val;

    actualizarCarritoUI();

});
