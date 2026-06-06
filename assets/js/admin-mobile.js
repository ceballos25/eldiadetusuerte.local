function adminPaymentMethodBadgeClass(paymentMethod) {
    const method = String(paymentMethod ?? '').trim();
    if (method === 'Venta Manual') {
        return 'bg-success';
    }
    if (method === 'Transferencia') {
        return 'bg-info';
    }
    if (method === 'Pagina Web' || method === 'Página Web') {
        return 'bg-primary';
    }
    return 'bg-secondary';
}

function adminEscapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function adminFormatDateColombia(raw) {
    if (!raw) return { fecha: '—', hora: '' };
    const f = new Date(String(raw).replace(' ', 'T') + '-05:00');
    const opts = { timeZone: 'America/Bogota' };
    return {
        fecha: f.toLocaleDateString('es-CO', opts),
        hora: f.toLocaleTimeString('es-CO', {
            ...opts,
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
    };
}

function adminParseCOP(value) {
    if (typeof parsePrecioCOP === 'function') {
        return parsePrecioCOP(value);
    }
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.round(value);
    }
    const s = String(value).trim();
    if (!s) return 0;
    if (/^\d+$/.test(s)) return parseInt(s, 10) || 0;
    if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
        return parseInt(s.replace(/\./g, ''), 10) || 0;
    }
    if (/^\d+[.,]\d{1,2}$/.test(s)) {
        return Math.round(parseFloat(s.replace(',', '.')));
    }
    const digits = s.replace(/\D/g, '');
    return digits ? (parseInt(digits, 10) || 0) : 0;
}

function adminFormatCOP(value) {
    if (typeof formatPrecioCOP === 'function') {
        return formatPrecioCOP(value);
    }
    const n = adminParseCOP(value);
    if (!n) return '';
    return n.toLocaleString('es-CO', { maximumFractionDigits: 0, minimumFractionDigits: 0 });
}

function adminBindCOPInput(el) {
    if (!el || el.dataset.copBound) return;
    el.dataset.copBound = '1';

    const applyFormat = () => {
        const digits = el.value.replace(/\D/g, '');
        el.value = digits ? adminFormatCOP(parseInt(digits, 10)) : '';
        try {
            el.setSelectionRange(el.value.length, el.value.length);
        } catch (_) {  }
    };

    el.addEventListener('input', applyFormat);
    el.addEventListener('blur', () => {
        if (el.value.trim()) el.value = adminFormatCOP(adminParseCOP(el.value));
    });
}

function adminInitial(name) {
    const n = String(name ?? '').trim();
    return n ? n.charAt(0).toUpperCase() : '?';
}

function adminAvatarCard(initial) {
    return `<div class="admin-card-avatar">${adminEscapeHtml(initial)}</div>`;
}

function adminWhatsAppLink(phone) {
    const raw = String(phone ?? '').trim();
    if (!raw) return '<span class="text-muted small">--</span>';
    const digits = raw.replace(/\D/g, '');
    const wa = digits.startsWith('57') ? digits : '57' + digits;
    return `<a href="https://api.whatsapp.com/send?phone=${wa}" target="_blank" rel="noopener noreferrer"
        class="admin-card-phone admin-phone-link text-decoration-none">
        <i class="ti ti-brand-whatsapp admin-icon-whatsapp"></i><span>${adminEscapeHtml(raw)}</span>
    </a>`;
}

function adminVendedorOrigenLines(vendedor, origen) {
    const v = adminEscapeHtml(vendedor || 'Sistema');
    const o = adminEscapeHtml(origen || '—');
    return `<div class="admin-card-extra admin-seller-line">
        <span><i class="ti ti-user text-secondary"></i> Vendedor: <span class="admin-seller-name">${v}</span></span>
    </div>
    <div class="admin-card-extra admin-origin-line">
        <span><i class="ti ti-world text-secondary"></i> Origen: <span class="admin-origin-name">${o}</span></span>
    </div>`;
}

function adminTokenChip(code) {
    return `<span class="token-chip" title="${adminEscapeHtml(code)}">${adminEscapeHtml(code)}</span>`;
}

function adminTokenChipBlock(code) {
    return `<span class="token-chip token-chip-block" title="${adminEscapeHtml(code)}">${adminEscapeHtml(code)}</span>`;
}

function adminCityLine(city, inline) {
    if (!city) return '';
    const cls = inline
        ? 'admin-city-line admin-city-line--inline text-muted small'
        : 'admin-card-extra admin-city-line text-muted small';
    return `<span class="${cls}">
        <i class="ti ti-map-pin text-secondary"></i> ${adminEscapeHtml(city)}
    </span>`;
}

function adminNombreCompleto(record) {
    return `${record.name_customer || ''} ${record.lastname_customer || ''}`.trim() || '—';
}

function adminClientMobileHead({ initial, name, phone, extraHtml }) {
    return `
    <div class="admin-card-head__client d-flex align-items-center gap-2 min-w-0">
        ${adminAvatarCard(initial)}
        <div class="admin-card-client-info min-w-0 flex-grow-1">
            <span class="admin-card-name"><span class="cell-truncate" title="${adminEscapeHtml(name)}">${adminEscapeHtml(name)}</span></span>
            ${adminWhatsAppLink(phone)}
            ${extraHtml || ''}
        </div>
    </div>`;
}

function adminClientDesktopHead({ initial, name, phone, city, linesHtml }) {
    const cityHtml = city ? adminCityLine(city, true) : '';
    return `
    <div class="d-none d-lg-flex align-items-start gap-3 admin-desktop-client">
        ${adminAvatarCard(initial)}
        <div class="admin-desktop-client__info">
            <div class="admin-client-name text-capitalize">
                <span class="cell-truncate" title="${adminEscapeHtml(name)}">${adminEscapeHtml(name)}</span>
            </div>
            <div class="admin-client-contact">
                ${adminWhatsAppLink(phone)}
                ${cityHtml}
            </div>
            ${linesHtml || ''}
        </div>
    </div>`;
}

function adminFechaRowMobile(fecha, hora) {
    return `
    <div class="d-flex flex-column admin-fecha-block">
        <span class="admin-fecha-dia">${adminEscapeHtml(fecha)}</span>
        ${hora ? `<span class="admin-fecha-hora">${adminEscapeHtml(hora)}</span>` : ''}
    </div>`;
}

function adminFechaCompact(fecha, hora) {
    const h = hora ? ` · ${adminEscapeHtml(hora)}` : '';
    return `<span class="admin-fecha-compact">${adminEscapeHtml(fecha)}${h}</span>`;
}

function adminClientDesktopCompact({ initial, name, phone, sub }) {
    const subHtml = sub
        ? `<span class="admin-client-sub"> · ${adminEscapeHtml(sub)}</span>`
        : '';
    return `
    <div class="d-none d-lg-flex align-items-center gap-2 admin-client-compact min-w-0">
        <div class="admin-card-avatar admin-card-avatar--sm">${adminEscapeHtml(initial)}</div>
        <div class="min-w-0">
            <div class="admin-client-name text-truncate" title="${adminEscapeHtml(name)}">${adminEscapeHtml(name)}</div>
            <div class="admin-client-sub-line text-muted small">
                ${adminWhatsAppLink(phone)}${subHtml}
            </div>
        </div>
    </div>`;
}

function crNumeroChipInlineStyle(variant = 'selected', large = false) {
    const v = String(variant || 'selected');
    const isLarge = large || v === 'lg';
    let colorKey = isLarge ? 'selected' : (v === '' ? 'selected' : v.replace(/[^a-z]/g, ''));
    if (!colorKey) colorKey = 'selected';

    const palettes = {
        selected: { backgroundColor: '#ffffff', color: '#000000', border: '2px solid #0D0D0D' },
        libre: { backgroundColor: '#ffffff', color: '#0D0D0D', border: '2px solid #d0d0d0' },
        vendido: { backgroundColor: '#eeeeee', color: '#888888', border: '2px solid #cccccc' },
        reservado: { backgroundColor: '#ffffff', color: '#15803d', border: '2px dashed #16a34a' },
        premium: { backgroundColor: '#16a34a', color: '#ffffff', border: '2px solid #15803d' },
        recibo: { backgroundColor: '#ffffff', color: '#000000', border: '2px solid #0D0D0D' },
    };
    const colors = palettes[colorKey] || palettes.selected;

    const styles = {
        display: 'inline-block',
        'text-align': 'center',
        'vertical-align': 'middle',
        'font-family': "Montserrat, Arial, Helvetica, sans-serif",
        'font-weight': '700',
        'font-size': isLarge ? '17px' : '16px',
        'line-height': '1.2',
        'letter-spacing': '0.02em',
        'border-radius': '10px',
        padding: isLarge ? '0 12px' : '7px 10px',
        'min-width': isLarge ? '54px' : '46px',
        'min-height': isLarge ? '48px' : 'auto',
        margin: '0 6px 6px 0',
        'box-sizing': 'border-box',
        'text-decoration': 'none',
        'white-space': 'nowrap',
        ...colors,
    };

    return Object.entries(styles).map(([k, val]) => `${k}:${val}`).join(';');
}

function crNumeroChipHtml(number, variant = 'selected', large = false) {
    const v = String(variant || 'selected');
    const parts = ['cr-numero-chip'];
    if (large || v === 'lg') {
        parts.push('cr-numero-chip--lg');
    } else if (v !== 'selected') {
        parts.push(`cr-numero-chip--${v.replace(/[^a-z]/g, '')}`);
    }
    const style = crNumeroChipInlineStyle(variant, large);
    return `<span class="${parts.join(' ')}" style="${style}">${adminEscapeHtml(String(number ?? ''))}</span>`;
}

function crNumeroChipVariantForStatus(status, isPremium) {
    const s = Number(status);
    if (s === 1) return 'vendido';
    if (s === 2) return 'reservado';
    if (Number(isPremium) === 1) return 'premium';
    return 'libre';
}

function adminTicketBadge(number) {
    return crNumeroChipHtml(number);
}

function adminTicketBadgeFeatured(number) {
    return crNumeroChipHtml(number, 'selected', true);
}

/** Chip dorado compacto para listas admin (números vendidos, ventas). */
function adminNumeroVendidoBadge(number) {
    return `<span class="cr-numero-chip cr-numero-chip--selected admin-numero-vendido-chip">${adminEscapeHtml(String(number ?? ''))}</span>`;
}

/** Chip compacto con color según estado (gestión de números / inventario). */
function adminNumeroInventarioBadge(number, status, isPremium) {
    const variant = crNumeroChipVariantForStatus(status, isPremium);
    return `<span class="cr-numero-chip cr-numero-chip--${variant} admin-numero-inventario-chip">${adminEscapeHtml(String(number ?? ''))}</span>`;
}

function adminAvatar(initial) {
    return `<div class="rounded-circle bg-light border d-flex justify-content-center align-items-center text-secondary fw-bold me-3 flex-shrink-0"
         style="width: 40px; height: 40px; font-size: 1rem;">${adminEscapeHtml(initial)}</div>`;
}

function adminClientBlock({ initial, name, phone, city, subHtml, subTitle }) {
    return `
    <div class="d-flex align-items-center">
        ${adminAvatar(initial)}
        <div class="d-flex flex-column admin-cell-client__body" style="line-height: 1.3;">
            <span class="fw-bold text-dark text-capitalize admin-cell-client__name" title="${adminEscapeHtml(name)}">${adminEscapeHtml(name)}</span>
            <div class="admin-cell-client__meta text-muted small mt-1">
                <span class="admin-cell-client__meta-item"><i class="ti ti-phone text-secondary"></i> ${adminEscapeHtml(phone || '--')}</span>
                <span class="admin-cell-client__meta-item"><i class="ti ti-map-pin text-secondary"></i> ${adminEscapeHtml(city || 'N/A')}</span>
            </div>
            ${subHtml ? `<small class="text-muted fst-italic admin-cell-client__sub" style="font-size: 0.75rem;" title="${adminEscapeHtml(subTitle || '')}">${subHtml}</small>` : ''}
        </div>
    </div>`;
}

function adminCodeBlock(code, sub) {
    return `
    <div class="d-flex flex-column">
        <span class="font-monospace bg-light text-secondary py-1 px-2 rounded border"
              style="font-size: 0.8rem; width: fit-content; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${adminEscapeHtml(code)}</span>
        ${sub ? `<span class="text-muted small fw-medium text-truncate d-block mt-1" style="max-width: 100%;">${adminEscapeHtml(sub)}</span>` : ''}
    </div>`;
}

function adminDateBlock(fecha, hora) {
    return `
    <div class="d-flex flex-column admin-fecha-desktop">
        <span class="admin-fecha-dia">${adminEscapeHtml(fecha)}</span>
        ${hora ? `<span class="admin-fecha-hora">${adminEscapeHtml(hora)}</span>` : ''}
    </div>`;
}

function adminIconBtn(onclick, icon, variant, title) {
    const map = {
        primary: 'btn-outline-primary',
        danger: 'btn-outline-danger',
        warning: 'btn-outline-warning',
    };
    const cls = map[variant] || map.primary;
    return `<button type="button" class="btn btn-icon btn-sm ${cls} border-0 rounded-circle shadow-sm"
        onclick="${onclick}" title="${adminEscapeHtml(title || '')}" style="width: 32px; height: 32px;">
        <i class="ti ${icon} fs-7"></i></button>`;
}

function adminIconActions(html) {
    return `<div class="d-inline-flex gap-1 flex-shrink-0">${html}</div>`;
}

function adminMobileRow(content) {
    return `<div class="admin-mobile-row">${content}</div>`;
}

function adminMobileSection(html, extraClass) {
    return `<div class="admin-mobile-row__section${extraClass ? ' ' + extraClass : ''}">${html}</div>`;
}

function adminMobileKpi(html) {
    return `<div class="admin-mobile-row__kpi">${html}</div>`;
}

function adminMobileBottom(leftHtml, rightHtml) {
    return `<div class="admin-mobile-row__bottom">
        <div class="min-w-0">${leftHtml}</div>
        ${rightHtml || ''}
    </div>`;
}

function adminSimpleMobileRow({ title, sub, fields, badgeHtml, actionsHtml }) {
    const fieldsHtml = (fields || []).map(f => `
        <div class="admin-mobile-row__field">
            <div class="text-muted small">${adminEscapeHtml(f.label)}</div>
            <div class="${f.bold ? 'fw-bold text-dark' : ''}">${f.html != null ? f.html : adminEscapeHtml(f.value)}</div>
        </div>`).join('');

    return adminMobileRow(`
        ${title ? adminMobileSection(`<div class="fw-bold text-dark">${adminEscapeHtml(title)}</div>${sub ? `<div class="text-muted small text-truncate">${adminEscapeHtml(sub)}</div>` : ''}`) : ''}
        ${fieldsHtml ? `<div class="admin-mobile-row__fields">${fieldsHtml}</div>` : ''}
        ${badgeHtml ? adminMobileKpi(badgeHtml) : ''}
        ${actionsHtml ? adminMobileBottom('', adminIconActions(actionsHtml)) : ''}
    `);
}

function renderAdminMobileRows(containerId, rowsHtml) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!rowsHtml || !rowsHtml.length) {
        el.innerHTML = '<div class="text-center text-muted py-4">Sin registros</div>';
        return;
    }
    el.innerHTML = rowsHtml.join('');
}

function renderAdminMobileCards(containerId, rowsHtml) {
    if (Array.isArray(rowsHtml) && rowsHtml.length && typeof rowsHtml[0] === 'string') {
        renderAdminMobileRows(containerId, rowsHtml);
        return;
    }
    renderAdminMobileRows(containerId, (rowsHtml || []).map(c => {
        if (typeof c === 'string') return c;
        return adminSimpleMobileRow({
            title: c.title,
            sub: c.lines ? c.lines.join(' · ') : '',
            actionsHtml: c.actionsHtml || '',
        });
    }));
}
