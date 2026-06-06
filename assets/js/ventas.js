let paginaActual = 1;
const registrosPorPagina = 10;
const VENTAS_ENDPOINT = '/front/ajax/ventas.ajax.php';

function formatVendedorVenta(emailAdmin) {
    const e = String(emailAdmin ?? 'Sistema').trim();
    return e || 'Sistema';
}

function ventaNombreCompleto(v) {
    return `${v.name_customer || ''} ${v.lastname_customer || ''}`.trim();
}

document.addEventListener('DOMContentLoaded', function() {
    

    cargarAdminsSelect();
    cargarOrigenesSelect();
    

    cargarVentas();

    const filtrosIds = ['filterMetodoPago', 'filterAdmin', 'filterRifa', 'filterOrigen', 'filterPeriodo'];
    filtrosIds.forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            paginaActual = 1;
            cargarVentas();
        });
    });

    document.getElementById('searchVentas')?.addEventListener('input', debounce(() => {
        paginaActual = 1;
        cargarVentas();
    }, 600));

    ['fecha_inicio', 'fecha_fin'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', function() {
            if (this.value !== "") document.getElementById('filterPeriodo').value = '';
            if (document.getElementById('fecha_inicio').value && document.getElementById('fecha_fin').value) {
                paginaActual = 1;
                cargarVentas();
            }
        });
    });
});

async function cargarVentas() {

    if (typeof showPreloader === 'function') showPreloader();

    const tbody = document.getElementById('bodyTabla');
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5">Cargando datos...</td></tr>`;

    try {
        const fd = new FormData();
        fd.append('action', 'obtener');
        fd.append('page', paginaActual);
        fd.append('limit', registrosPorPagina);
        

        fd.append('search', document.getElementById('searchVentas')?.value || '');
        fd.append('id_raffle', document.getElementById('filterRifa')?.value || '');
        fd.append('id_admin', document.getElementById('filterAdmin')?.value || '');
        fd.append('payment_method', document.getElementById('filterMetodoPago')?.value || '');
        fd.append('periodo', document.getElementById('filterPeriodo')?.value || '');
        fd.append('fecha_inicio', document.getElementById('fecha_inicio')?.value || '');
        fd.append('fecha_fin', document.getElementById('fecha_fin')?.value || '');
        fd.append('source_sale', document.getElementById('filterOrigen')?.value || '');

        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });

        if (data.success) {
            const ventas = Array.isArray(data.data) ? data.data : (data.data ? [data.data] : []);
            renderTabla(ventas);
            actualizarPaginacion(data.total);
        } else {
            renderTabla([]);
            adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las ventas.'));
        }
    } catch (e) {
        renderTabla([]);
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar las ventas.');
    } finally {
        if (typeof hidePreloader === 'function') hidePreloader();
    }
}

function renderTabla(ventas) {
    const tbody = document.getElementById('bodyTabla');
    if (!tbody) return;

    if (ventas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron registros</td></tr>`;
        return;
    }

    tbody.innerHTML = ventas.map(v => renderVentaRow(v)).join('');
}

function ventaDatos(v) {
    const raw = v.date_created_sale ? String(v.date_created_sale).replace(' ', 'T') + '-05:00' : null;
    const f = raw ? new Date(raw) : new Date();
    const opts = { timeZone: 'America/Bogota' };
    const fecha = f.toLocaleDateString('es-CO', opts);
    const hora = f.toLocaleTimeString('es-CO', {
        ...opts,
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
    const inicial = v.name_customer ? v.name_customer.charAt(0).toUpperCase() : 'C';
    const nombre = ventaNombreCompleto(v);
    const vendedor = formatVendedorVenta(v.email_admin);
    const origen = String(v.source_sale ?? '').trim();
    const badgeClass = adminPaymentMethodBadgeClass(v.payment_method_sale);
    const qtyLabel = `${v.quantity_sale} núm${v.quantity_sale > 1 ? 's' : ''}`;
    const totalLabel = `$${Number(v.total_sale).toLocaleString('es-CO')}`;
    return { fecha, hora, inicial, nombre, vendedor, origen, badgeClass, qtyLabel, totalLabel };
}

function renderVentaClientMobile(d, v) {
    return `
    <div class="admin-card-head d-lg-none">
        ${adminClientMobileHead({
            initial: d.inicial,
            name: d.nombre,
            phone: v.phone_customer,
            extraHtml: adminVendedorOrigenLines(formatVendedorShort(d.vendedor), d.origen),
        })}
        <div class="admin-card-head__status-row">
            <span class="badge ${d.badgeClass} px-2 py-1 rounded-pill">${adminEscapeHtml(v.payment_method_sale)}</span>
        </div>
        <div class="admin-card-head__meta">
            <span class="card-meta-chip card-meta-chip--qty">${adminEscapeHtml(d.qtyLabel)}</span>
            <span class="card-meta-chip card-meta-chip--total">${adminEscapeHtml(d.totalLabel)}</span>
        </div>
        <div class="admin-card-head__rifa">
            <span class="cell-rifa-name">${adminEscapeHtml(v.title_raffle || '—')}</span>
        </div>
        <div class="admin-card-head__code-fecha d-lg-none">
            ${adminTokenChipBlock(v.code_sale)}
            ${adminFechaCompact(d.fecha, d.hora)}
        </div>
    </div>`;
}

function formatVendedorShort(emailAdmin) {
    const e = String(emailAdmin ?? 'Sistema').trim() || 'Sistema';
    const at = e.indexOf('@');
    return at > 0 ? e.slice(0, at) : e;
}

function renderVentaClientDesktop(d, v) {
    const vendedorShort = adminEscapeHtml(formatVendedorShort(d.vendedor));
    const origen = String(d.origen ?? '').trim();
    const metaLine = origen
        ? `<small class="text-muted fst-italic venta-meta-desktop">
            <i class="ti ti-user"></i> ${vendedorShort}
            &nbsp;·&nbsp;<i class="ti ti-world"></i> ${adminEscapeHtml(origen)}
           </small>`
        : `<small class="text-muted fst-italic venta-meta-desktop">
            <i class="ti ti-user"></i> ${vendedorShort}
           </small>`;

    return `
    <div class="d-none d-lg-flex">
        <div class="rounded-circle bg-light border d-flex justify-content-center align-items-center text-secondary fw-bold me-3 flex-shrink-0 venta-avatar-desktop">
            ${adminEscapeHtml(d.inicial)}
        </div>
        <div class="d-flex flex-column venta-client-desktop-col">
            <span class="fw-bold text-dark text-capitalize">${adminEscapeHtml(d.nombre)}</span>
            <div class="text-muted small mt-1">
                <span class="me-2"><i class="ti ti-phone"></i> ${adminEscapeHtml(v.phone_customer || '—')}</span>
            </div>
            ${metaLine}
        </div>
    </div>`;
}

function renderVentaActionsMobile(id) {
    return `
    <div class="d-lg-none">
        <div class="btn-group btn-group-sm shadow-sm w-100 venta-mobile-actions" role="group">
            <button type="button" class="btn btn-outline-primary flex-fill" onclick="verRecibo(${id})" title="Ver Detalle">
                <i class="ti ti-eye"></i> Ver
            </button>
            <button type="button" class="btn btn-outline-secondary flex-fill" onclick="gestionarVenta(${id})" title="Gestionar venta">
                <i class="ti ti-settings"></i> Gestionar
            </button>
        </div>
    </div>`;
}

function renderVentaActionsDesktop(id) {
    return `
    <button type="button" class="btn btn-icon btn-sm btn-outline-primary border-0 rounded-circle shadow-sm d-none d-lg-inline-flex venta-action-btn"
        onclick="verRecibo(${id})" title="Ver Detalle">
        <i class="ti ti-eye fs-7"></i>
    </button>
    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary border-0 rounded-circle shadow-sm ms-1 d-none d-lg-inline-flex venta-action-btn"
        onclick="gestionarVenta(${id})" title="Gestionar venta">
        <i class="ti ti-settings fs-7"></i>
    </button>`;
}

function renderVentaRow(v) {
    const d = ventaDatos(v);
    return `
    <tr class="align-middle border-bottom card-row-admin card-row-venta venta-table-row">
        <td class="py-3 ps-3 mobile-card-head">
            ${renderVentaClientMobile(d, v)}
            ${renderVentaClientDesktop(d, v)}
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="font-monospace bg-light text-primary px-2 py-1 rounded border venta-code-chip">${adminEscapeHtml(v.code_sale)}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="fw-medium text-dark d-block">${adminEscapeHtml(d.qtyLabel)}</span>
            <small class="text-muted text-truncate d-block venta-rifa-truncate">${adminEscapeHtml(v.title_raffle || '—')}</small>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="fw-bold text-dark">${adminEscapeHtml(d.totalLabel)}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="badge ${d.badgeClass} px-3 py-2 rounded-pill">${adminEscapeHtml(v.payment_method_sale)}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <div class="d-flex flex-column text-muted">
                <span class="text-dark fw-medium">${adminEscapeHtml(d.fecha)}</span>
                <span class="venta-hora-desktop">${adminEscapeHtml(d.hora)}</span>
            </div>
        </td>
        <td class="py-3 text-end pe-3 mobile-card-actions">
            ${renderVentaActionsMobile(v.id_sale)}
            ${renderVentaActionsDesktop(v.id_sale)}
        </td>
    </tr>`;
}

function actualizarPaginacion(totalItems) {
    const totalPaginas = Math.ceil(totalItems / registrosPorPagina);
    const contenedor = document.getElementById('contenedorPaginacion');
    const info = document.getElementById('infoPaginacion');

    if (info) {
        const desde = totalItems === 0 ? 0 : (paginaActual - 1) * registrosPorPagina + 1;
        const hasta = Math.min(paginaActual * registrosPorPagina, totalItems);
        info.innerText = `Mostrando ${desde} a ${hasta} de ${totalItems.toLocaleString()} registros`;
    }

    if (!contenedor) return;

    let html = '';
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${paginaActual - 1})">Anterior</a></li>`;

    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, inicio + 4);
    if (fin - inicio < 4) inicio = Math.max(1, fin - 4);

    for (let i = inicio; i <= fin; i++) {
        if (i <= 0) continue;
        html += `<li class="page-item ${i === paginaActual ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${i})">${i}</a></li>`;
    }

    html += `<li class="page-item ${paginaActual >= totalPaginas || totalPaginas === 0 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${paginaActual + 1})">Siguiente</a></li>`;
    contenedor.innerHTML = html;
}

function cambiarPagina(p) {
    paginaActual = p;
    cargarVentas();
    window.scrollTo(0, 0);
}

async function cargarRifasSelect() {
    try {
        const fd = new FormData();
        fd.append('action', 'obtener_rifas');
        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (data.success) {
            const sel = document.getElementById('filterRifa');
            if (sel) sel.innerHTML = '<option value="">Todas las rifas</option>' + data.data.map(r => `<option value="${r.id_raffle}">${r.title_raffle}</option>`).join('');
        } else {
            adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las rifas.'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar las rifas.');
    }
}

async function cargarAdminsSelect() {
    try {
        const fd = new FormData(); fd.append('action', 'obtener_admins');
        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (data.success) {
            const sel = document.getElementById('filterAdmin');
            if (sel) sel.innerHTML = '<option value="">Todos los admins</option>' + data.data.map(a => `<option value="${a.id_admin}">${a.email_admin}</option>`).join('');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los vendedores.');
    }
}

async function cargarOrigenesSelect() {
    try {
        const fd = new FormData(); fd.append('action', 'obtener_origenes');
        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (data.success && data.data) {
            const sel = document.getElementById('filterOrigen');
            if (sel) sel.innerHTML = '<option value="">Todos los orígenes</option>' + data.data.map(o => `<option value="${o}">${o}</option>`).join('');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los orígenes.');
    }
}

function debounce(f, w) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), w); };
}

async function verRecibo(id) {
    try {
        const fd = new FormData();
        fd.append('action', 'detalle_venta');
        fd.append('id_sale', id);
        const res = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (res.success) {
            document.getElementById('cuerpoRecibo').innerHTML = res.html_recibo;
            new bootstrap.Modal(document.getElementById('modalRecibo')).show();
        } else {
            adminNotifyError(adminExtractMessage(res, 'No se pudo cargar el recibo.'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el recibo.');
    }
}

function gestionarVenta(id) {
    Swal.fire({
        title: 'Gestionar venta',
        text: 'Seleccione una acción',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="ti ti-trash"></i> Anular venta',
        denyButtonText: '<i class="ti ti-scissors"></i> Anulación parcial',
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#dc3545',
        denyButtonColor: '#fd7e14',
    }).then(result => {
        if (result.isConfirmed) {
            anularVenta(id);
        } else if (result.isDenied) {
            anularParcialVenta(id);
        }
    });
}

function anularVenta(id) {
    Swal.fire({
        title: '¿Anular venta?',
        text: 'Esta acción no se puede revertir',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),

        preConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('action', 'anular');
                fd.append('id_sale', id);
                const csrfToken = window.APP_CSRF_TOKEN || '';
                fd.append('csrf_token', csrfToken);

                const res = await fetch(VENTAS_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': csrfToken
                    },
                    body: fd
                });

                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al anular');
                }

                return data;

            } catch (error) {
                Swal.showValidationMessage(error.message);
            }
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire('OK', 'Venta anulada correctamente', 'success');
            cargarVentas();
        }
    });
}

async function anularParcialVenta(id) {
    try {
        const fd = new FormData();
        fd.append('action', 'detalle_venta');
        fd.append('id_sale', id);
        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (!data.success) {
            Swal.fire('Error', adminExtractMessage(data, 'No se pudo cargar la venta'), 'error');
            return;
        }

        const tmp = document.createElement('div');
        tmp.innerHTML = data.html_recibo || '';
        const ticketEls = tmp.querySelectorAll('[data-ticket-id]');
        let optionsHtml = '';
        if (ticketEls.length) {
            ticketEls.forEach(el => {
                optionsHtml += `<label class="d-block mb-1"><input type="checkbox" class="ticket-cancel-check" value="${el.getAttribute('data-ticket-id')}"> ${el.textContent.trim()}</label>`;
            });
        } else {
            optionsHtml = '<p class="text-muted small">No se encontraron IDs de tickets en el recibo.</p>';
        }

        const result = await Swal.fire({
            title: 'Anulación parcial',
            html: `<p class="small text-muted">Selecciona los nros a liberar. El resto permanece en la venta.</p>${optionsHtml}`,
            showCancelButton: true,
            confirmButtonText: 'Anular seleccionados',
            preConfirm: () => {
                const checks = document.querySelectorAll('.ticket-cancel-check:checked');
                const ids = [...checks].map(c => c.value);
                if (!ids.length) {
                    Swal.showValidationMessage('Seleccione al menos un nro');
                    return false;
                }
                return ids;
            }
        });

        if (!result.isConfirmed) return;

        const fd2 = new FormData();
        fd2.append('action', 'anular_parcial');
        fd2.append('id_sale', id);
        fd2.append('csrf_token', window.APP_CSRF_TOKEN || '');
        result.value.forEach(tid => fd2.append('ticket_ids[]', tid));

        const d2 = await adminFetchJson(VENTAS_ENDPOINT, {
            body: fd2,
            withCsrf: true,
            headers: { 'X-CSRF-Token': window.APP_CSRF_TOKEN || '' },
        });

        if (d2.success) {
            const totalMsg = d2.total_sale != null
                ? ` Nuevo total: $${Number(d2.total_sale).toLocaleString('es-CO')}.`
                : '';
            Swal.fire('OK', `Liberados ${d2.released || 0} nro(s). Quedan ${d2.remaining || 0}.${totalMsg}`, 'success');
            cargarVentas();
        } else {
            Swal.fire('Error', adminExtractMessage(d2, 'No se pudo anular'), 'error');
        }
    } catch (e) {
        Swal.fire('Error', e instanceof Error ? e.message : 'Error de conexión', 'error');
    }
}
