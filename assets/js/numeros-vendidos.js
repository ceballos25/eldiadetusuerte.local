let cache = [], paginaActual = 1;
const registrosPorPagina = 10;
const VENTAS_ENDPOINT = '/front/ajax/ventas.ajax.php';

document.addEventListener('DOMContentLoaded', () => {
    cargarRifas();
    cargarNumeros();

    document.getElementById('searchNumeros').addEventListener('input', debounce(() => {
        paginaActual = 1;
        cargarNumeros();
    }, 500));
    document.getElementById('filterRifa').addEventListener('change', () => {
        paginaActual = 1;
        cargarNumeros();
    });
});

async function cargarRifas() {
    try {
        const fd = new FormData();
        fd.append('action', 'obtener_rifas');
        const j = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        const s = document.getElementById('filterRifa');
        if (j.success && s) {
            j.data.forEach(raffle => {
                s.innerHTML += `<option value="${raffle.id_raffle}">${raffle.title_raffle}</option>`;
            });
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar las rifas.');
    }
}

async function cargarNumeros() {
    const tbody = document.getElementById('bodyTabla');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-5">Cargando...</td></tr>`;
    }

    try {
        const fd = new FormData();
        fd.append('action', 'numeros_vendidos');
        fd.append('search', document.getElementById('searchNumeros').value);
        fd.append('id_raffle', document.getElementById('filterRifa').value);

        const data = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });

        cache = data.success ? data.data : [];
        if (!data.success) {
            adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar los nros vendidos.'));
        }
        renderTodo();
    } catch (e) {
        cache = [];
        renderTodo();
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los nros vendidos.');
    }
}

function renderTodo() {
    if (typeof PaginationHelper !== 'undefined') {
        const segmento = PaginationHelper.getSegment(cache, paginaActual, registrosPorPagina);
        renderTabla(segmento);
        PaginationHelper.render({
            totalItems: cache.length,
            currentPage: paginaActual,
            limit: registrosPorPagina,
            containerId: 'contenedorPaginacion',
            infoId: 'infoPaginacion',
            callbackName: 'cambiarPagina',
        });
    } else {
        const inicio = (paginaActual - 1) * registrosPorPagina;
        renderTabla(cache.slice(inicio, inicio + registrosPorPagina));
        renderPaginadorManual();
    }
}

function cambiarPagina(p) {
    paginaActual = p;
    renderTodo();
}

function numeroDatos(t) {
    const dt = adminFormatDateColombia(t.date_created_sale);
    return {
        fecha: dt.fecha,
        hora: dt.hora,
        inicial: adminInitial(t.name_customer),
        nombre: adminNombreCompleto(t),
    };
}

function renderNumeroClientMobile(d, t) {
    return `
    <div class="admin-card-head d-lg-none">
        <div class="admin-card-head__client d-flex align-items-center min-w-0">
            ${adminAvatar(d.inicial)}
            <div class="admin-card-client-info min-w-0 flex-grow-1">
                <span class="fw-bold text-dark text-capitalize d-block">${adminEscapeHtml(d.nombre)}</span>
                <div class="text-muted small mt-1 d-flex gap-2 flex-wrap">
                    <span><i class="ti ti-phone text-secondary"></i> ${adminEscapeHtml(t.phone_customer || '--')}</span>
                    ${t.city_customer ? `<span class="border-start ps-2"><i class="ti ti-map-pin text-secondary"></i> ${adminEscapeHtml(t.city_customer)}</span>` : ''}
                </div>
                ${t.email_customer ? `<small class="text-muted fst-italic d-block" style="font-size: 0.75rem;">${adminEscapeHtml(t.email_customer)}</small>` : ''}
            </div>
        </div>
        <div class="admin-card-head__meta admin-card-head__meta--numero">
            ${adminNumeroVendidoBadge(t.number_ticket)}
        </div>
        <div class="admin-card-head__rifa">
            <span class="cell-rifa-name">${adminEscapeHtml(t.title_raffle || '—')}</span>
        </div>
        <div class="admin-card-head__code-fecha d-lg-none">
            ${adminTokenChipBlock(t.code_sale)}
            ${adminFechaCompact(d.fecha, d.hora)}
        </div>
    </div>`;
}

function renderNumeroActionsMobile(idSale) {
    return `
    <div class="d-lg-none">
        <button type="button" class="btn btn-outline-primary btn-sm w-100"
            onclick="verReciboVenta(${idSale})" title="Ver boleta completa">
            <i class="ti ti-file-text"></i> Ver boleta
        </button>
    </div>`;
}

function renderNumeroActionsDesktop(idSale) {
    return `
    <div class="d-none d-lg-block text-end">
        <button type="button" class="btn btn-outline-primary btn-sm"
            onclick="verReciboVenta(${idSale})" title="Ver boleta completa">
            <i class="ti ti-file-text"></i> Boleta
        </button>
    </div>`;
}

function renderNumeroDetalleDesktop(t) {
    return `
    <div class="admin-venta-resumen">
        <div class="admin-venta-resumen__code">${adminTokenChip(t.code_sale)}</div>
        <div class="cell-rifa-name">${adminEscapeHtml(t.title_raffle || '—')}</div>
    </div>`;
}

function renderNumeroClientDesktop(d, t) {
    return `
    <div class="d-none d-lg-block">
        <div class="d-flex align-items-center">
            ${adminAvatar(d.inicial)}
            <div class="d-flex flex-column" style="line-height: 1.3;">
                <span class="fw-bold text-dark text-capitalize">
                    ${adminEscapeHtml(d.nombre)}
                </span>
                <div class="text-muted small mt-1 d-flex gap-2 flex-wrap">
                    <span><i class="ti ti-phone text-secondary"></i> ${adminEscapeHtml(t.phone_customer || '--')}</span>
                    ${t.city_customer ? `<span class="border-start ps-2"><i class="ti ti-map-pin text-secondary"></i> ${adminEscapeHtml(t.city_customer)}</span>` : ''}
                </div>
                ${t.email_customer ? `<small class="text-muted fst-italic" style="font-size: 0.75rem;">${adminEscapeHtml(t.email_customer)}</small>` : ''}
            </div>
        </div>
    </div>`;
}

function renderNumeroRow(t) {
    const d = numeroDatos(t);
    const idSale = parseInt(t.id_sale_ticket, 10) || 0;
    return `
    <tr class="align-middle card-row-admin admin-table-row">
        <td class="py-2 ps-3 mobile-card-head">
            ${renderNumeroClientMobile(d, t)}
            ${renderNumeroClientDesktop(d, t)}
        </td>
        <td class="d-none d-lg-table-cell py-2 text-center col-numero-desktop">
            ${adminNumeroVendidoBadge(t.number_ticket)}
        </td>
        <td class="d-none d-lg-table-cell py-2 admin-col-resumen">
            ${renderNumeroDetalleDesktop(t)}
        </td>
        <td class="d-none d-lg-table-cell py-2 col-fecha-desktop">
            ${adminFechaCompact(d.fecha, d.hora)}
        </td>
        <td class="py-2 pe-3 mobile-card-actions">
            ${idSale > 0 ? renderNumeroActionsMobile(idSale) : ''}
            ${idSale > 0 ? renderNumeroActionsDesktop(idSale) : ''}
        </td>
    </tr>`;
}

function renderTabla(datos) {
    const tbody = document.getElementById('bodyTabla');
    if (!tbody) return;

    if (!datos || datos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-muted">No se encontraron nros</td></tr>`;
        return;
    }

    tbody.innerHTML = datos.map(t => renderNumeroRow(t)).join('');
}

function renderPaginadorManual() {
    const totalPaginas = Math.ceil(cache.length / registrosPorPagina) || 1;
    const container = document.getElementById('contenedorPaginacion');
    const info = document.getElementById('infoPaginacion');

    if (info) {
        const desde = cache.length === 0 ? 0 : (paginaActual - 1) * registrosPorPagina + 1;
        const hasta = Math.min(paginaActual * registrosPorPagina, cache.length);
        info.textContent = `Mostrando ${desde} a ${hasta} de ${cache.length.toLocaleString()} registros`;
    }
    if (!container) return;

    let html = '';
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${paginaActual - 1})">Anterior</a>
    </li>`;

    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
            html += `<li class="page-item ${i === paginaActual ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${i})">${i}</a>
            </li>`;
        }
    }

    html += `<li class="page-item ${paginaActual >= totalPaginas ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${paginaActual + 1})">Siguiente</a>
    </li>`;
    container.innerHTML = html;
}

function limpiarFiltros() {
    document.getElementById('searchNumeros').value = '';
    document.getElementById('filterRifa').value = '';
    paginaActual = 1;
    cargarNumeros();
}

function debounce(f, t) {
    let e;
    return () => {
        clearTimeout(e);
        e = setTimeout(f, t);
    };
}

async function verReciboVenta(idSale) {
    if (!idSale) return;

    const fd = new FormData();
    fd.append('action', 'detalle_venta');
    fd.append('id_sale', String(idSale));

    try {
        const res = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (res.success) {
            document.getElementById('cuerpoRecibo').innerHTML = res.html_recibo;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRecibo')).show();
        } else {
            adminNotifyError(adminExtractMessage(res, 'No se pudo cargar la boleta.'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'Error al cargar la boleta.');
    }
}
