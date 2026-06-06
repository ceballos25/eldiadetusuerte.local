const TICKETS_AJAX_URL = 'front/ajax/ventas.ajax.php';
const PHONE_PATTERN = /^\d{10}$/;
const PHONE_MAX_LEN = 10;

const inputBuscarTickets = document.getElementById('inputBuscarTickets');
const resultadoBusqueda = document.getElementById('resultadoBusqueda');
const modalBuscarTickets = document.getElementById('modalBuscarTickets');
const btnBuscarTickets = document.getElementById('btnBuscarTickets');
const contadorBuscarTickets = document.getElementById('buscarTicketsContador');

function normalizarCelularBusqueda(raw) {
    let val = String(raw ?? '').replace(/\D/g, '');

    if (val.startsWith('57') && val.length > PHONE_MAX_LEN) {
        val = val.substring(2);
    }

    return val.slice(0, PHONE_MAX_LEN);
}

function actualizarEstadoInputBusqueda() {
    if (!inputBuscarTickets) return;

    const valor = inputBuscarTickets.value;
    const listo = PHONE_PATTERN.test(valor);

    if (contadorBuscarTickets) {
        contadorBuscarTickets.textContent = `${valor.length}/${PHONE_MAX_LEN}`;
        contadorBuscarTickets.classList.toggle('text-success', listo);
        contadorBuscarTickets.classList.toggle('text-muted', !listo);
        contadorBuscarTickets.classList.toggle('fw-semibold', listo);
    }

    if (btnBuscarTickets) {
        btnBuscarTickets.disabled = !listo;
    }

    inputBuscarTickets.classList.toggle('is-valid', listo);
    inputBuscarTickets.classList.toggle('is-invalid', valor.length > 0 && !listo);
}

function aplicarCelularAlInput(el, raw) {
    const val = normalizarCelularBusqueda(raw);
    el.value = val;
    actualizarEstadoInputBusqueda();
}

function renderAlert(type, message, icon = '') {
    const iconMarkup = icon ? `<i class="${icon} me-1"></i>` : '';
    resultadoBusqueda.innerHTML = `<div class="alert alert-${type} mb-0">${iconMarkup}${message}</div>`;
}

function limpiarResultadoBusqueda() {
    if (resultadoBusqueda) resultadoBusqueda.innerHTML = '';
}

function buscarTickets() {
    if (!inputBuscarTickets || !resultadoBusqueda) {
        return;
    }

    const valor = normalizarCelularBusqueda(inputBuscarTickets.value);

    if (!valor) {
        renderAlert('danger', 'Ingresa el celular con el que hiciste la compra.');
        inputBuscarTickets.focus();
        return;
    }

    if (!PHONE_PATTERN.test(valor)) {
        renderAlert('danger', 'El celular debe tener exactamente 10 dígitos (ejemplo: 3001234567).');
        inputBuscarTickets.focus();
        return;
    }

    if (btnBuscarTickets) btnBuscarTickets.disabled = true;
    renderAlert('info', `Buscando stickers comprados con el celular <strong>${valor}</strong>…`);

    $.ajax({
        url: TICKETS_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'obtener_por_celular',
            phone_customer: valor
        },
        success: function(resp) {
            if (!resp.success) {
                renderAlert(
                    'warning',
                    resp.message || 'No encontramos compras con ese celular. Verifica que sea el mismo celular con el que pagaste.',
                    'ti ti-alert-triangle'
                );
                return;
            }

            resultadoBusqueda.innerHTML = resp.html;
        },
        error: function() {
            renderAlert('danger', 'Error consultando los tickets. Intenta de nuevo.', 'ti ti-alert-circle');
        },
        complete: function() {
            actualizarEstadoInputBusqueda();
        }
    });
}

if (modalBuscarTickets && inputBuscarTickets) {
    modalBuscarTickets.addEventListener('shown.bs.modal', () => {
        limpiarResultadoBusqueda();
        inputBuscarTickets.value = '';
        actualizarEstadoInputBusqueda();
        inputBuscarTickets.focus();
    });

    modalBuscarTickets.addEventListener('hidden.bs.modal', () => {
        limpiarResultadoBusqueda();
        inputBuscarTickets.value = '';
        inputBuscarTickets.classList.remove('is-valid', 'is-invalid');
        actualizarEstadoInputBusqueda();
    });

    inputBuscarTickets.addEventListener('input', function () {
        aplicarCelularAlInput(this, this.value);
    });

    inputBuscarTickets.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        aplicarCelularAlInput(this, pasted);
    });

    inputBuscarTickets.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (PHONE_PATTERN.test(this.value)) {
                buscarTickets();
            }
            return;
        }

        const permitidas = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (permitidas.includes(e.key)) return;

        if (!/^\d$/.test(e.key)) {
            e.preventDefault();
        }
    });

    actualizarEstadoInputBusqueda();
}
