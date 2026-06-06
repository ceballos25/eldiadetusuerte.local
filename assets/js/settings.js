let SETTINGS = {};
let SETTINGS_ROWS = [];
let SETTINGS_LOADED = false;
let SETTINGS_PROMISE = null;

const SETTING_UI_META = {
    maintenance_mode: {
        title: 'Modo mantenimiento',
        help: 'Actívalo cuando quieras cerrar la página al público por un rato. Tú y tu equipo podéis seguir entrando con normalidad.',
        type: 'toggle',
        section: 'web',
    },
    maintenance_message: {
        title: 'Mensaje para visitantes',
        help: 'Lo que verán quienes entren mientras el sitio está en mantenimiento.',
        placeholder: 'Ej: Estamos actualizando la página. Volvemos pronto.',
        type: 'textarea',
        section: 'web',
    },
    web_compras_habilitadas: {
        title: 'Permitir compras en la web',
        help: 'Encendido: pueden pagar en línea y subir comprobante. Apagado: solo ven información y consultan nros, sin comprar.',
        type: 'toggle',
        section: 'web',
    },
    web_mensaje_compras_bloqueadas: {
        title: 'Aviso cuando las compras están pausadas',
        help: 'Texto de la franja roja en la web. Si lo dejas vacío, usamos un mensaje genérico.',
        placeholder: 'Ej: Las compras en línea están pausadas por ahora.',
        type: 'textarea',
        section: 'web',
    },
    web_id_raffle: {
        title: 'Rifa que se muestra en la página principal',
        help: 'Se actualiza solo al crear o activar una rifa. Si pausas la rifa actual, pasa a la última rifa activa. El ID lo ves en Rifas.',
        placeholder: 'Ej: 1',
        type: 'text',
        section: 'web',
    },
    barra: {
        title: 'Ajuste de la barra de progreso',
        help: 'Déjalo en 0 para mostrar el avance real. Solo cámbialo si necesitas sumar un poco al porcentaje visible (máx. 100). Recarga la web para verlo.',
        placeholder: '0',
        type: 'text',
        section: 'web',
    },
    whatsapp: {
        title: 'WhatsApp (celular)',
        help: 'Solo dígitos, con indicativo. Ejemplo Colombia: 573001234567',
        placeholder: '573001234567',
        type: 'text',
        section: 'contacto',
    },
    whatsapp_chat_url: {
        title: 'Enlace de WhatsApp (opcional)',
        help: 'Si tienes un enlace wa.me completo, pégalo aquí. Tiene prioridad sobre el celular de arriba.',
        placeholder: 'https://wa.me/573001234567',
        type: 'text',
        section: 'contacto',
    },
    social_instagram_url: {
        title: 'Instagram',
        help: 'Enlace a tu perfil o página de Instagram.',
        placeholder: 'https://instagram.com/tu_cuenta',
        type: 'text',
        section: 'contacto',
    },
    social_facebook_url: {
        title: 'Facebook',
        help: 'Enlace a tu página de Facebook.',
        placeholder: 'https://facebook.com/tu_pagina',
        type: 'text',
        section: 'contacto',
    },
    numeros_bendecidos: {
        title: 'Nros bendecidos en la web',
        help: 'Lista separada por comas, si aún usas esta función. Si los premios los marcas en los tickets, puede quedar vacío.',
        placeholder: '12, 45, 108',
        type: 'text',
        section: 'otros',
    },
    pricing_tiered_enabled: {
        title: 'Precios por tramos (web)',
        help: 'Encendido: 1.º nro, 2.º nro y del 3.º en adelante con precios distintos.',
        type: 'toggle',
        section: 'precios',
    },
    pricing_first_unit: {
        title: 'Precio 1.º nro (COP)',
        help: 'Valor del primer nro. Ej: 1200',
        placeholder: '1200',
        type: 'text',
        section: 'precios',
    },
    pricing_tier1_qty: {
        title: 'Cantidad precio normal (legacy)',
        help: 'Obsoleto: ya no se usa. Precios por posición (1.º, 2.º, 3+).',
        placeholder: '2',
        type: 'text',
        section: 'precios',
    },
    pricing_tier1_unit: {
        title: 'Precio 2.º nro (COP)',
        help: 'Valor del segundo nro. Ej: 1200',
        placeholder: '1200',
        type: 'text',
        section: 'precios',
    },
    pricing_tier2_unit: {
        title: 'Precio desde umbral bulk (COP)',
        help: 'Valor por nro desde el umbral bulk. Ej: 1000',
        placeholder: '1000',
        type: 'text',
        section: 'precios',
    },
    pricing_bulk_threshold: {
        title: 'Umbral descuento bulk',
        help: 'Desde esta cantidad cada nro usa pricing_tier2_unit. Ej: 40',
        placeholder: '40',
        type: 'text',
        section: 'precios',
    },
};

const SETTING_SECTIONS = [
    {
        id: 'web',
        title: 'Página web y ventas',
        subtitle: 'Qué ven los visitantes y si pueden comprar en línea.',
    },
    {
        id: 'precios',
        title: 'Precios por cantidad',
        subtitle: '1.º, 2.º y del 3.º en adelante (web y checkout).',
    },
    {
        id: 'contacto',
        title: 'Contacto y redes',
        subtitle: 'WhatsApp e iconos de redes en la página principal.',
    },
    {
        id: 'otros',
        title: 'Otros ajustes',
        subtitle: 'Opciones adicionales. Si no estás seguro, déjalas como están.',
    },
];

const SETTING_UI_ORDER = [
    'maintenance_mode',
    'maintenance_message',
    'web_compras_habilitadas',
    'web_mensaje_compras_bloqueadas',
    'web_id_raffle',
    'barra',
    'pricing_tiered_enabled',
    'pricing_first_unit',
    'pricing_tier1_unit',
    'pricing_tier2_unit',
    'whatsapp',
    'whatsapp_chat_url',
    'social_instagram_url',
    'social_facebook_url',
    'numeros_bendecidos',
];

document.addEventListener('DOMContentLoaded', async () => {
    initSettingsAlerts();

    if (document.getElementById('settingsContainer')) {
        await cargarSettingsGlobal();
        renderSettingsFromGlobal();
    }
});

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function initSettingsAlerts() {
    if (typeof alertify === 'undefined') return;
    alertify.set('notifier', 'position', 'top-center');
    alertify.set('notifier', 'delay', 5);
}

function settingLabelForKey(key) {
    return metaForKey(key).title || key;
}

function showSettingsToast(type, message) {
    initSettingsAlerts();
    const msg = String(message ?? '').trim();
    if (!msg) return;

    if (typeof alertify !== 'undefined') {
        if (typeof alertify.dismissAll === 'function') {
            alertify.dismissAll();
        }
        if (type === 'error') {
            alertify.error(msg);
        } else {
            alertify.success(msg);
        }
        return;
    }

    if (type === 'error' && typeof toastError === 'function') {
        toastError(msg);
        return;
    }
    if (type !== 'error' && typeof toastSuccess === 'function') {
        toastSuccess(msg);
    }
}

function notifySettingSaved(label) {
    const name = String(label ?? '').trim();
    const msg = name ? `✓ Guardado: ${name}` : '✓ Cambio guardado correctamente';
    showSettingsToast('success', msg);
}

function notifySettingError(message) {
    const msg = String(message ?? '').trim() || 'No se pudo guardar. Revisa los datos e intenta de nuevo.';
    showSettingsToast('error', `✕ ${msg}`);
}

function flashSettingRow(key) {
    const row = document.querySelector(`.settings-row[data-setting-key="${CSS.escape(String(key))}"]`);
    if (!row) return;
    row.classList.remove('settings-row--saved');
    void row.offsetWidth;
    row.classList.add('settings-row--saved');
    window.setTimeout(() => row.classList.remove('settings-row--saved'), 1800);
}

function getSetting(key, defaultValue = null) {
    return SETTINGS[key] ?? defaultValue;
}

function settingsCsrfToken() {
    if (typeof adminCsrfToken === 'function') {
        return adminCsrfToken();
    }
    if (window.PUBLIC_CSRF_TOKEN) {
        return window.PUBLIC_CSRF_TOKEN;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
}

function settingsAppendCsrf(fd) {
    if (fd instanceof FormData && !fd.has('csrf_token')) {
        const token = settingsCsrfToken();
        if (token) {
            fd.append('csrf_token', token);
        }
    }
    return fd;
}

async function fetchSettings(action, extra = {}) {

    const fd = new FormData();
    fd.append('action', action);

    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));

    const writeActions = ['actualizar', 'crear', 'eliminar'];
    if (writeActions.includes(action)) {
        settingsAppendCsrf(fd);
    }

    const token = settingsCsrfToken();
    const res = await fetch('/front/ajax/settings.ajax.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: token ? { 'X-CSRF-Token': token } : {},
    });

    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text.trim());
    } catch {
        throw new Error('El servidor respondió de forma inesperada. Recarga la página.');
    }

    if (!data.success) {
        if (data.code === 'csrf_invalid') {
            throw new Error(data.message || 'Sesión expirada. Recarga la página (F5) e intenta de nuevo.');
        }
        throw new Error(data.message || 'No se pudo completar la operación.');
    }

    return data;
}

async function cargarSettingsGlobal(force = false) {

    if (force) {
        SETTINGS_LOADED = false;
        SETTINGS_PROMISE = null;
    }

    if (SETTINGS_LOADED) return SETTINGS;

    if (SETTINGS_PROMISE) return SETTINGS_PROMISE;

    SETTINGS_PROMISE = (async () => {

        try {

            const data = await fetchSettings('obtener');

            SETTINGS = {};
            SETTINGS_ROWS = Array.isArray(data.data) ? data.data : [];

            SETTINGS_ROWS.forEach(s => {
                SETTINGS[s.key_setting] = s.value_setting;
            });

            SETTINGS_LOADED = true;

            return SETTINGS;

        } catch (e) {
            if (typeof notifySettingError === 'function') {
                notifySettingError(e instanceof Error ? e.message : 'Error cargando ajustes');
            } else if (typeof adminNotifyError === 'function') {
                adminNotifyError(e instanceof Error ? e.message : 'Error cargando ajustes');
            }
            return {};
        }

    })();

    return SETTINGS_PROMISE;
}

function isSettingTruthyValue(val) {
    const v = String(val ?? '').trim().toLowerCase();
    return !['0', 'false', 'no', 'off'].includes(v);
}

function onSettingsReady(callback) {
    cargarSettingsGlobal().then(callback);
}

function metaForKey(key) {
    return (
        SETTING_UI_META[key] || {
            title: key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
            help: 'Opción personalizada. Si no la reconoces, consulta antes de cambiarla.',
            type: 'text',
            section: 'avanzado',
        }
    );
}

function isKnownSettingKey(key) {
    return Object.prototype.hasOwnProperty.call(SETTING_UI_META, key);
}

function renderSettingControl(key, meta, val) {
    if (meta.type === 'toggle') {
        const checked = isSettingTruthyValue(val) ? 'checked' : '';
        return `
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="toggle-${escapeHtml(key)}"
                    data-key="${escapeHtml(key)}"
                    data-boolean-toggle="1"
                    ${checked}
                    onchange="guardarToggleSetting('${escapeHtml(key)}', this)">
                <label class="form-check-label small" for="toggle-${escapeHtml(key)}">
                    <span class="text-muted toggle-state-label" data-for-key="${escapeHtml(key)}"></span>
                </label>
            </div>
        `;
    }
    if (meta.type === 'textarea') {
        const ph = meta.placeholder ? ` placeholder="${escapeHtml(meta.placeholder)}"` : '';
        return `
            <textarea class="form-control form-control-sm" rows="2" data-key="${escapeHtml(key)}"${ph}>${escapeHtml(val)}</textarea>
        `;
    }
    const ph = meta.placeholder ? ` placeholder="${escapeHtml(meta.placeholder)}"` : '';
    return `
        <input type="text" class="form-control form-control-sm" data-key="${escapeHtml(key)}"
            value="${escapeHtml(val)}"${ph}>
    `;
}

function renderSettingRow(s, options = {}) {
    const { showDelete = false } = options;
    const key = s.key_setting;
    const meta = metaForKey(key);
    const val = s.value_setting ?? '';
    const id = s.id_setting;
    const showSave = meta.type !== 'toggle';
    const known = isKnownSettingKey(key);

    return `
    <div class="settings-row row g-3 align-items-start" data-setting-key="${escapeHtml(key)}">
        <div class="col-lg-4">
            <div class="settings-title">${escapeHtml(meta.title)}</div>
            ${known ? '' : `<div class="settings-code text-muted small mb-1"><span class="badge bg-light text-muted border fw-normal">${escapeHtml(key)}</span></div>`}
            <p class="settings-help mb-0">${escapeHtml(meta.help)}</p>
        </div>
        <div class="col-lg-5">
            ${renderSettingControl(key, meta, val)}
        </div>
        <div class="col-lg-3 text-lg-end">
            ${showSave ? `
                <button type="button" class="btn btn-sm btn-success btn-save-row"
                    onclick="guardarIndividualDesdeUI('${escapeHtml(key)}', this)">
                    <i class="ti ti-device-floppy me-1"></i> Guardar
                </button>
            ` : `
                <span class="small text-success"><i class="ti ti-bolt me-1"></i>Guardado automático</span>
            `}
            ${showDelete ? `
                <button type="button" class="btn btn-sm btn-outline-danger ms-lg-2 mt-2 mt-lg-0 d-inline-block"
                    title="Eliminar"
                    onclick="eliminarSetting(${Number(id)})"
                    ${['precio_ticket', 'max_tickets', 'min_tickets'].includes(key) ? 'disabled' : ''}>
                    <i class="ti ti-trash"></i>
                </button>
            ` : ''}
        </div>
    </div>
    `;
}

function renderSettingsSectionHeader(section) {
    return `
    <div class="settings-section-header">
        <h6 class="settings-section-title mb-0">${escapeHtml(section.title)}</h6>
        <p class="settings-section-subtitle mb-0">${escapeHtml(section.subtitle)}</p>
    </div>`;
}

function sortSettingsRows(rows) {
    const seen = new Set();
    const ordered = [];

    SETTING_UI_ORDER.forEach((k) => {
        const row = rows.find((r) => r.key_setting === k);
        if (row) {
            ordered.push(row);
            seen.add(k);
        }
    });

    const rest = rows
        .filter((r) => !seen.has(r.key_setting))
        .sort((a, b) => String(a.key_setting).localeCompare(String(b.key_setting)));

    return ordered.concat(rest);
}

function renderSettingsFromGlobal() {

    const container = document.getElementById('settingsContainer');
    if (!container) return;

    const rows = sortSettingsRows([...SETTINGS_ROWS]);
    const rowsByKey = Object.fromEntries(rows.map((r) => [r.key_setting, r]));

    if (!rows.length) {
        container.innerHTML = `<div class="text-center py-5 text-muted">No hay opciones configuradas todavía.</div>`;
        return;
    }

    let html = '';

    SETTING_SECTIONS.forEach((section) => {
        const sectionRows = SETTING_UI_ORDER
            .filter((k) => (SETTING_UI_META[k]?.section || '') === section.id)
            .map((k) => rowsByKey[k])
            .filter(Boolean);

        if (!sectionRows.length) return;

        html += renderSettingsSectionHeader(section);
        html += sectionRows.map((s) => renderSettingRow(s)).join('');
    });

    const knownSet = new Set(SETTING_UI_ORDER);
    const extraRows = rows.filter((r) => !knownSet.has(r.key_setting));
    if (extraRows.length) {
        html += renderSettingsSectionHeader({
            title: 'Opciones adicionales',
            subtitle: 'Parámetros personalizados. Elimínalos solo si sabes para qué sirven.',
        });
        html += extraRows.map((s) => renderSettingRow(s, { showDelete: true })).join('');
    }

    container.innerHTML = html;

    rows.forEach((s) => {
        if (metaForKey(s.key_setting).type === 'toggle') {
            updateToggleLabel(s.key_setting);
        }
    });
}

function updateToggleLabel(key) {
    const el = document.querySelector(`.toggle-state-label[data-for-key="${key}"]`);
    const input = document.getElementById(`toggle-${key}`);
    if (!el || !input) return;
    const on = input.checked;
    if (key === 'maintenance_mode') {
        el.textContent = on ? 'Sitio cerrado al público' : 'Sitio abierto al público';
    } else if (key === 'web_compras_habilitadas') {
        el.textContent = on ? 'Compras activas' : 'Compras pausadas';
    } else {
        el.textContent = on ? 'Encendido' : 'Apagado';
    }
}

function setSaveButtonLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.dataset.prevHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando…';
    } else {
        btn.disabled = false;
        if (btn.dataset.prevHtml) {
            btn.innerHTML = btn.dataset.prevHtml;
            delete btn.dataset.prevHtml;
        }
    }
}

async function guardarToggleSetting(key, inputEl) {
    const v = inputEl.checked ? '1' : '0';
    const label = settingLabelForKey(key);
    updateToggleLabel(key);
    inputEl.disabled = true;
    try {
        await actualizarSettings({ [key]: v });
        notifySettingSaved(label);
        flashSettingRow(key);
    } catch (e) {
        notifySettingError(e.message);
        inputEl.checked = !inputEl.checked;
        updateToggleLabel(key);
    } finally {
        inputEl.disabled = false;
    }
}

async function actualizarSettings(payload) {

    await fetchSettings('actualizar', payload);
    await cargarSettingsGlobal(true);

    return true;
}

function syncSettingValueInDom(key, value) {
    const row = document.querySelector(`.settings-row[data-setting-key="${CSS.escape(String(key))}"]`);
    if (!row) return;
    const input = row.querySelector('[data-key]');
    if (!input) return;
    if (input.type === 'checkbox') {
        input.checked = isSettingTruthyValue(value);
        updateToggleLabel(key);
    } else if (input.tagName === 'TEXTAREA') {
        input.value = value ?? '';
    } else {
        input.value = value ?? '';
    }
}

async function guardarIndividualDesdeUI(key, btn) {

    const row = btn.closest('.settings-row');
    const input = row && row.querySelector('[data-key]');
    if (!input) return;

    const value = input.type === 'checkbox'
        ? (input.checked ? '1' : '0')
        : input.value.trim();

    setSaveButtonLoading(btn, true);

    try {

        await actualizarSettings({ [key]: value });

        syncSettingValueInDom(key, value);
        notifySettingSaved(settingLabelForKey(key));
        flashSettingRow(key);

    } catch (e) {
        notifySettingError(e.message || e);
    } finally {
        setSaveButtonLoading(btn, false);
    }
}

async function guardarSettings() {

    const btn = document.querySelector('button[onclick="guardarSettings()"]');
    const inputs = document.querySelectorAll('[data-key]');
    const payload = {};

    inputs.forEach(i => {
        if (i.type === 'checkbox') {
            payload[i.dataset.key] = i.checked ? '1' : '0';
        } else {
            payload[i.dataset.key] = i.value.trim();
        }
    });

    setSaveButtonLoading(btn, true);

    try {

        await actualizarSettings(payload);

        notifySettingSaved('Todos los ajustes');
        if (document.getElementById('settingsContainer')) {
            renderSettingsFromGlobal();
        }

    } catch (e) {
        notifySettingError(e.message);
    } finally {
        setSaveButtonLoading(btn, false);
    }
}

async function crearSetting() {

    let key = document.getElementById('newKey').value.trim();
    const value = document.getElementById('newValue').value.trim();

    if (!key) {
        notifySettingError('Escribe un nombre para la opción');
        return;
    }

    key = key.toLowerCase().replace(/\s+/g, '_');

    try {

        await fetchSettings('crear', {
            key_setting: key,
            value_setting: value
        });

        notifySettingSaved('Nueva opción');

        document.getElementById('newKey').value = '';
        document.getElementById('newValue').value = '';

        await cargarSettingsGlobal(true);
        renderSettingsFromGlobal();

    } catch (e) {
        notifySettingError(e.message);
    }
}

async function eliminarSetting(id) {

    if (!confirm('¿Eliminar esta opción?')) return;

    try {

        await fetchSettings('eliminar', { id_setting: id });

        notifySettingSaved('Opción eliminada');

        await cargarSettingsGlobal(true);
        renderSettingsFromGlobal();

    } catch (e) {
        notifySettingError(e.message || 'Error al eliminar');
    }
}

window.getSetting = getSetting;
window.onSettingsReady = onSettingsReady;
window.cargarSettingsGlobal = cargarSettingsGlobal;

function seedPublicSettings(map) {
    if (!map || typeof map !== 'object') {
        return;
    }
    Object.assign(SETTINGS, map);
    SETTINGS_LOADED = true;
    SETTINGS_PROMISE = Promise.resolve(SETTINGS);
}

window.seedPublicSettings = seedPublicSettings;
window.guardarToggleSetting = guardarToggleSetting;
window.guardarIndividualDesdeUI = guardarIndividualDesdeUI;
window.guardarSettings = guardarSettings;
window.crearSetting = crearSetting;
window.eliminarSetting = eliminarSetting;

function redondearPorcentajeUIPct(n, decimales = 2) {
    if (!Number.isFinite(n)) {
        return 0;
    }
    return parseFloat(n.toFixed(decimales));
}

function obtenerPorcentajeFinal(porcentajeBackend) {

    const real = Number(porcentajeBackend);
    const base = Number.isFinite(real) ? real : 0;

    const raw = getBarraBoostRaw();

    if (raw === null || raw === undefined) {
        return base;
    }

    const trimmed = String(raw).trim();
    if (trimmed === '') {
        return base;
    }

    const barraSetting = parseFloat(trimmed.replace(',', '.'));

    if (!Number.isFinite(barraSetting) || barraSetting <= 0) {
        return base;
    }

    return base + barraSetting;
}

function getBarraBoostRaw() {
    if (typeof getSetting !== 'function') {
        return null;
    }
    const direct = getSetting('barra');
    if (direct !== null && direct !== undefined && String(direct).trim() !== '') {
        return direct;
    }
    if (typeof SETTINGS === 'object' && SETTINGS !== null) {
        const keys = Object.keys(SETTINGS);
        const found = keys.find((k) => k.toLowerCase() === 'barra');
        if (found !== undefined && SETTINGS[found] !== undefined && SETTINGS[found] !== null) {
            return SETTINGS[found];
        }
    }
    return null;
}

function actualizarBarraProgreso(porcentajeBackend) {
    const porcentaje = obtenerPorcentajeFinal(porcentajeBackend);

    const clamped = Math.min(Math.max(porcentaje, 0), 100);
    const porcentajeFinal = redondearPorcentajeUIPct(clamped, 2);

    const texto = document.getElementById('porcentajeTexto');
    if (texto) {
        texto.innerText = porcentajeFinal + '%';
    }

    const barra = document.getElementById('barraProgreso');
    if (barra) {
        barra.style.width = porcentajeFinal + '%';
    }

    const barraCarrera = document.getElementById('barraProgresoCarrera');
    if (barraCarrera) {
        barraCarrera.style.width = porcentajeFinal + '%';
    }

    const caballo = document.getElementById('caballoProgreso');
    if (caballo) {
        caballo.style.left = porcentajeFinal + '%';
    }
}

let porcentajeBackendGlobal = 0;

function cargarDatosRifa(data) {
    porcentajeBackendGlobal = Number(data?.porcentaje) || 0;

    onSettingsReady(() => {
        actualizarBarraProgreso(porcentajeBackendGlobal);
    });
}

async function initBarraProgreso() {

    await cargarSettingsGlobal();

    const porcentajeBackend = 25;

    actualizarBarraProgreso(porcentajeBackend);
}
