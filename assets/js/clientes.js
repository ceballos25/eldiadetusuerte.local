let clientesCache = [], idClienteEliminar = null, modalCliente = null, modalConfirm = null;
let paginaActual = 1;
const registrosPorPagina = 10;
const CLIENTES_ENDPOINT = '/front/ajax/clientes.ajax.php';

function abrirModal() {
    const form = document.getElementById('formCliente');
    if (form) form.reset();
    
    setVal('clienteId', '');
    document.getElementById('modalTitle').textContent = 'Nuevo Cliente';

    $('#departamento').val('').trigger('change');
    $('#ciudad').val('').trigger('change').prop('disabled', true);
    
    if(modalCliente) modalCliente.show();
}

function cambiarPagina(p) { 
    paginaActual = p; 
    renderizarTodo(); 
}

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.value = (value !== null && value !== undefined) ? value : '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const elModalCliente = document.getElementById('modalCliente');
    const elModalConfirm = document.getElementById('modalConfirm');

    if (elModalCliente) modalCliente = bootstrap.Modal.getOrCreateInstance(elModalCliente);
    if (elModalConfirm) modalConfirm = bootstrap.Modal.getOrCreateInstance(elModalConfirm);

    if ($('.select2-departamento').length) {
        $('.select2-departamento').select2({
            theme: 'bootstrap-5', dropdownParent: $('#modalCliente'), width: '100%', placeholder: 'Departamento'
        });
    }
    if ($('.select2-ciudad').length) {
        $('.select2-ciudad').select2({
            theme: 'bootstrap-5', dropdownParent: $('#modalCliente'), width: '100%', placeholder: 'Ciudad'
        });
    }

    if (typeof inicializarUbicacion === 'function') inicializarUbicacion();

    if (document.getElementById('bodyTabla')) cargarClientes();

    const inputSearch = document.getElementById('searchClientes');
    if (inputSearch) inputSearch.addEventListener('input', debounce(cargarClientes, 500));

    const selectStatus = document.getElementById('filterStatus');
    if (selectStatus) selectStatus.addEventListener('change', cargarClientes);
});

async function cargarClientes() {
    if (typeof showPreloader === 'function') showPreloader();
    try {
        const formData = new FormData();
        formData.append('action', 'obtener');
        formData.append('search', document.getElementById('searchClientes')?.value.trim() || '');
        formData.append('status', document.getElementById('filterStatus')?.value || '');

        const response = await adminFetchJson(CLIENTES_ENDPOINT, { body: formData });

        if (response.success) {
            clientesCache = response.data || [];
            renderizarTodo();
        } else {
            adminNotifyError(adminExtractMessage(response, 'No se pudieron cargar los clientes.'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'Error al cargar clientes.');
    } finally { if (typeof hidePreloader === 'function') hidePreloader(); }
}

function renderizarTodo() {
    if (typeof PaginationHelper === 'undefined') return;
    const segmento = PaginationHelper.getSegment(clientesCache, paginaActual, registrosPorPagina);
    renderTabla(segmento);
    PaginationHelper.render({
        totalItems: clientesCache.length, currentPage: paginaActual, limit: registrosPorPagina,
        containerId: 'contenedorPaginacion', infoId: 'infoPaginacion', callbackName: 'cambiarPagina'
    });
}

function renderTabla(clientes) {
    const tbody = document.getElementById('bodyTabla');
    if (!tbody) return;
    if (!clientes || clientes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">No se encontraron registros</td></tr>`;
        if (typeof renderAdminMobileRows === 'function') renderAdminMobileRows('clientesMobile', []);
        return;
    }
    tbody.innerHTML = clientes.map(c => {
        const activo = parseInt(c.status_customer) === 1;
        const inicial = adminInitial(c.name_customer);
        return `
            <tr class="align-middle admin-table-row">
                <td class="py-3 ps-4">${c.id_customer}</td>
                <td class="py-3 admin-cell-client">
                    ${adminClientBlock({
                        initial: inicial,
                        name: `${c.name_customer} ${c.lastname_customer}`,
                        phone: c.phone_customer,
                        city: c.city_customer,
                        subHtml: adminEscapeHtml(c.email_customer || ''),
                        subTitle: c.email_customer || '',
                    })}
                </td>
                <td class="py-3">${c.phone_customer || '-'}</td>
                <td class="py-3 text-truncate" style="max-width:180px" title="${adminEscapeHtml(c.email_customer || '')}">${c.email_customer || '-'}</td>
                <td class="py-3">${c.department_customer || '-'}</td>
                <td class="py-3">${c.city_customer || '-'}</td>
                <td class="py-3"><span class="badge ${activo ? 'bg-success' : 'bg-danger'}">${activo ? 'Activo' : 'Inactivo'}</span></td>
                <td class="py-3">
                    ${adminIconActions(
                        adminIconBtn(`editarCliente(${c.id_customer})`, 'ti-edit', 'primary', 'Editar') +
                        adminIconBtn(`eliminarCliente(${c.id_customer})`, 'ti-trash', 'danger', 'Eliminar')
                    )}
                </td>
            </tr>`;
    }).join('');

    if (typeof renderAdminMobileRows === 'function') {
        renderAdminMobileRows('clientesMobile', clientes.map(c => {
            const activo = parseInt(c.status_customer) === 1;
            return adminMobileRow(
                adminMobileSection(adminClientBlock({
                    initial: adminInitial(c.name_customer),
                    name: `${c.name_customer} ${c.lastname_customer}`,
                    phone: c.phone_customer,
                    city: c.city_customer,
                    subHtml: adminEscapeHtml(c.email_customer || ''),
                    subTitle: c.email_customer || '',
                })) +
                `<div class="admin-mobile-row__fields">
                    <div class="admin-mobile-row__field"><div class="text-muted small">ID</div><div class="fw-bold">#${c.id_customer}</div></div>
                    <div class="admin-mobile-row__field"><div class="text-muted small">Departamento</div><div>${adminEscapeHtml(c.department_customer || '—')}</div></div>
                </div>` +
                adminMobileKpi(`<span class="badge ${activo ? 'bg-success' : 'bg-danger'}">${activo ? 'Activo' : 'Inactivo'}</span>`) +
                adminMobileBottom('', adminIconActions(
                    adminIconBtn(`editarCliente(${c.id_customer})`, 'ti-edit', 'primary', 'Editar') +
                    adminIconBtn(`eliminarCliente(${c.id_customer})`, 'ti-trash', 'danger', 'Eliminar')
                ))
            );
        }));
    }
}

function editarCliente(id) {
    const c = clientesCache.find(x => parseInt(x.id_customer) === parseInt(id));
    if (!c) return;

    setVal('clienteId', c.id_customer);
    setVal('nombre', c.name_customer);
    setVal('apellido', c.lastname_customer);
    setVal('telefono', c.phone_customer);
    setVal('email', c.email_customer);
    setVal('estado', c.status_customer);

    if (c.department_customer) {
        $('#departamento').val(c.department_customer).trigger('change');
        if (c.city_customer) {

            setTimeout(() => {
                $('#ciudad').val(c.city_customer).trigger('change');
            }, 350);
        }
    }

    document.getElementById('modalTitle').textContent = 'Editar Cliente';
    if(modalCliente) modalCliente.show();
}

async function guardarCliente() {
    const id = document.getElementById('clienteId').value;
    const formData = new FormData();
    formData.append('action', id ? 'actualizar' : 'crear');
    formData.append('id_customer', id);
    formData.append('name_customer', document.getElementById('nombre').value.trim());
    formData.append('lastname_customer', document.getElementById('apellido').value.trim());
    formData.append('phone_customer', document.getElementById('telefono').value.trim());
    formData.append('email_customer', document.getElementById('email').value.trim());
    formData.append('department_customer', $('#departamento').val() || '');
    formData.append('city_customer', $('#ciudad').val() || '');
    formData.append('status_customer', document.getElementById('estado').value);
    formData.append('csrf_token', window.APP_CSRF_TOKEN || '');
    if (typeof showPreloader === 'function') showPreloader();
    try {
        const res = await fetch(CLIENTES_ENDPOINT, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            alertify.success(data.message);
            if(modalCliente) modalCliente.hide();
            cargarClientes();
        } else { alertify.error(data.message); }
    } catch (e) { alertify.error("Error en la solicitud"); }
    finally { if (typeof hidePreloader === 'function') hidePreloader(); }
}

function eliminarCliente(id) { 
    idClienteEliminar = id; 
    if(modalConfirm) modalConfirm.show(); 
}

async function confirmarEliminar() {
    if (typeof showPreloader === 'function') showPreloader();
    try {
        const fd = new FormData();
        fd.append('action', 'eliminar');
        fd.append('id_customer', idClienteEliminar);
        fd.append('csrf_token', window.APP_CSRF_TOKEN || '');
        const res = await fetch(CLIENTES_ENDPOINT, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            alertify.success('Eliminado');
            if(modalConfirm) modalConfirm.hide();
            cargarClientes();
        } else {
            alertify.error(adminExtractMessage(data, 'No se pudo eliminar el cliente.'));
        }
    } catch (e) {
        alertify.error(e instanceof Error ? e.message : 'Error en la solicitud');
    } finally { if (typeof hidePreloader === 'function') hidePreloader(); }
}

function limpiarFiltros() {
    document.getElementById('searchClientes').value = '';
    document.getElementById('filterStatus').value = '';
    paginaActual = 1;
    cargarClientes();
}

function debounce(f, w) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), w); }; }
