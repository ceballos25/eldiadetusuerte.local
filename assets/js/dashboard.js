let chartTendenciaInst = null;
let chartMediosDineroInst = null;
let chartMediosCantInst = null;
let chartMediosTransInst = null;
let chartTopCliInst = null;
let chartTopCiuInst = null;
let chartHeatmapInst = null;
let chartPaquetesInst = null;
const DASHBOARD_ENDPOINT = '/front/ajax/dashboard.ajax.php';
const VENTAS_ENDPOINT = '/front/ajax/ventas.ajax.php';

async function fetchDashboard(fd) {
    return adminFetchJson(DASHBOARD_ENDPOINT, { body: fd });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarRifas();
    cambiarPeriodo(); 

    document.getElementById('filterPeriodo').addEventListener('change', cambiarPeriodo);
    document.getElementById('filterDesde').addEventListener('change', () => document.getElementById('filterPeriodo').value = '');
    document.getElementById('filterHasta').addEventListener('change', () => document.getElementById('filterPeriodo').value = '');
});

function cambiarPeriodo() {
    const periodo = document.getElementById('filterPeriodo').value;
    const date = new Date();
    const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    let desde = '', hasta = '';

    if (periodo === 'hoy') { desde = hasta = formatDate(date); }
    else if (periodo === 'ayer') { date.setDate(date.getDate() - 1); desde = hasta = formatDate(date); }
    else if (periodo === 'semana') { const day = date.getDay() || 7; if(day!==1) date.setHours(-24 * (day-1)); desde = formatDate(date); hasta = formatDate(new Date()); }
    else if (periodo === 'mes') { desde = formatDate(new Date(date.getFullYear(), date.getMonth(), 1)); hasta = formatDate(new Date(date.getFullYear(), date.getMonth() + 1, 0)); }
    else if (periodo === 'ano') { desde = formatDate(new Date(date.getFullYear(), 0, 1)); hasta = formatDate(new Date(date.getFullYear(), 11, 31)); }

    if (desde && hasta) {
        document.getElementById('filterDesde').value = desde;
        document.getElementById('filterHasta').value = hasta;
        cargarDashboard();
    }
}

async function cargarRifas() {
    try {
        const fd = new FormData();
        fd.append('action', 'obtener_rifas');
        const j = await fetchDashboard(fd);
        const sel = document.getElementById('filterRifa');
        if (j.success && sel) {
            sel.innerHTML = '<option value="">🌐 Todas las Rifas</option>';
            j.data.forEach(x => sel.innerHTML += `<option value="${x.id_raffle}">${x.title_raffle}</option>`);
        } else if (!j.success) {
            adminNotifyError(adminExtractMessage(j, 'No se pudieron cargar las rifas del dashboard'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudieron cargar las rifas del dashboard');
    }
}

async function cargarDashboard() {
    const desde = document.getElementById('filterDesde').value;
    const hasta = document.getElementById('filterHasta').value;
    const rifa  = document.getElementById('filterRifa').value;

    try {
        const fd = new FormData();
        fd.append('action', 'obtener_dashboard');
        fd.append('fechaDesde', desde);
        fd.append('fechaHasta', hasta);
        fd.append('id_raffle', rifa);

        const data = await fetchDashboard(fd);

        if (data.success) {
            renderKPIs(data.data.kpis);
            renderCharts(data.data.graficas);
            renderTabla(data.data.ultimasVentas);
        } else {
            adminNotifyError(adminExtractMessage(data, 'No se pudo cargar el dashboard'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el dashboard');
    }
}

function limpiarFiltrosDashboard() {
    document.getElementById('filterRifa').value = '';
    document.getElementById('filterPeriodo').value = 'ano';
    cambiarPeriodo();
}

function renderKPIs(kpis) {
    const fmtMoney = (n) => '$' + Number(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const fmtNum = (n) => Number(n).toLocaleString('es-CO');

    document.getElementById('kpiVentas').innerText = fmtMoney(kpis.totalVentas);
    document.getElementById('kpiVendidos').innerText = fmtNum(kpis.numerosVendidos);
    document.getElementById('kpiClientes').innerText = fmtNum(kpis.totalClientes);
    document.getElementById('kpiDisponibles').innerText = fmtNum(kpis.numerosDisponibles);

    const elStockDet = document.getElementById('kpiDisponiblesDetalle');
    if (elStockDet) {
        const libres = Number(kpis.numerosLibres) || 0;
        const reservados = Number(kpis.numerosReservados) || 0;
        elStockDet.textContent = `${fmtNum(libres)} disponibles · ${fmtNum(reservados)} reservados`;
    }

    renderKpiOperativos(kpis);
}

function renderKpiOperativos(kpis) {
    const pct = Math.min(100, Math.max(0, Number(kpis.porcentajeReal) || 0));
    const vendidos = Number(kpis.numerosVendidosRifa) || 0;
    const total = Number(kpis.totalNumerosRifa) || 0;
    const pendientes = Number(kpis.transferenciasPendientes) || 0;
    const pctLabel = Number.isInteger(pct) ? `${pct}%` : `${pct.toFixed(2)}%`;

    const elPct = document.getElementById('kpiPorcentajeReal');
    const elBar = document.getElementById('kpiBarraReal');
    const elDet = document.getElementById('kpiProgresoDetalle');
    const elRifa = document.getElementById('kpiProgresoRifaLabel');
    const elTrans = document.getElementById('kpiTransferPendientes');

    if (elPct) elPct.textContent = pctLabel;
    if (elBar) {
        elBar.style.width = `${pct}%`;
        elBar.setAttribute('aria-valuenow', String(pct));
    }
    if (elDet) {
        elDet.textContent = `${vendidos.toLocaleString('es-CO')} de ${total.toLocaleString('es-CO')} nros`;
    }
    if (elRifa) {
        const titulo = String(kpis.tituloRifaProgreso || '').trim();
        elRifa.textContent = titulo || 'Todas las rifas';
    }
    if (elTrans) elTrans.textContent = pendientes.toLocaleString('es-CO');

    const cardTrans = document.querySelector('.dashboard-kpi-transfers');
    if (cardTrans) {
        cardTrans.classList.toggle('dashboard-kpi-transfers--hot', pendientes > 0);
    }
}

const commonDonutOptions = {
    chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: { show: true, fontSize: '14px' }, value: { show: true, fontSize: '22px', fontWeight: 700, offsetY: 5 }, total: { show: true, label: 'TOTAL', fontSize: '12px', fontWeight: 600, color: '#6c757d' } } } } },
    dataLabels: { enabled: false }
};

function renderCharts(graficas) {
    const fmtMoney = v => '$' + Number(v).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const fmtNum = v => Number(v).toLocaleString('es-CO');

    const colorsTicket = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585'];
    const colorsDinero = ['#2ec4b6', '#ff9f1c', '#e71d36', '#011627'];
    const colorsTrans  = ['#3f37c9', '#4cc9f0', '#4895ef', '#560bad'];

    const optTendencia = {
        series: [{ name: 'Ventas ($)', data: graficas.tendencia.map(x => x.total) }],
        chart: { type: 'area', height: 350, toolbar: { show: false }, fontFamily: 'inherit' },
        xaxis: { categories: graficas.tendencia.map(x => x.fecha) },
        yaxis: { labels: { formatter: (val) => fmtMoney(val) } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#4361ee'],
        tooltip: { y: { formatter: (val) => fmtMoney(val) } },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.6, opacityTo: 0.1 } }
    };
    if(chartTendenciaInst) chartTendenciaInst.destroy();
    chartTendenciaInst = new ApexCharts(document.querySelector("#chartTendencia"), optTendencia);
    chartTendenciaInst.render();

    const optTrans = JSON.parse(JSON.stringify(commonDonutOptions));
    optTrans.series = graficas.mediosPagoTransacciones.length ? graficas.mediosPagoTransacciones : [1];
    optTrans.labels = graficas.mediosPagoLabels.length ? graficas.mediosPagoLabels : ['Sin datos'];
    optTrans.colors = colorsTrans;
    optTrans.plotOptions.pie.donut.labels.value.formatter = val => fmtNum(val);
    optTrans.plotOptions.pie.donut.labels.total.formatter = w => fmtNum(w.globals.seriesTotals.reduce((a, b) => a + b, 0));
    optTrans.tooltip = { y: { formatter: v => fmtNum(v) + ' Ventas' } };
    
    if(chartMediosTransInst) chartMediosTransInst.destroy();
    chartMediosTransInst = new ApexCharts(document.querySelector("#chartMediosTransacciones"), optTrans);
    chartMediosTransInst.render();

    const optTick = JSON.parse(JSON.stringify(commonDonutOptions));
    optTick.series = graficas.mediosPagoTickets.length ? graficas.mediosPagoTickets : [1];
    optTick.labels = graficas.mediosPagoLabels.length ? graficas.mediosPagoLabels : ['Sin datos'];
    optTick.colors = colorsTicket;
    optTick.plotOptions.pie.donut.labels.value.formatter = val => fmtNum(val);
    optTick.plotOptions.pie.donut.labels.total.formatter = w => fmtNum(w.globals.seriesTotals.reduce((a, b) => a + b, 0));
    optTick.tooltip = { y: { formatter: v => fmtNum(v) + ' Nros' } };
    
    if(chartMediosCantInst) chartMediosCantInst.destroy();
    chartMediosCantInst = new ApexCharts(document.querySelector("#chartMediosTickets"), optTick);
    chartMediosCantInst.render();

    const optDin = JSON.parse(JSON.stringify(commonDonutOptions));
    optDin.series = graficas.mediosPagoDinero.length ? graficas.mediosPagoDinero : [1];
    optDin.labels = graficas.mediosPagoLabels.length ? graficas.mediosPagoLabels : ['Sin datos'];
    optDin.colors = colorsDinero;
    optDin.plotOptions.pie.donut.labels.value.formatter = val => fmtMoney(val);
    optDin.plotOptions.pie.donut.labels.total.formatter = w => fmtMoney(w.globals.seriesTotals.reduce((a, b) => a + b, 0));
    optDin.tooltip = { y: { formatter: v => fmtMoney(v) } };
    
    if(chartMediosDineroInst) chartMediosDineroInst.destroy();
    chartMediosDineroInst = new ApexCharts(document.querySelector("#chartMediosDinero"), optDin);
    chartMediosDineroInst.render();

    const optTopCli = {
        series: [{ name: 'Compras', data: graficas.topClientes.map(x => x.total) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: graficas.topClientes.map(x => x.name), labels: { style: { fontSize: '11px' } } },
        colors: ['#212529'],
        grid: { show: false },
        tooltip: {
            custom: function({ series, seriesIndex, dataPointIndex, w }) {
                const c = graficas.topClientes[dataPointIndex];
                return `<div class="px-3 py-2 border rounded shadow bg-white text-dark text-start" style="font-size: 0.85rem; min-width: 180px;"><div class="fw-bold mb-2 border-bottom pb-1 text-uppercase text-primary">${c.name}</div><div class="d-flex justify-content-between mb-1"><span>💰 Total:</span><span class="fw-bold">${fmtMoney(c.total)}</span></div><div class="d-flex justify-content-between mb-2"><span>🎟️ Nros:</span><span class="fw-bold">${fmtNum(c.cantidad)}</span></div><div class="bg-light p-1 rounded small text-muted"><div><i class="ti ti-phone me-1"></i> ${c.telefono}</div><div><i class="ti ti-map-pin me-1"></i> ${c.ciudad}</div></div></div>`;
            }
        }
    };
    if(chartTopCliInst) chartTopCliInst.destroy();
    chartTopCliInst = new ApexCharts(document.querySelector("#chartTopClientes"), optTopCli);
    chartTopCliInst.render();

    const optTopCiu = {
        series: [{ name: 'Nros', data: graficas.topCiudades.map(x => x.data) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%', distributed: true } },
        dataLabels: { enabled: true, formatter: (val) => fmtNum(val) },
        xaxis: { categories: graficas.topCiudades.map(x => x.name) },
        colors: ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0'],
        legend: { show: false },
        grid: { show: false },
        tooltip: { y: { formatter: v => fmtNum(v) + ' Nros' } }
    };
    if(chartTopCiuInst) chartTopCiuInst.destroy();
    chartTopCiuInst = new ApexCharts(document.querySelector("#chartTopCiudades"), optTopCiu);
    chartTopCiuInst.render();

    const optHeat = {
        series: graficas.heatmap,
        chart: { type: 'heatmap', height: 350, toolbar: { show: false }, fontFamily: 'inherit' },
        dataLabels: { enabled: false },
        colors: ["#dd1313"],
        title: { text: '' },
        plotOptions: { heatmap: { shadeIntensity: 0.5, colorScale: { ranges: [{ from: 0, to: 0, color: '#f8f9fa', name: 'Sin Ventas' }] } } },
        tooltip: { y: { formatter: v => v + ' Ventas' } }
    };
    if(chartHeatmapInst) chartHeatmapInst.destroy();
    chartHeatmapInst = new ApexCharts(document.querySelector("#chartHeatmap"), optHeat);
    chartHeatmapInst.render();

    const optPaq = {
        series: [{ name: 'Ventas', data: graficas.paquetes.map(x => x.data) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
        dataLabels: { enabled: true },
        xaxis: { categories: graficas.paquetes.map(x => x.name) },
        colors: ['#10b981'],
        grid: { show: false },
        tooltip: { y: { formatter: v => v + ' veces comprado' } }
    };
    if(chartPaquetesInst) chartPaquetesInst.destroy();
    chartPaquetesInst = new ApexCharts(document.querySelector("#chartPaquetes"), optPaq);
    chartPaquetesInst.render();
}

function renderTabla(ventas) {
    const tbody = document.getElementById('tablaUltimasVentas');
    if (!tbody) return;

    if (!ventas || ventas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No hay ventas registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = ventas.map(v => renderDashboardVentaRow(v)).join('');
}

function dashboardVentaNombre(v) {
    return `${v.name_customer || ''} ${v.lastname_customer || ''}`.trim() || '—';
}

function dashboardFormatVendedor(emailAdmin) {
    const e = String(emailAdmin ?? 'Sistema').trim();
    return e || 'Sistema';
}

function dashboardFormatVendedorShort(emailAdmin) {
    const e = dashboardFormatVendedor(emailAdmin);
    const at = e.indexOf('@');
    return at > 0 ? e.slice(0, at) : e;
}

function dashboardVentaDatos(v) {
    const { fecha, hora } = adminFormatDateColombia(v.date_created_sale);
    const qty = Number(v.quantity_sale) || 0;
    const badgeClass = adminPaymentMethodBadgeClass(v.payment_method_sale);

    return {
        fecha,
        hora,
        inicial: adminInitial(v.name_customer),
        nombre: dashboardVentaNombre(v),
        vendedor: dashboardFormatVendedor(v.email_admin),
        origen: String(v.source_sale ?? '').trim(),
        badgeClass,
        qtyLabel: `${qty} núm${qty > 1 ? 's' : ''}`,
        totalLabel: `$${Number(v.total_sale).toLocaleString('es-CO')}`,
    };
}

function renderDashboardVentaClientMobile(d, v) {
    return `
    <div class="admin-card-head d-lg-none">
        ${adminClientMobileHead({
            initial: d.inicial,
            name: d.nombre,
            phone: v.phone_customer,
            extraHtml: adminVendedorOrigenLines(dashboardFormatVendedorShort(d.vendedor), d.origen),
        })}
        <div class="admin-card-head__status-row">
            <span class="badge ${d.badgeClass} px-2 py-1 rounded-pill">${adminEscapeHtml(v.payment_method_sale || '—')}</span>
        </div>
        <div class="admin-card-head__meta">
            <span class="card-meta-chip card-meta-chip--qty">${adminEscapeHtml(d.qtyLabel)}</span>
            <span class="card-meta-chip card-meta-chip--total">${adminEscapeHtml(d.totalLabel)}</span>
        </div>
        <div class="admin-card-head__rifa">
            <span class="cell-rifa-name">${adminEscapeHtml(v.title_raffle || '—')}</span>
        </div>
        <div class="admin-card-head__code-fecha d-lg-none">
            ${adminTokenChipBlock(v.code_sale)}
            ${adminFechaCompact(d.fecha, d.hora)}
        </div>
    </div>`;
}

function renderDashboardVentaClientDesktop(d, v) {
    const vendedorShort = adminEscapeHtml(dashboardFormatVendedorShort(d.vendedor));
    const origen = String(d.origen ?? '').trim();
    const metaLine = origen
        ? `<small class="text-muted fst-italic venta-meta-desktop">
            <i class="ti ti-user"></i> ${vendedorShort}
            &nbsp;·&nbsp;<i class="ti ti-world"></i> ${adminEscapeHtml(origen)}
           </small>`
        : `<small class="text-muted fst-italic venta-meta-desktop">
            <i class="ti ti-user"></i> ${vendedorShort}
           </small>`;

    return `
    <div class="d-none d-lg-flex">
        <div class="rounded-circle bg-light border d-flex justify-content-center align-items-center text-secondary fw-bold me-3 flex-shrink-0 venta-avatar-desktop">
            ${adminEscapeHtml(d.inicial)}
        </div>
        <div class="d-flex flex-column venta-client-desktop-col">
            <span class="fw-bold text-dark text-capitalize">${adminEscapeHtml(d.nombre)}</span>
            <div class="text-muted small mt-1">
                <span class="me-2"><i class="ti ti-phone"></i> ${adminEscapeHtml(v.phone_customer || '—')}</span>
            </div>
            ${metaLine}
        </div>
    </div>`;
}

function renderDashboardVentaActions(id) {
    return `
    <div class="d-lg-none">
        <div class="btn-group btn-group-sm shadow-sm w-100 venta-mobile-actions" role="group">
            <button type="button" class="btn btn-outline-primary flex-fill" onclick="dashboardVerRecibo(${id})" title="Ver Detalle">
                <i class="ti ti-eye"></i> Ver
            </button>
            <button type="button" class="btn btn-outline-secondary flex-fill" onclick="window.location.href='ventas.php'" title="Gestionar en Ventas">
                <i class="ti ti-settings"></i> Gestionar
            </button>
        </div>
    </div>
    <button type="button" class="btn btn-icon btn-sm btn-outline-primary border-0 rounded-circle shadow-sm d-none d-lg-inline-flex venta-action-btn"
        onclick="dashboardVerRecibo(${id})" title="Ver Detalle">
        <i class="ti ti-eye fs-7"></i>
    </button>
    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary border-0 rounded-circle shadow-sm ms-1 d-none d-lg-inline-flex venta-action-btn"
        onclick="window.location.href='ventas.php'" title="Gestionar en Ventas">
        <i class="ti ti-settings fs-7"></i>
    </button>`;
}

function renderDashboardVentaRow(v) {
    const d = dashboardVentaDatos(v);
    const id = Number(v.id_sale);

    return `
    <tr class="align-middle border-bottom card-row-admin card-row-venta venta-table-row">
        <td class="py-3 ps-3 mobile-card-head">
            ${renderDashboardVentaClientMobile(d, v)}
            ${renderDashboardVentaClientDesktop(d, v)}
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="font-monospace bg-light text-primary px-2 py-1 rounded border venta-code-chip">${adminEscapeHtml(v.code_sale)}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="fw-medium text-dark d-block">${adminEscapeHtml(d.qtyLabel)}</span>
            <small class="text-muted text-truncate d-block venta-rifa-truncate">${adminEscapeHtml(v.title_raffle || '—')}</small>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="fw-bold text-dark">${adminEscapeHtml(d.totalLabel)}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <span class="badge ${d.badgeClass} px-3 py-2 rounded-pill">${adminEscapeHtml(v.payment_method_sale || '—')}</span>
        </td>
        <td class="d-none d-lg-table-cell py-3">
            <div class="d-flex flex-column text-muted">
                <span class="text-dark fw-medium">${adminEscapeHtml(d.fecha)}</span>
                <span class="venta-hora-desktop">${adminEscapeHtml(d.hora)}</span>
            </div>
        </td>
        <td class="py-3 text-end pe-3 mobile-card-actions">
            ${renderDashboardVentaActions(id)}
        </td>
    </tr>`;
}

async function dashboardVerRecibo(id) {
    try {
        const fd = new FormData();
        fd.append('action', 'detalle_venta');
        fd.append('id_sale', id);
        const res = await adminFetchJson(VENTAS_ENDPOINT, { body: fd });
        if (res.success) {
            document.getElementById('cuerpoRecibo').innerHTML = res.html_recibo;
            new bootstrap.Modal(document.getElementById('modalRecibo')).show();
        } else {
            adminNotifyError(adminExtractMessage(res, 'No se pudo cargar el comprobante'));
        }
    } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cargar el comprobante');
    }
}
