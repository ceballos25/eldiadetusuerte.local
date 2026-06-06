let transferencias = [];
let paginaActual = 1;
let totalTransferencias = 0;
let pendingTotal = 0;
const registrosPorPagina = 10;

const TRANSFERENCIAS_ENDPOINT = '/front/ajax/transferencias.ajax.php';
const VENTAS_ENDPOINT = '/front/ajax/ventas.ajax.php';
const TZ_COLOMBIA = 'America/Bogota';
const LOCALE_CO = 'es-CO';

function parseTransferDateTime(raw) {
    const s = String(raw ?? '').trim();
    if (!s) return null;
    const d = new Date(s.replace(' ', 'T') + '-05:00');
    return Number.isFinite(d.getTime()) ? d : null;
}

function formatTransferFechaHora(raw) {
    const d = parseTransferDateTime(raw);
    if (!d) return { fecha: '—', hora: '' };
    const opts = { timeZone: TZ_COLOMBIA };
    return {
        fecha: d.toLocaleDateString(LOCALE_CO, opts),
        hora: d.toLocaleTimeString(LOCALE_CO, {
            ...opts,
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
    };
}

function transferNombreCompleto(t) {
    return `${t.name_customer || ''} ${t.lastname_customer || ''}`.trim() || '—';
}

function transferStatusBadge(status) {
    const map = {
        1: '<span class="badge transfer-badge-pendiente px-3 py-2 rounded-pill">Pendiente</span>',
        2: '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Aprobado</span>',
        3: '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Rechazado</span>',
        4: '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill">Error</span>',
    };
    return map[Number(status)] || '<span class="badge bg-light text-muted border px-3 py-2 rounded-pill">—</span>';
}

function transferSourceBadge(source) {
    const label = String(source ?? '').trim().toUpperCase() || 'N/A';
    return `<span class="badge border text-primary bg-light transfer-source-badge">
        <i class="ti ti-building-bank"></i> ${adminEscapeHtml(label)}
    </span>`;
}

function renderTransferReservedNumbers(t) {
    const nums = Array.isArray(t.reserved_numbers) ? t.reserved_numbers : [];
    if (nums.length > 0) {
        return nums.map(n => `<span class="cr-numero-chip cr-numero-chip--selected me-1 mb-1">${adminEscapeHtml(n)}</span>`).join('');
    }
    return adminEscapeHtml(t.reserved_numbers_label || 'Automático al aprobar');
}

function transferDatos(t) {
    const { fecha, hora } = formatTransferFechaHora(t.date_created_transfer);
    return {
        fecha,
        hora,
        inicial: adminInitial(t.name_customer),
        nombre: transferNombreCompleto(t),
        qtyLabel: `${t.quantity_transfer} núm${Number(t.quantity_transfer) > 1 ? 's' : ''}`,
        totalLabel: `$${Number(t.amount_transfer).toLocaleString('es-CO')}`,
        estadoHtml: transferStatusBadge(t.status_transfer),
    };
}

function actualizarBadgePendientes() {
    const badge = document.getElementById('transferPendingBadge');
    if (!badge) return;
    badge.textContent = `${pendingTotal} pendiente${pendingTotal !== 1 ? 's' : ''}`;
    badge.classList.toggle('d-none', pendingTotal === 0);
}

function actualizarPaginacionTransfer() {
    if (typeof PaginationHelper === 'undefined') return;
    PaginationHelper.render({
        totalItems: totalTransferencias,
        currentPage: paginaActual,
        limit: registrosPorPagina,
        containerId: 'contenedorPaginacionTransfer',
        infoId: 'infoPaginacionTransfer',
        callbackName: 'cambiarPaginaTransfer',
    });
    const info = document.getElementById('infoPaginacionTransfer');
    if (info && totalTransferencias > 0) {
        const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
        const desde = (paginaActual - 1) * registrosPorPagina + 1;
        const hasta = Math.min(paginaActual * registrosPorPagina, totalTransferencias);
        info.textContent = `Mostrando ${desde}–${hasta} de ${totalTransferencias} · ${registrosPorPagina} por página (pág. ${paginaActual} de ${totalPaginas})`;
    }
}

function cambiarPaginaTransfer(pagina) {
    const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaActual = pagina;
    cargarTransferencias();
}

function renderTabla() {
    actualizarBadgePendientes();

    const cola = document.getElementById('transferCola');
    if (!cola) return;

    if (!transferencias.length) {
        cola.innerHTML = `
        <div class="transfer-empty card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ti ti-inbox text-muted fs-1 mb-2 d-block"></i>
                <p class="fw-semibold mb-1">No hay transferencias</p>
                <p class="text-muted small mb-0">Prueba otro filtro o espera nuevas solicitudes.</p>
            </div>
        </div>`;
        actualizarPaginacionTransfer();
        return;
    }

    cola.innerHTML = transferencias.map(t => renderTransferReviewCard(t)).join('');
    actualizarPaginacionTransfer();
}

function transferFiltrosParams() {
    const fd = new URLSearchParams();
    fd.append('action', 'obtener');
    fd.append('page', String(paginaActual));
    fd.append('limit', String(registrosPorPagina));
    fd.append('search', document.getElementById('searchTransfer')?.value?.trim() || '');
    fd.append('status', document.getElementById('filterEstado')?.value ?? '1');
    fd.append('fecha_inicio', document.getElementById('fecha_inicio')?.value || '');
    fd.append('fecha_fin', document.getElementById('fecha_fin')?.value || '');
    return fd;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarTransferencias();

    document.getElementById('searchTransfer')?.addEventListener('input', debounce(() => {
        paginaActual = 1;
        cargarTransferencias();
    }, 400));

    document.getElementById('filterEstado')?.addEventListener('change', () => {
        paginaActual = 1;
        cargarTransferencias();
    });

    ['fecha_inicio', 'fecha_fin'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', function () {
            const ini = document.getElementById('fecha_inicio')?.value || '';
            const fin = document.getElementById('fecha_fin')?.value || '';
            if (ini && fin) {
                paginaActual = 1;
                cargarTransferencias();
            }
        });
    });
});

function cargarTransferencias() {
    const cola = document.getElementById('transferCola');
    if (cola) {
        cola.innerHTML = '<div class="text-center py-5 text-muted">Cargando…</div>';
    }

    fetch(TRANSFERENCIAS_ENDPOINT, { method: 'POST', body: transferFiltrosParams() })
        .then(parseJsonTransferenciasAjax)
        .then(data => {
            if (!data.success) {
                transferencias = [];
                totalTransferencias = 0;
                pendingTotal = 0;
                renderTabla();
                adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las transferencias.'));
                return;
            }

            transferencias = Array.isArray(data.data) ? data.data : (data.data ? [data.data] : []);
            totalTransferencias = Number(data.total) || 0;
            pendingTotal = Number(data.pending_total) || 0;

            const totalPaginas = Math.ceil(totalTransferencias / registrosPorPagina) || 1;
            if (paginaActual > totalPaginas && totalPaginas > 0) {
                paginaActual = totalPaginas;
                cargarTransferencias();
                return;
            }

            renderTabla();
        })
        .catch(err => {
            transferencias = [];
            totalTransferencias = 0;
            pendingTotal = 0;
            renderTabla();
            adminNotifyError(err instanceof Error ? err.message : 'No se pudieron cargar las transferencias.');
        });
}

function limpiarFiltrosTransfer() {
    document.getElementById('searchTransfer').value = '';
    document.getElementById('fecha_inicio').value = '';
    document.getElementById('fecha_fin').value = '';
    document.getElementById('filterEstado').value = '1';
    paginaActual = 1;
    cargarTransferencias();
}

function renderTransferProofBlock(t, large) {
    const id = Number(t.id_transfer);
    if (!t.url_transfer) {
        return `<div class="transfer-proof transfer-proof--empty ${large ? 'transfer-proof--lg' : ''}">
            <i class="ti ti-photo-off fs-1 text-muted"></i>
            <span class="small text-muted">Sin comprobante</span>
        </div>`;
    }
    const cls = large ? 'transfer-proof transfer-proof--lg' : 'transfer-proof';
    return `
    <button type="button" class="${cls} transfer-proof-btn" onclick="verComprobantePorId(${id}, true)" title="Ampliar comprobante">
        <img src="${adminEscapeHtml(t.url_transfer)}" alt="Comprobante" loading="lazy" decoding="async">
        <span class="transfer-proof-zoom"><i class="ti ti-zoom-in"></i></span>
    </button>`;
}

function renderTransferReviewCard(t) {
    const d = transferDatos(t);
    const id = Number(t.id_transfer);
    const isPending = Number(t.status_transfer) === 1;

    return `
    <article class="transfer-review-card ${isPending ? 'transfer-review-card--pending' : 'transfer-review-card--done'}">
        <div class="transfer-review-card__body">
            <div class="transfer-review-card__proof-col">
                ${renderTransferProofBlock(t, true)}
                <span class="transfer-review-card__proof-hint small text-muted">Toca para ampliar</span>
            </div>
            <div class="transfer-review-card__info">
                <div class="transfer-review-card__head">
                    <div class="transfer-review-card__client">
                        <div class="transfer-review-avatar">${adminEscapeHtml(d.inicial)}</div>
                        <div>
                            <h3 class="transfer-review-name">${adminEscapeHtml(d.nombre)}</h3>
                            <a href="${String(t.phone_customer || '').replace(/\D/g, '') ? 'https://api.whatsapp.com/send?phone=57' + String(t.phone_customer).replace(/\D/g, '') : '#'}"
                               ${String(t.phone_customer || '').replace(/\D/g, '') ? 'target="_blank" rel="noopener"' : ''}
                               class="transfer-review-phone ${String(t.phone_customer || '').replace(/\D/g, '') ? '' : 'text-muted pe-none'}">
                                <i class="ti ti-brand-whatsapp"></i> ${adminEscapeHtml(t.phone_customer || '—')}
                            </a>
                        </div>
                    </div>
                    ${d.estadoHtml}
                </div>
                <div class="transfer-review-meta">
                    <div class="transfer-review-meta__item transfer-review-meta__item--total">
                        <span class="transfer-review-meta__label">Total</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.totalLabel)}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Cantidad</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.qtyLabel)}</span>
                    </div>
                    <div class="transfer-review-meta__item transfer-review-meta__item--numbers">
                        <span class="transfer-review-meta__label">Nros reservados</span>
                        <span class="transfer-review-meta__value">${renderTransferReservedNumbers(t)}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Código</span>
                        <span class="transfer-review-meta__value font-monospace">${adminEscapeHtml(t.code_transfer)}</span>
                    </div>
                    <div class="transfer-review-meta__item">
                        <span class="transfer-review-meta__label">Fecha</span>
                        <span class="transfer-review-meta__value">${adminEscapeHtml(d.fecha)} · ${adminEscapeHtml(d.hora)}</span>
                    </div>
                </div>
                <div class="transfer-review-bank">${transferSourceBadge(t.source_transfer)}</div>
            </div>
        </div>
        ${isPending ? `
        <div class="transfer-review-card__actions">
            <button type="button" class="btn btn-outline-danger btn-lg flex-fill" onclick="rechazar(${id})">
                <i class="ti ti-x me-1"></i> Rechazar
            </button>
            <button type="button" class="btn btn-success btn-lg flex-fill" onclick="aprobar(${id})">
                <i class="ti ti-check me-1"></i> Aprobar compra
            </button>
        </div>` : ''}
    </article>`;
}

function verComprobantePorId(id, conAcciones) {
    const t = transferencias.find(x => Number(x.id_transfer) === Number(id));
    if (!t?.url_transfer) {
        Swal.fire('Error', 'No hay comprobante disponible', 'error');
        return;
    }
    verComprobante(t.url_transfer, conAcciones ? t : null);
}

function verComprobante(url, transfer) {
    if (!url) return;

    const cuerpo = document.getElementById('cuerpoComprobante');
    const footer = document.getElementById('modalComprobanteActions');
    if (cuerpo) {
        cuerpo.innerHTML = `<img src="${adminEscapeHtml(url)}" class="img-fluid rounded transfer-proof-modal-img" alt="Comprobante">`;
    }

    if (footer && transfer && Number(transfer.status_transfer) === 1) {
        footer.classList.remove('d-none');
        footer.innerHTML = `
            <button type="button" class="btn btn-outline-danger" onclick="rechazar(${Number(transfer.id_transfer)}); bootstrap.Modal.getInstance(document.getElementById('modalComprobante'))?.hide();">
                <i class="ti ti-x me-1"></i> Rechazar
            </button>
            <button type="button" class="btn btn-success" onclick="aprobar(${Number(transfer.id_transfer)}); bootstrap.Modal.getInstance(document.getElementById('modalComprobante'))?.hide();">
                <i class="ti ti-check me-1"></i> Aprobar compra
            </button>`;
    } else if (footer) {
        footer.classList.add('d-none');
        footer.innerHTML = '';
    }

    new bootstrap.Modal(document.getElementById('modalComprobante')).show();
}

function paramsAprobarTransferencia(t) {
    const p = new URLSearchParams();
    p.set('action', 'aprobar');
    p.set('id_transfer', String(t.id_transfer ?? ''));
    p.set('status_transfer', String(t.status_transfer ?? ''));
    p.set('quantity_transfer', String(t.quantity_transfer ?? ''));
    p.set('id_raffle_transfer', String(t.id_raffle_transfer ?? ''));
    p.set('amount_transfer', String(t.amount_transfer ?? ''));
    p.set('id_customer_transfer', String(t.id_customer_transfer ?? ''));
    p.set('code_transfer', String(t.code_transfer ?? ''));
    if (t.source_transfer != null && String(t.source_transfer).trim() !== '') {
        p.set('source_transfer', String(t.source_transfer));
    }
    p.set('csrf_token', window.APP_CSRF_TOKEN || '');
    return p;
}

function parseJsonTransferenciasAjax(res) {
    if (!res || typeof res.text !== 'function') {
        return Promise.reject(new Error('Respuesta inválida del servidor. Recarga la página e intenta de nuevo.'));
    }
    return res.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Respuesta inválida del servidor. Recarga la página e intenta de nuevo.');
        }
    });
}

function aprobar(id) {
    const t = transferencias.find(x => x.id_transfer == id);
    if (!t) return;

    Swal.fire({
        title: '¿Aprobar esta compra?',
        html: `<p class="mb-2">${adminEscapeHtml(transferNombreCompleto(t))}</p>
               <p class="fw-bold text-success mb-0">${adminEscapeHtml('$' + Number(t.amount_transfer).toLocaleString('es-CO'))}</p>
               <small class="text-muted font-monospace">${adminEscapeHtml(t.code_transfer)}</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        reverseButtons: true,
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'Procesando…',
            text: 'Creando venta y asignando nros',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        fetch(TRANSFERENCIAS_ENDPOINT, { method: 'POST', body: paramsAprobarTransferencia(t) })
            .then(parseJsonTransferenciasAjax)
            .then(res => {
                const idSale = Number(res.id_sale);
                if (res.success && idSale > 0) {
                    Swal.fire({ icon: 'success', title: 'Venta creada', text: 'Abriendo recibo…', timer: 1200, showConfirmButton: false });
                    setTimeout(() => verRecibo(idSale), 1200);
                } else if (!res.success && idSale > 0) {
                    Swal.fire({ icon: 'warning', title: 'Venta registrada', text: res.message || 'Revise el estado en la base de datos.' });
                    verRecibo(idSale);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo aprobar' });
                }
                cargarTransferencias();
            })
            .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
    });
}

function rechazar(id) {
    const t = transferencias.find(x => x.id_transfer == id);
    if (!t) return;

    Swal.fire({
        title: '¿Rechazar transferencia?',
        html: `<p class="mb-1">${adminEscapeHtml(transferNombreCompleto(t))}</p>
               <small class="text-muted font-monospace">${adminEscapeHtml(t.code_transfer)}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        reverseButtons: true,
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Procesando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch(TRANSFERENCIAS_ENDPOINT, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'rechazar',
                id_transfer: String(t.id_transfer ?? ''),
                csrf_token: window.APP_CSRF_TOKEN || '',
            }),
        })
            .then(parseJsonTransferenciasAjax)
            .then(res => {
                if (res.success !== false) {
                    Swal.fire({ icon: 'success', title: 'Rechazado', text: 'La transferencia fue rechazada' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo rechazar' });
                }
                cargarTransferencias();
            })
            .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
    });
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function verRecibo(id) {
    const fd = new FormData();
    fd.append('action', 'detalle_venta');
    fd.append('id_sale', id);

    fetch(VENTAS_ENDPOINT, { method: 'POST', body: fd })
        .then(parseJsonTransferenciasAjax)
        .then(res => {
            if (res.success) {
                document.getElementById('cuerpoRecibo').innerHTML = res.html_recibo;
                new bootstrap.Modal(document.getElementById('modalRecibo')).show();
            } else {
                Swal.fire('Error', 'No se pudo cargar el recibo', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'No se pudo cargar el recibo', 'error'));
}
