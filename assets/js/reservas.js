let reservas = [];
let paginaReservas = 1;
let totalReservas = 0;
const reservasPorPagina = 15;

const RESERVAS_ENDPOINT = '/front/ajax/reservas.ajax.php';

function reservaNombreCompleto(r) {
    return `${r.name_customer || ''} ${r.lastname_customer || ''}`.trim() || '—';
}

function reservaOrigenBadge(kind) {
    if (kind === 'pse') {
        return '<span class="badge bg-primary-subtle text-primary border">PSE</span>';
    }
    return '<span class="badge bg-info-subtle text-info border">Transferencia</span>';
}

function reservaTipoBadge(type) {
    return type === 'manual'
        ? '<span class="badge bg-warning-subtle text-dark border">Manual</span>'
        : '<span class="badge bg-secondary-subtle text-secondary border">Automática</span>';
}

function renderNumerosReserva(r) {
    const nums = Array.isArray(r.reserved_numbers) ? r.reserved_numbers : [];
    if (nums.length === 0) {
        return `<span class="text-muted small">${adminEscapeHtml(r.reserved_numbers_label || '—')}</span>`;
    }
    return nums.map(n => `<span class="cr-numero-chip cr-numero-chip--selected me-1 mb-1">${adminEscapeHtml(n)}</span>`).join('');
}

function renderReservaCard(r) {
    const total = Number(r.amount || 0).toLocaleString('es-CO');
    const qty = Number(r.quantity || 0);
    const expira = r.expires_at
        ? `<small class="text-muted d-block">Expira: ${adminEscapeHtml(String(r.expires_at))}</small>`
        : r.source_kind === 'transferencia'
            ? '<small class="text-muted d-block">Reservado hasta aprobación o rechazo</small>'
            : '';

    return `
    <article class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h5 class="fw-bold mb-1">${adminEscapeHtml(reservaNombreCompleto(r))}</h5>
                    <a href="${r.phone_customer ? 'https://api.whatsapp.com/send?phone=57' + String(r.phone_customer).replace(/\\D/g, '') : '#'}"
                       class="small text-muted" ${r.phone_customer ? 'target="_blank" rel="noopener"' : ''}>
                        <i class="ti ti-brand-whatsapp"></i> ${adminEscapeHtml(r.phone_customer || '—')}
                    </a>
                </div>
                <div class="text-end">
                    ${reservaOrigenBadge(r.source_kind)}
                    ${reservaTipoBadge(r.type_raffle)}
                </div>
            </div>
            <div class="row g-2 small">
                <div class="col-md-3"><span class="text-muted">Código</span><br><span class="font-monospace fw-semibold">${adminEscapeHtml(r.code)}</span></div>
                <div class="col-md-3"><span class="text-muted">Rifa</span><br>${adminEscapeHtml(r.title_raffle || '')}</div>
                <div class="col-md-2"><span class="text-muted">Cantidad</span><br><strong>${qty}</strong></div>
                <div class="col-md-2"><span class="text-muted">Total</span><br><strong>$${total}</strong></div>
                <div class="col-md-2"><span class="text-muted">Fecha</span><br>${adminEscapeHtml(String(r.date_created || ''))}</div>
            </div>
            <div class="mt-3 pt-2 border-top">
                <span class="text-muted small d-block mb-1">Nros reservados</span>
                <div class="d-flex flex-wrap gap-1">${renderNumerosReserva(r)}</div>
                ${expira}
            </div>
            ${r.source_kind === 'transferencia' ? `
            <div class="mt-2">
                <a href="transferencias.php" class="btn btn-sm btn-outline-primary">Revisar en transferencias</a>
            </div>` : ''}
        </div>
    </article>`;
}

function actualizarBadgeReservas() {
    const badge = document.getElementById('reservasPendingBadge');
    if (!badge) return;
    badge.textContent = `${totalReservas} pendiente${totalReservas !== 1 ? 's' : ''}`;
    badge.classList.toggle('d-none', totalReservas === 0);
}

function renderReservasTabla() {
    const cola = document.getElementById('reservasCola');
    if (!cola) return;

    actualizarBadgeReservas();

    if (!reservas.length) {
        cola.innerHTML = `
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                No hay reservas pendientes
            </div>
        </div>`;
        return;
    }

    cola.innerHTML = reservas.map(renderReservaCard).join('');
}

function reservasFiltrosParams() {
    const fd = new FormData();
    fd.append('action', 'obtener');
    fd.append('page', String(paginaReservas));
    fd.append('limit', String(reservasPorPagina));
    fd.append('search', document.getElementById('searchReservas')?.value?.trim() || '');
    fd.append('source', document.getElementById('filterOrigenReserva')?.value || '');
    return fd;
}

function debounce(fn, wait) {
    return typeof adminDebounce === 'function'
        ? adminDebounce(fn, wait)
        : (function () {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        })();
}

async function cargarReservas() {
    const cola = document.getElementById('reservasCola');
    if (cola) cola.innerHTML = '<div class="text-center py-5 text-muted">Cargando…</div>';

    try {
        const data = await adminFetchJson(RESERVAS_ENDPOINT, { body: reservasFiltrosParams() });
        if (!data.success) {
            reservas = [];
            totalReservas = 0;
            renderReservasTabla();
            adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las reservas.'));
            return;
        }
        reservas = Array.isArray(data.data) ? data.data : [];
        totalReservas = Number(data.total) || 0;
        renderReservasTabla();
    } catch (e) {
        reservas = [];
        totalReservas = 0;
        renderReservasTabla();
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar las reservas.');
    }
}

function limpiarFiltrosReservas() {
    const s = document.getElementById('searchReservas');
    const o = document.getElementById('filterOrigenReserva');
    if (s) s.value = '';
    if (o) o.value = '';
    paginaReservas = 1;
    cargarReservas();
}

document.addEventListener('DOMContentLoaded', () => {
    cargarReservas();
    document.getElementById('searchReservas')?.addEventListener('input', debounce(() => {
        paginaReservas = 1;
        cargarReservas();
    }, 400));
    document.getElementById('filterOrigenReserva')?.addEventListener('change', () => {
        paginaReservas = 1;
        cargarReservas();
    });
});
