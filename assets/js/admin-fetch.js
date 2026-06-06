function adminExtractMessage(data, fallback) {
    if (data && typeof data.message === 'string' && data.message.trim()) {
        return data.message.trim();
    }
    return fallback || 'La operación no se completó.';
}

function adminNotifyError(mensaje, titulo) {
    const msg = String(mensaje || 'Ocurrió un error inesperado').trim() || 'Ocurrió un error inesperado';
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: titulo || 'Error', text: msg, confirmButtonText: 'Entendido' });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.error(msg, 8);
        return;
    }
    alert((titulo ? titulo + ': ' : '') + msg);
}

function adminNotifySuccess(mensaje) {
    const msg = String(mensaje || 'Operación exitosa').trim();
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'success', title: msg, timer: 2200, showConfirmButton: false, toast: true, position: 'top-end' });
        return;
    }
    if (typeof alertify !== 'undefined') {
        alertify.success(msg, 4);
        return;
    }
}

function adminCsrfToken() {
    if (window.APP_CSRF_TOKEN) {
        return window.APP_CSRF_TOKEN;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
}

function adminAppendCsrf(fd) {
    if (fd instanceof FormData && !fd.has('csrf_token')) {
        fd.append('csrf_token', adminCsrfToken());
    }
    return fd;
}

async function adminFetchJson(url, options) {
    const opts = options || {};
    let body = opts.body;

    if (body instanceof FormData && opts.withCsrf !== false) {
        const action = body.get('action');
        const writeActions = opts.writeActions;
        if (writeActions ? writeActions.includes(String(action || '')) : opts.withCsrf === true) {
            adminAppendCsrf(body);
        }
    }

    const res = await fetch(url, {
        method: opts.method || 'POST',
        body,
        credentials: opts.credentials || 'same-origin',
        headers: opts.headers,
    });

    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text.trim());
    } catch (e) {
        const preview = text.slice(0, 120).replace(/\s+/g, ' ').trim();
        throw new Error(
            res.ok
                ? 'Respuesta inválida del servidor. Recarga la página e intenta de nuevo.'
                : `Error del servidor (HTTP ${res.status})${preview ? ': ' + preview : ''}`
        );
    }

    if (!res.ok && data && !data.message) {
        data.message = `Error HTTP ${res.status}`;
    }

    return data;
}

function adminDebounce(fn, wait) {
    let timer;
    return function debounced(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}
