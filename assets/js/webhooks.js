const WEBHOOKS_ENDPOINT = '/front/ajax/webhooks.ajax.php';

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientes();
    listarWebhooksOpenPay();
});

function adminCsrfToken() {
    if (window.APP_CSRF_TOKEN) {
        return window.APP_CSRF_TOKEN;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
}

function webhookCsrf(fd) {
    const token = adminCsrfToken();
    fd.append('csrf_token', token);
    return fd;
}

function webhookFetch(body) {
    const token = adminCsrfToken();
    return fetch(WEBHOOKS_ENDPOINT, {
        method: 'POST',
        credentials: 'same-origin',
        headers: token ? { 'X-CSRF-Token': token } : {},
        body,
    });
}

async function webhookParseJson(res) {
    const text = await res.text();
    try {
        return JSON.parse(text.trim());
    } catch (e) {
        throw new Error('Respuesta inválida del servidor');
    }
}

async function cargarPendientes() {
    const tbody = document.getElementById('bodyWebhooks');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Cargando...</td></tr>';
    try {
        const fd = new FormData();
        fd.append('action', 'listar_pendientes');
        const res = await webhookFetch(fd);
        const data = await webhookParseJson(res);
        if (!data.success) {
            alertify.error(data.message || 'No autorizado');
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Acceso denegado</td></tr>';
            return;
        }
        renderTabla(data.data || []);
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'Error de red al cargar webhooks.');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de red</td></tr>';
    }
}

function renderTabla(rows) {
    const tbody = document.getElementById('bodyWebhooks');
    if (!tbody) return;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay webhooks pendientes</td></tr>';
        if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('webhooksMobile', []);
        return;
    }
    tbody.innerHTML = rows.map(r => renderWebhookDesktopRow(r)).join('');
    if (typeof renderAdminMobileRows === 'function') {
        renderAdminMobileRows('webhooksMobile', rows.map(r => renderWebhookMobileRow(r)));
    }
}

function webhookDatos(r) {
    const dt = adminFormatDateColombia(r.created_at);
    return { dt, uuid: r.uuid_webhook || '—' };
}

function renderWebhookDesktopRow(r) {
    const d = webhookDatos(r);
    return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4">${adminCodeBlock(d.uuid, '')}</td>
            <td class="py-3 fw-medium">${adminEscapeHtml(r.event_type_webhook || '—')}</td>
            <td class="py-3"><span class="badge bg-warning text-dark">${adminEscapeHtml(r.status_webhook || 'pending')}</span></td>
            <td class="py-3">${adminDateBlock(d.dt.fecha, d.dt.hora)}</td>
            <td class="py-3">
                <button type="button" class="btn btn-sm btn-primary" onclick='reprocesar(${JSON.stringify(d.uuid || '')})'>
                    <i class="ti ti-refresh"></i> Reprocesar
                </button>
            </td>
        </tr>`;
}

function renderWebhookMobileRow(r) {
    const d = webhookDatos(r);
    return adminMobileRow(
        adminMobileSection(adminCodeBlock(d.uuid, r.event_type_webhook || '—')) +
        adminMobileKpi(`<span class="badge bg-warning text-dark">${adminEscapeHtml(r.status_webhook || 'pending')}</span>`) +
        adminMobileBottom(adminDateBlock(d.dt.fecha, d.dt.hora),
            `<button type="button" class="btn btn-sm btn-primary" onclick='reprocesar(${JSON.stringify(d.uuid || '')})'><i class="ti ti-refresh"></i> Reprocesar</button>`)
    );
}

async function reprocesar(uuid) {
    if (!uuid) return;
    try {
        const fd = webhookCsrf(new FormData());
        fd.append('action', 'reprocesar');
        fd.append('uuid', uuid);
        const res = await webhookFetch(fd);
        const data = await webhookParseJson(res);
        if (data.success) {
            alertify.success('Webhook reprocesado');
            cargarPendientes();
        } else {
            alertify.error(data.message || 'Error al reprocesar');
            if (data.code === 'csrf_invalid') {
                setTimeout(() => window.location.reload(), 1500);
            }
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'Error de red al reprocesar.');
    }
}

async function listarWebhooksOpenPay() {
    const box = document.getElementById('openpayWebhooksList');
    if (box) box.innerHTML = '<span class="text-muted">Cargando…</span>';
    try {
        const fd = new FormData();
        fd.append('action', 'openpay_listar');
        const res = await webhookFetch(fd);
        const data = await webhookParseJson(res);
        if (!data.success) {
            if (box) box.innerHTML = `<span class="text-danger">${adminEscapeHtml(data.message || 'Error')}</span>`;
            return;
        }
        const list = Array.isArray(data.data) ? data.data : [];
        if (!list.length) {
            if (box) box.innerHTML = '<span class="text-muted">No hay webhooks registrados en OpenPay.</span>';
            return;
        }
        if (box) {
            box.innerHTML = list.map(h => `
                <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="font-monospace small">${adminEscapeHtml(h.id || '—')}</div>
                        <div class="small text-break">${adminEscapeHtml(h.url || '')}</div>
                        <div class="mt-1">
                            <span class="badge ${h.status === 'verified' ? 'bg-success' : 'bg-secondary'}">${adminEscapeHtml(h.status || '—')}</span>
                        </div>
                        <div class="small text-muted mt-1">${(h.event_types || []).map(e => `<code class="me-1">${adminEscapeHtml(e)}</code>`).join('')}</div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick='eliminarWebhookOpenPay(${JSON.stringify(h.id || '')})'>
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            `).join('');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'Error de red al listar webhooks OpenPay.');
        if (box) box.innerHTML = '<span class="text-danger">Error de red</span>';
    }
}

async function registrarWebhookOpenPay() {
    if (!confirm('¿Registrar webhook en OpenPay con la URL y eventos estándar del sistema?')) return;
    const token = adminCsrfToken();
    if (!token) {
        alertify.error('Sesión sin token CSRF. Recarga la página (F5).');
        return;
    }
    try {
        const fd = webhookCsrf(new FormData());
        fd.append('action', 'openpay_registrar');
        const res = await webhookFetch(fd);
        const data = await webhookParseJson(res);
        if (data.success) {
            alertify.success(data.message || 'Webhook registrado');
            listarWebhooksOpenPay();
        } else {
            alertify.error(data.message || 'No se pudo registrar');
            if (data.code === 'csrf_invalid') {
                setTimeout(() => window.location.reload(), 1500);
            }
        }
    } catch (e) {
        alertify.error('Error de red');
    }
}

async function eliminarWebhookOpenPay(id) {
    if (!id || !confirm('¿Eliminar este webhook en OpenPay?')) return;
    try {
        const fd = webhookCsrf(new FormData());
        fd.append('action', 'openpay_eliminar');
        fd.append('webhook_id', id);
        const res = await webhookFetch(fd);
        const data = await webhookParseJson(res);
        if (data.success) {
            alertify.success('Eliminado');
            listarWebhooksOpenPay();
        } else {
            alertify.error(data.message || 'Error');
            if (data.code === 'csrf_invalid') {
                setTimeout(() => window.location.reload(), 1500);
            }
        }
    } catch (e) {
        alertify.error('Error de red');
    }
}
