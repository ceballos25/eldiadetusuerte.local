const ENDPOINT = '/front/ajax/auditoria.ajax.php';
let paginaAuditoria = 1;

document.addEventListener('DOMContentLoaded', () => cargarAuditoria());

async function cargarAuditoria() {
    try {
        const fd = new FormData();
        fd.append('action', 'listar');
        fd.append('page', paginaAuditoria);
        fd.append('search_action', document.getElementById('filtroAccion')?.value || '');
        fd.append('date_from', document.getElementById('filtroDesde')?.value || '');
        fd.append('date_to', document.getElementById('filtroHasta')?.value || '');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (!data.success) {
            adminNotifyError(adminExtractMessage(data, 'No se pudo cargar la auditoría.'));
            return;
        }

        const rows = data.data || [];
        document.getElementById('infoAuditoria').textContent = `${data.total} registros`;

        if (!rows.length) {
            document.getElementById('bodyAuditoria').innerHTML = '<tr><td colspan="5" class="text-center py-3">Sin registros</td></tr>';
            if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('cardsAuditoria', []);
            return;
        }

        document.getElementById('bodyAuditoria').innerHTML = rows.map(r => renderAuditoriaDesktopRow(r)).join('');

        if (typeof renderAdminMobileRows === 'function') {
            renderAdminMobileRows('cardsAuditoria', rows.map(r => renderAuditoriaMobileRow(r)));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar la auditoría.');
    }
}

function auditoriaDatos(r) {
    const dt = adminFormatDateColombia(r.created_at);
    const detalle = esc(r.new_data || r.old_data || '');
    const entity = [r.entity_type, r.entity_id].filter(Boolean).join(' #');
    return { dt, detalle, entity };
}

function renderAuditoriaDesktopRow(r) {
    const d = auditoriaDatos(r);
    return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4 small">${adminDateBlock(d.dt.fecha, d.dt.hora)}</td>
            <td class="py-3">${adminEscapeHtml(r.email_admin || 'Sistema')}</td>
            <td class="py-3"><code>${adminEscapeHtml(r.action_audit)}</code></td>
            <td class="py-3">${adminEscapeHtml(d.entity || '—')}</td>
            <td class="py-3 small text-truncate" style="max-width:200px" title="${d.detalle}">${d.detalle || '—'}</td>
        </tr>`;
}

function renderAuditoriaMobileRow(r) {
    const d = auditoriaDatos(r);
    return adminMobileRow(
        adminMobileSection(adminClientBlock({
            initial: adminInitial(r.email_admin || 'S'),
            name: r.action_audit,
            phone: r.email_admin || 'Sistema',
            city: d.entity || '—',
        })) +
        (d.detalle ? adminMobileSection(`<div class="text-muted small text-truncate" title="${d.detalle}">${d.detalle}</div>`) : '') +
        adminMobileBottom(adminDateBlock(d.dt.fecha, d.dt.hora), '')
    );
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
