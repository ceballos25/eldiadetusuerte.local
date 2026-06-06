let cache = [], paginaActual = 1;
const registrosPorPagina = 20;
const NUMEROS_ENDPOINT = '/front/ajax/numeros.ajax.php';

const numerosToast = typeof Swal !== 'undefined'
    ? Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
    })
    : null;

function numerosAlertError(mensaje) {
    const msg = mensaje || 'Ocurrió un error';
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
        return;
    }
    if (typeof adminNotifyError === 'function') {
        adminNotifyError(msg);
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.error(msg);
        return;
    }
    alert(msg);
}

function numerosTicketById(id) {
    return cache.find(t => Number(t.id_ticket) === Number(id));
}

document.addEventListener('DOMContentLoaded', () => {
    cargarRifasSelect();

    const elRifa = document.getElementById('filterRifa');
    const elEstado = document.getElementById('filterEstado');
    const elSearch = document.getElementById('searchNumeros');

    if (elRifa) elRifa.addEventListener('change', () => { paginaActual = 1; cargarInventario(); });
    if (elEstado) elEstado.addEventListener('change', () => { paginaActual = 1; cargarInventario(); });
    if (elSearch) elSearch.addEventListener('input', debounce(() => { paginaActual = 1; cargarInventario(); }, 500));
});

async function cargarRifasSelect() {
    try {
        const fd = new FormData();
        fd.append('action', 'obtener_rifas');
        const j = await adminFetchJson(NUMEROS_ENDPOINT, { body: fd });
        const s = document.getElementById('filterRifa');
        if (j.success && s) {
            s.innerHTML = '<option value="" disabled selected>Seleccione...</option>';
            j.data.forEach(raffle => {
                s.innerHTML += `<option value="${raffle.id_raffle}">${raffle.title_raffle}</option>`;
            });
            if (j.data.length > 0) {
                s.value = j.data[0].id_raffle;
                cargarInventario();
            }
        }
    } catch (e) {
        numerosAlertError(e instanceof Error ? e.message : 'No se pudieron cargar las rifas.');
    }
}

async function cargarInventario() {
    const idRaffle = document.getElementById('filterRifa')?.value;
    if (!idRaffle) return;

    const tbody = document.getElementById('bodyTablaNumeros');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-5 text-muted">Cargando...</td></tr>`;
    }

    try {
        const fd = new FormData();
        fd.append('action', 'obtener_inventario');
        fd.append('id_raffle', idRaffle);
        fd.append('search', document.getElementById('searchNumeros')?.value || '');
        fd.append('status', document.getElementById('filterEstado')?.value || '');

        const data = await adminFetchJson(NUMEROS_ENDPOINT, { body: fd });

        cache = data.success ? data.data : [];
        if (!data.success) {
            numerosAlertError(adminExtractMessage(data, 'Error al cargar inventario'));
        }
        renderTodo();
    } catch (e) {
        cache = [];
        renderTodo();
        numerosAlertError(e instanceof Error ? e.message : 'Error al cargar inventario');
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
    }
}

function cambiarPagina(p) {
    paginaActual = p;
    renderTodo();
}

function numeroStatusBadge(status) {
    if (status === 1) {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Vendido</span>';
    }
    if (status === 2) {
        return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill">Reservado</span>';
    }
    return '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Disponible</span>';
}

function renderPremiumControl(t, status) {
    const premiumChecked = Number(t.is_premium_ticket || 0) === 1 ? 'checked' : '';
    if (status === 1) {
        return '<span class="text-muted small">No editable</span>';
    }
    return `
    <div class="form-check form-switch d-inline-block mb-0" title="Marcar o desmarcar como premium">
        <input class="form-check-input" type="checkbox" ${premiumChecked}
            onchange="cambiarPremiumTicket(${t.id_ticket}, this.checked, this)">
    </div>`;
}

function renderNumeroActionsMobile(id, status) {
    if (status === 1) {
        return `
        <div class="d-lg-none">
            <button type="button" class="btn btn-light btn-sm w-100 border text-muted" disabled>
                <i class="ti ti-ban me-1"></i> Bloqueado
            </button>
        </div>`;
    }
    if (status === 2) {
        return `
        <div class="d-lg-none">
            <button type="button" class="btn btn-outline-success btn-sm w-100"
                onclick="cambiarEstadoTicket(${id}, 0)">
                <i class="ti ti-lock-open me-1"></i> Liberar
            </button>
        </div>`;
    }
    return `
    <div class="d-lg-none">
        <button type="button" class="btn btn-outline-secondary btn-sm w-100"
            onclick="cambiarEstadoTicket(${id}, 2)">
            <i class="ti ti-lock me-1"></i> Reservar
        </button>
    </div>`;
}

function renderNumeroActionsDesktop(id, status) {
    if (status === 1) {
        return `
        <button type="button" class="btn btn-icon btn-sm btn-light border-0 rounded-circle shadow-sm numeros-action-btn d-none d-lg-inline-flex"
            disabled title="No se puede cambiar">
            <i class="ti ti-ban fs-7"></i>
        </button>`;
    }
    if (status === 2) {
        return `
        <button type="button" class="btn btn-icon btn-sm btn-outline-success border-0 rounded-circle shadow-sm numeros-action-btn d-none d-lg-inline-flex"
            onclick="cambiarEstadoTicket(${id}, 0)" title="Liberar">
            <i class="ti ti-lock-open fs-7"></i>
        </button>`;
    }
    return `
    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary border-0 rounded-circle shadow-sm numeros-action-btn d-none d-lg-inline-flex"
        onclick="cambiarEstadoTicket(${id}, 2)" title="Reservar">
        <i class="ti ti-lock fs-7"></i>
    </button>`;
}

function renderNumeroMobile(t, status, badge, premiumControl) {
    return `
    <div class="admin-card-head d-lg-none">
        <div class="admin-card-head__meta admin-card-head__meta--numero">
            ${adminNumeroInventarioBadge(t.number_ticket, status, t.is_premium_ticket)}
        </div>
        <div class="admin-card-head__status-row numeros-card-status-row">
            ${badge}
            <div class="admin-card-head__meta admin-card-head__meta--premium numeros-card-premium">
                <span class="small text-muted">Premium</span>
                ${premiumControl}
            </div>
        </div>
    </div>`;
}

function renderNumeroRow(t) {
    const status = parseInt(t.status_ticket, 10);
    const badge = numeroStatusBadge(status);
    const premiumControl = renderPremiumControl(t, status);

    return `
    <tr class="align-middle border-bottom card-row-admin card-row-numeros numeros-table-row">
        <td class="py-3 ps-3 mobile-card-head">
            ${renderNumeroMobile(t, status, badge, premiumControl)}
            <div class="d-none d-lg-flex numeros-col-numero-desktop">${adminNumeroInventarioBadge(t.number_ticket, status, t.is_premium_ticket)}</div>
        </td>
        <td class="d-none d-lg-table-cell py-3 text-center">${badge}</td>
        <td class="d-none d-lg-table-cell py-3 text-center">${premiumControl}</td>
        <td class="py-3 text-end pe-3 mobile-card-actions">
            ${renderNumeroActionsMobile(t.id_ticket, status)}
            ${renderNumeroActionsDesktop(t.id_ticket, status)}
        </td>
    </tr>`;
}

function renderTabla(datos) {
    const tbody = document.getElementById('bodyTablaNumeros');
    if (!tbody) return;

    if (!datos || datos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-5 text-muted">No se encontraron nros</td></tr>`;
        return;
    }

    tbody.innerHTML = datos.map(t => renderNumeroRow(t)).join('');
}

async function cambiarEstadoTicket(id, nuevoEstado) {
    const ticket = numerosTicketById(id);
    const numero = ticket ? String(ticket.number_ticket) : '';
    const esLiberar = nuevoEstado === 0;

    if (typeof Swal === 'undefined') {
        const accion = esLiberar ? 'liberar' : 'reservar';
        if (!window.confirm(`¿Deseas ${accion} este nro?`)) return;
        try {
            const fd = new FormData();
            fd.append('action', 'cambiar_estado');
            fd.append('id_ticket', id);
            fd.append('status', nuevoEstado);
            fd.append('csrf_token', window.APP_CSRF_TOKEN || '');
            const r = await fetch(NUMEROS_ENDPOINT, { method: 'POST', body: fd });
            const j = await r.json();
            if (!j.success) {
                numerosAlertError(j.message || 'Error al cambiar estado');
                return;
            }
            cargarInventario();
        } catch (e) {
            numerosAlertError(e instanceof Error ? e.message : 'Error de conexión');
        }
        return;
    }

    const result = await Swal.fire({
            title: esLiberar ? '¿Liberar nro?' : '¿Reservar nro?',
            html: numero
                ? `<p class="mb-2 text-muted">El nro quedará como <strong>${esLiberar ? 'disponible' : 'reservado'}</strong>.</p>
                   ${crNumeroChipHtml(numero, 'lg')}`
                : `<p class="mb-0 text-muted">El nro quedará como <strong>${esLiberar ? 'disponible' : 'reservado'}</strong>.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: esLiberar ? '<i class="ti ti-lock-open me-1"></i> Sí, liberar' : '<i class="ti ti-lock me-1"></i> Sí, reservar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: esLiberar ? '#198754' : '#6c757d',
            reverseButtons: true,
            focusCancel: true,
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: async () => {
                try {
                    const fd = new FormData();
                    fd.append('action', 'cambiar_estado');
                    fd.append('id_ticket', id);
                    fd.append('status', nuevoEstado);
                    fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

                    const r = await fetch(NUMEROS_ENDPOINT, { method: 'POST', body: fd });
                    const j = await r.json();
                    if (!j.success) {
                        throw new Error(j.message || 'Error al cambiar estado');
                    }
                    return j;
                } catch (e) {
                    Swal.showValidationMessage(e.message || 'Error de conexión');
                }
            },
        });

    if (!result.isConfirmed) return;

    if (numerosToast) {
        numerosToast.fire({
            icon: 'success',
            title: esLiberar ? 'Nro liberado' : 'Nro reservado',
        });
    }

    cargarInventario();
}

async function cambiarPremiumTicket(id, checked, controlEl) {
    if (!id) return;
    const previous = !checked;
    if (controlEl) controlEl.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'marcar_flags');
        fd.append('id_ticket', id);
        fd.append('is_premium_ticket', checked ? 1 : 0);
        fd.append('csrf_token', window.APP_CSRF_TOKEN || '');

        const r = await fetch(NUMEROS_ENDPOINT, { method: 'POST', body: fd });
        const j = await r.json();

        if (!j.success) {
            if (controlEl) controlEl.checked = previous;
            numerosAlertError(j.message || 'No se pudo actualizar premium');
            return;
        }

        cache = cache.map(item => {
            if (Number(item.id_ticket) !== Number(id)) return item;
            return { ...item, is_premium_ticket: checked ? 1 : 0 };
        });

        if (numerosToast) {
            numerosToast.fire({
                icon: 'success',
                title: checked ? 'Marcado como premium' : 'Premium removido',
            });
        }
    } catch (e) {
        if (controlEl) controlEl.checked = previous;
        numerosAlertError(e instanceof Error ? e.message : 'Error de conexión');
    } finally {
        if (controlEl) controlEl.disabled = false;
    }
}

function limpiarFiltrosNumeros() {
    document.getElementById('searchNumeros').value = '';
    document.getElementById('filterEstado').value = '';
    cargarInventario();
}

function debounce(f, t) {
    let e;
    return () => {
        clearTimeout(e);
        e = setTimeout(f, t);
    };
}
