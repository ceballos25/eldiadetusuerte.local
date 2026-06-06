const ENDPOINT = '/front/ajax/visual.ajax.php';
const KNOWN_KEYS = {
    logo: 'Logo principal',
    logo_white: 'Logo panel admin (blanco)',
    favicon: 'Favicon',
    pse: 'Logo PSE',
    hero_banner: 'Banner principal',
    hero_mobile: 'Banner móvil',
    section_info_1: 'Sección informativa 1',
    section_info_2: 'Sección informativa 2',
    fallback_image: 'Imagen de respaldo global',
};

document.addEventListener('DOMContentLoaded', () => cargarVisual());

async function cargarVisual() {
    try {
        const fd = new FormData();
        fd.append('action', 'listar');
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        const existing = {};
        (data.data || []).forEach(row => { existing[row.key_image] = row; });
        const keys = data.keys || KNOWN_KEYS;

        document.getElementById('visualContainer').innerHTML = Object.entries(keys).map(([key, label]) => {
            const row = existing[key] || {};
            const url = row.url_image || '';
            const fb = row.fallback_url || '';
            const safeKey = String(key).replace(/'/g, "\\'");
            return `<div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold">${label}</h6>
            <small class="text-muted d-block mb-2">${key}</small>
            <input type="url" class="form-control form-control-sm mb-2 visual-url" data-key="${key}" placeholder="https://..." value="${url}">
            <input type="url" class="form-control form-control-sm mb-2 visual-fb" data-key="${key}" placeholder="URL respaldo (opcional)" value="${fb}">
            ${url ? `<img src="${url}" class="img-fluid rounded mt-2 visual-preview" data-key="${key}" style="max-height:120px" onerror="this.style.display='none'">` : ''}
            <button class="btn btn-sm btn-primary mt-2" onclick="guardarImagen('${safeKey}')">Guardar</button>
        </div></div></div>`;
        }).join('');
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar la configuración visual.');
    }
}

async function guardarImagen(key) {
    const url = document.querySelector(`.visual-url[data-key="${key}"]`)?.value.trim();
    const fb = document.querySelector(`.visual-fb[data-key="${key}"]`)?.value.trim();
    const fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('csrf_token', window.APP_CSRF_TOKEN || '');
    fd.append('key_image', key);
    fd.append('url_image', url);
    fd.append('fallback_url', fb);
    try {
        const data = await adminFetchJson(ENDPOINT, { body: fd });
        if (data.success) { alertify.success('Imagen guardada'); cargarVisual(); }
        else alertify.error(data.message || 'Error');
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo guardar la imagen.');
    }
}
