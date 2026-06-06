let rifasCache = [], idRifaEliminar = null, modalRifa = null, modalConfirm = null;
let paginaActual = 1;
const registrosPorPagina = 10;
const RIFAS_ENDPOINT = '/front/ajax/rifas.ajax.php';

document.addEventListener('DOMContentLoaded', function() {
    const elModalRifa = document.getElementById('modalRifa');
    const elModalConfirm = document.getElementById('modalConfirm');

    if (elModalRifa) modalRifa = new bootstrap.Modal(elModalRifa);
    if (elModalConfirm) modalConfirm = new bootstrap.Modal(elModalConfirm);

    if (document.getElementById('bodyTabla')) cargarRifas();

    const inputSearch = document.getElementById('searchRifas');
    if (inputSearch) inputSearch.addEventListener('input', debounce(cargarRifas, 500));

    const selectStatus = document.getElementById('filterStatus');
    if (selectStatus) selectStatus.addEventListener('change', cargarRifas);

    const inputPrecio = document.getElementById('precio');
    if (inputPrecio && typeof adminBindCOPInput === 'function') adminBindCOPInput(inputPrecio);
});

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) {

        el.value = (value !== null && value !== undefined) ? value : '';
    }
}

function setTxt(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

async function cargarRifas() {
    if (typeof showPreloader === 'function') showPreloader(); 
    try {
        const searchInput = document.getElementById('searchRifas');
        const statusSelect = document.getElementById('filterStatus');

        const formData = new FormData();
        formData.append('action', 'obtener');
        formData.append('search', searchInput ? searchInput.value.trim() : '');
        formData.append('status', statusSelect ? statusSelect.value : '');

        const data = await adminFetchJson(RIFAS_ENDPOINT, { body: formData });

        if (data.success) {
            rifasCache = data.data || [];
            renderizarTodo();
        } else {
            adminNotifyError(adminExtractMessage(data, 'No se pudieron cargar las rifas.'));
        }
    } catch (error) {
        adminNotifyError(error instanceof Error ? error.message : 'No se pudieron cargar las rifas.');
    } finally {
        if (typeof hidePreloader === 'function') hidePreloader();
    }
}

function renderizarTodo() {
    if (typeof PaginationHelper === 'undefined') return;
    const segmento = PaginationHelper.getSegment(rifasCache, paginaActual, registrosPorPagina);
    renderTabla(segmento);
    PaginationHelper.render({
        totalItems: rifasCache.length,
        currentPage: paginaActual,
        limit: registrosPorPagina,
        containerId: 'contenedorPaginacion',
        infoId: 'infoPaginacion',
        callbackName: 'cambiarPagina'
    });
}

function renderTabla(rifas) {
    const tbody = document.getElementById('bodyTabla');
    if (!tbody) return;
    if (!rifas || rifas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">No hay rifas registradas</td></tr>`;
        if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('rifasMobile', []);
        return;
    }

    tbody.innerHTML = rifas.map(r => {
        const activo = parseInt(r.status_raffle) === 1;
        const dt = adminFormatDateColombia(r.date_raffle);
        return `
            <tr class="align-middle admin-table-row">
                <td class="py-3 ps-4">${r.id_raffle}</td>
                <td class="py-3 fw-bold text-dark">${adminEscapeHtml(r.title_raffle)}</td>
                <td class="py-3 text-truncate" style="max-width:160px" title="${adminEscapeHtml(r.description_raffle || '')}">${r.description_raffle || '-'}</td>
                <td class="py-3">${r.digits_raffle} dígitos</td>
                <td class="py-3 fw-bold">$${(typeof formatPrecioCOP === 'function' ? formatPrecioCOP(r.price_raffle) : adminFormatCOP(r.price_raffle))}</td>
                <td class="py-3">${adminDateBlock(dt.fecha, dt.hora || '')}</td>
                <td class="py-3"><span class="badge ${activo ? 'bg-success' : 'bg-danger'}">${activo ? 'Activa' : 'Inactiva'}</span></td>
                <td class="py-3">${adminIconActions(adminIconBtn(`editarRifa(${r.id_raffle})`, 'ti-edit', 'primary', 'Editar'))}</td>
            </tr>`;
    }).join('');

    if (typeof renderAdminMobileRows === 'function') {
        renderAdminMobileRows('rifasMobile', rifas.map(r => renderRifaMobileCard(r)));
    }
}

function renderRifaMobileCard(r) {
    const activo = parseInt(r.status_raffle) === 1;
    const dt = adminFormatDateColombia(r.date_raffle);
    const precio = `$${typeof formatPrecioCOP === 'function' ? formatPrecioCOP(r.price_raffle) : adminFormatCOP(r.price_raffle)}`;
    const desc = String(r.description_raffle || '').trim();
    const id = Number(r.id_raffle);

    const statusClass = activo ? 'rifa-mobile-card--active' : 'rifa-mobile-card--inactive';
    const statusBadge = activo
        ? '<span class="badge bg-success-subtle text-success border border-success-subtle rifa-mobile-card__status"><i class="ti ti-circle-check me-1"></i>Activa</span>'
        : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rifa-mobile-card__status"><i class="ti ti-circle-x me-1"></i>Inactiva</span>';

    const fechaLine = dt.hora
        ? `${adminEscapeHtml(dt.fecha)} · ${adminEscapeHtml(dt.hora)}`
        : adminEscapeHtml(dt.fecha || '—');

    return `
    <article class="rifa-mobile-card ${statusClass}">
        <div class="rifa-mobile-card__head">
            <div class="rifa-mobile-card__avatar" aria-hidden="true">
                <i class="ti ti-ticket"></i>
            </div>
            <div class="rifa-mobile-card__head-text min-w-0">
                <h3 class="rifa-mobile-card__title">${adminEscapeHtml(r.title_raffle)}</h3>
                <span class="rifa-mobile-card__id">Rifa #${id}</span>
            </div>
            ${statusBadge}
        </div>
        ${desc ? `<p class="rifa-mobile-card__desc" title="${adminEscapeHtml(desc)}">${adminEscapeHtml(desc)}</p>` : ''}
        <div class="rifa-mobile-card__meta">
            <div class="rifa-mobile-chip rifa-mobile-chip--price">
                <span class="rifa-mobile-chip__icon"><i class="ti ti-currency-dollar"></i></span>
                <span class="rifa-mobile-chip__body">
                    <span class="rifa-mobile-chip__label">Precio</span>
                    <span class="rifa-mobile-chip__value">${adminEscapeHtml(precio)}</span>
                </span>
            </div>
            <div class="rifa-mobile-chip">
                <span class="rifa-mobile-chip__icon"><i class="ti ti-hash"></i></span>
                <span class="rifa-mobile-chip__body">
                    <span class="rifa-mobile-chip__label">Cifras</span>
                    <span class="rifa-mobile-chip__value">${adminEscapeHtml(String(r.digits_raffle))}</span>
                </span>
            </div>
            <div class="rifa-mobile-chip rifa-mobile-chip--wide">
                <span class="rifa-mobile-chip__icon"><i class="ti ti-calendar-event"></i></span>
                <span class="rifa-mobile-chip__body">
                    <span class="rifa-mobile-chip__label">Sorteo</span>
                    <span class="rifa-mobile-chip__value">${fechaLine}</span>
                </span>
            </div>
        </div>
        <div class="rifa-mobile-card__actions">
            <button type="button" class="btn btn-primary btn-sm w-100" onclick="editarRifa(${id})">
                <i class="ti ti-edit me-1"></i> Editar rifa
            </button>
        </div>
    </article>`;
}

function abrirModal() {
    const form = document.getElementById('formRifa');
    if (form) form.reset();
    setVal('rifaId', '');
    setTxt('modalTitle', 'Nueva Rifa');
    if(modalRifa) modalRifa.show();
}

function editarRifa(id) {
    const r = rifasCache.find(x => parseInt(x.id_raffle) === parseInt(id));
    if (!r) return;

    setVal('rifaId', r.id_raffle);
    setVal('titulo', r.title_raffle);
    setVal('descripcion', r.description_raffle);
    setVal('precio', typeof adminFormatCOP === 'function' ? adminFormatCOP(r.price_raffle) : r.price_raffle);
    setVal('cifras', r.digits_raffle);
    setVal('fecha', r.date_raffle ? r.date_raffle.replace(" ", "T") : '');
    setVal('estado', r.status_raffle);
    setVal('tipoRifa', r.type_raffle || 'automatic');
    setTxt('modalTitle', 'Editar Rifa');
    if(modalRifa) modalRifa.show();
}

async function guardarRifa() {
    const id = document.getElementById('rifaId')?.value;
    const precioNum = typeof adminParseCOP === 'function'
        ? adminParseCOP(document.getElementById('precio')?.value)
        : (typeof parsePrecioCOP === 'function'
            ? parsePrecioCOP(document.getElementById('precio')?.value)
            : 0);

    if (!precioNum || precioNum <= 0) {
        alertify.error('Ingresa un precio válido para la boleta');
        document.getElementById('precio')?.focus();
        return;
    }

    const formData = new FormData();
    formData.append('action', id ? 'actualizar' : 'crear');
    formData.append('id_raffle', id || '');
    formData.append('title_raffle', document.getElementById('titulo')?.value.trim() || '');
    formData.append('description_raffle', document.getElementById('descripcion')?.value.trim() || '');
    formData.append('price_raffle', String(precioNum));
    formData.append('digits_raffle', document.getElementById('cifras')?.value || '4');
    formData.append('date_raffle', document.getElementById('fecha')?.value || '');
    formData.append('status_raffle', document.getElementById('estado')?.value || '1');
    formData.append('type_raffle', document.getElementById('tipoRifa')?.value || 'automatic');
    formData.append('csrf_token', window.APP_CSRF_TOKEN || '');

    if (typeof showPreloader === 'function') showPreloader();
    try {
        const res = await fetch(RIFAS_ENDPOINT, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            alertify.success(data.message);
            if(modalRifa) modalRifa.hide();
            cargarRifas();
        } else {
            alertify.error(data.message);
        }
    } catch (e) { alertify.error("Error en la solicitud"); }
    finally { if (typeof hidePreloader === 'function') hidePreloader(); }
}

function eliminarRifa(id) { 
    idRifaEliminar = id; 
    if(modalConfirm) modalConfirm.show(); 
}

async function confirmarEliminar() {
    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('id_raffle', idRifaEliminar);
    fd.append('csrf_token', window.APP_CSRF_TOKEN || '');
    if (typeof showPreloader === 'function') showPreloader();
    try {
        const res = await fetch(RIFAS_ENDPOINT, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            alertify.success('Eliminado');
            if(modalConfirm) modalConfirm.hide();
            cargarRifas();
        } else {
            alertify.error(adminExtractMessage(data, 'No se pudo eliminar la rifa.'));
        }
    } catch (e) {
        alertify.error(e instanceof Error ? e.message : 'Error en la solicitud');
    } finally { if (typeof hidePreloader === 'function') hidePreloader(); }
}

function cambiarPagina(p) { paginaActual = p; renderizarTodo(); }
function debounce(f, w) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), w); }; }

function limpiarFiltros() {
    setVal('searchRifas', '');
    setVal('filterStatus', '');
    paginaActual = 1;
    cargarRifas();
}
