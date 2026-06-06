const ENDPOINT = '/front/ajax/usuarios.ajax.php';
let rolesCache = [], modalUsuario, usuariosCache = [];

document.addEventListener('DOMContentLoaded', async () => {
    modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));
    await cargarRoles();
    await cargarUsuarios();
});

async function api(action, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    const writeActions = ['crear', 'actualizar'];
    return adminFetchJson(ENDPOINT, {
        body: fd,
        withCsrf: writeActions.includes(action),
        writeActions,
    });
}

async function cargarRoles() {
    try {
        const data = await api('roles');
        if (data.success) {
            rolesCache = data.data;
            document.getElementById('usuarioRol').innerHTML = rolesCache.map(r =>
                `<option value="${r.id_role}">${r.name_role}</option>`).join('');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los roles.');
    }
}

async function cargarUsuarios() {
    const tbody = document.getElementById('bodyUsuarios');
    try {
        const data = await api('listar');
        if (!data.success || !data.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Sin usuarios</td></tr>';
            return;
        }
    usuariosCache = data.data;
    tbody.innerHTML = usuariosCache.map(u => {
        const activo = parseInt(u.status_admin) === 1;
        return `
        <tr class="align-middle admin-table-row">
            <td class="py-3 ps-4">${u.id_admin}</td>
            <td class="py-3 text-truncate" style="max-width:200px" title="${adminEscapeHtml(u.email_admin)}">${u.email_admin}</td>
            <td class="py-3"><span class="badge bg-primary">${u.name_role || u.rol_admin || '-'}</span></td>
            <td class="py-3">${activo ? '<span class="text-success fw-medium">Activo</span>' : '<span class="text-danger fw-medium">Inactivo</span>'}</td>
            <td class="py-3">${adminIconActions(adminIconBtn(`editarUsuario(${u.id_admin})`, 'ti-edit', 'primary', 'Editar'))}</td>
        </tr>`;
    }).join('');

    if (typeof renderAdminMobileRows === 'function') {
        renderAdminMobileRows('usuariosMobile', usuariosCache.map(u => {
            const activo = parseInt(u.status_admin) === 1;
            return adminMobileRow(
                adminMobileSection(`<div class="fw-bold text-dark text-truncate" title="${adminEscapeHtml(u.email_admin)}">${adminEscapeHtml(u.email_admin)}</div>`) +
                `<div class="admin-mobile-row__fields">
                    <div class="admin-mobile-row__field"><div class="text-muted small">ID</div><div>#${u.id_admin}</div></div>
                    <div class="admin-mobile-row__field"><div class="text-muted small">Rol</div><div><span class="badge bg-primary">${adminEscapeHtml(u.name_role || u.rol_admin || '—')}</span></div></div>
                </div>` +
                adminMobileKpi(`<span class="${activo ? 'text-success' : 'text-danger'} fw-medium">${activo ? 'Activo' : 'Inactivo'}</span>`) +
                adminMobileBottom('', adminIconActions(adminIconBtn(`editarUsuario(${u.id_admin})`, 'ti-edit', 'primary', 'Editar')))
            );
        }));
    }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Error al cargar</td></tr>';
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar los usuarios.');
    }
}

function abrirModalUsuario() {
    document.getElementById('usuarioId').value = '';
    document.getElementById('usuarioEmail').value = '';
    document.getElementById('usuarioPass').value = '';
    document.getElementById('usuarioEstado').value = '1';
    document.getElementById('tituloModalUsuario').textContent = 'Nuevo usuario';
    modalUsuario.show();
}

function editarUsuario(id) {
    const u = usuariosCache.find(x => parseInt(x.id_admin) === parseInt(id));
    if (!u) return;
    document.getElementById('usuarioId').value = u.id_admin;
    document.getElementById('usuarioEmail').value = u.email_admin;
    document.getElementById('usuarioPass').value = '';
    document.getElementById('usuarioRol').value = u.id_role || '';
    document.getElementById('usuarioEstado').value = u.status_admin;
    document.getElementById('tituloModalUsuario').textContent = 'Editar usuario';
    modalUsuario.show();
}

async function guardarUsuario() {
    const id = document.getElementById('usuarioId').value;
    const extra = {
        email_admin: document.getElementById('usuarioEmail').value.trim(),
        password_admin: document.getElementById('usuarioPass').value,
        id_role: document.getElementById('usuarioRol').value,
        status_admin: document.getElementById('usuarioEstado').value,
    };
    if (id) extra.id_admin = id;
    try {
        const data = await api(id ? 'actualizar' : 'crear', extra);
        if (data.success) {
            modalUsuario.hide();
            cargarUsuarios();
            alertify.success(data.message || 'Guardado');
        } else {
            alertify.error(data.message || 'Error');
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo guardar el usuario.');
    }
}
