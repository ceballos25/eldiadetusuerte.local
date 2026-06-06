const state = {
  datasets: [],
  currentFields: [],
  dimensions: [],
  measures: [],
  filters: [],
  table: null,
  lastResult: null,
};
const REPORTS_ENDPOINT = '/front/ajax/reports.ajax.php';
const DEFAULT_RUN_BUTTON_HTML = '<i class="ti ti-player-play me-1"></i> Ejecutar consulta';
const FIELD_TERM_TRANSLATIONS = {
  id: 'ID',
  customer: 'cliente',
  client: 'cliente',
  phone: 'telefono',
  mobile: 'celular',
  email: 'correo',
  mail: 'correo',
  name: 'nombre',
  first: 'primer',
  last: 'apellido',
  full: 'completo',
  number: 'numero',
  code: 'codigo',
  status: 'estado',
  type: 'tipo',
  amount: 'monto',
  total: 'total',
  subtotal: 'subtotal',
  tax: 'impuesto',
  discount: 'descuento',
  date: 'fecha',
  time: 'hora',
  created: 'creado',
  updated: 'actualizado',
  deleted: 'eliminado',
  at: '',
  ticket: 'ticket',
  order: 'orden',
  sale: 'venta',
  payment: 'pago',
  method: 'metodo',
  user: 'usuario',
  users: 'usuarios',
  branch: 'sucursal',
  store: 'tienda',
  city: 'ciudad',
  country: 'pais',
  address: 'direccion',
  notes: 'notas',
};
const ENTITY_SUFFIX_TRANSLATIONS = {
  admin: 'administrador',
  customer: 'cliente',
  raffle: 'rifa',
  sale: 'venta',
  ticket: 'ticket',
  transfer: 'transferencia',
  payment: 'pago',
  backup: 'respaldo',
  setting: 'configuracion',
};
const FIELD_EXACT_LABELS = {
  id_admin: 'ID administrador',
  email_admin: 'Correo administrador',
  password_admin: 'Contrasena administrador',
  rol_admin: 'Rol administrador',
  token_admin: 'Token administrador',
  token_exp_admin: 'Vencimiento token administrador',
  status_admin: 'Estado administrador',
  date_created_admin: 'Fecha creacion administrador',
  date_updated_admin: 'Fecha actualizacion administrador',

  id_customer: 'ID cliente',
  name_customer: 'Nombres cliente',
  lastname_customer: 'Apellidos cliente',
  phone_customer: 'Celular cliente',
  email_customer: 'Correo cliente',
  department_customer: 'Departamento cliente',
  city_customer: 'Ciudad cliente',
  status_customer: 'Estado cliente',
  date_created_customer: 'Fecha creacion cliente',
  date_updated_customer: 'Fecha actualizacion cliente',

  id_raffle: 'ID rifa',
  title_raffle: 'Titulo rifa',
  description_raffle: 'Descripcion rifa',
  price_raffle: 'Precio rifa',
  digits_raffle: 'Cifras rifa',
  date_raffle: 'Fecha sorteo',
  status_raffle: 'Estado rifa',
  date_created_raffle: 'Fecha creacion rifa',
  date_updated_raffle: 'Fecha actualizacion rifa',

  id_sale: 'ID venta',
  id_customer_sale: 'ID cliente venta',
  id_raffle_sale: 'ID rifa venta',
  code_sale: 'Codigo venta',
  quantity_sale: 'Cantidad venta',
  total_sale: 'Total venta',
  payment_method_sale: 'Metodo de pago venta',
  status_sale: 'Estado venta',
  id_admin_sale: 'ID administrador venta',
  source_sale: 'Origen venta',
  date_created_sale: 'Fecha creacion venta',
  date_updated_sale: 'Fecha actualizacion venta',

  id_ticket: 'ID ticket',
  number_ticket: 'Numero ticket',
  status_ticket: 'Estado ticket',
  id_raffle_ticket: 'ID rifa ticket',
  id_customer_ticket: 'ID cliente ticket',
  id_sale_ticket: 'ID venta ticket',
  date_created_ticket: 'Fecha creacion ticket',
  date_updated_ticket: 'Fecha actualizacion ticket',
};
const FIELD_TYPE_TRANSLATIONS = {
  string: 'texto',
  text: 'texto',
  varchar: 'texto',
  char: 'texto',
  int: 'numero',
  integer: 'numero',
  bigint: 'numero',
  float: 'numero decimal',
  double: 'numero decimal',
  decimal: 'numero decimal',
  bool: 'si/no',
  boolean: 'si/no',
  date: 'fecha',
  datetime: 'fecha y hora',
  timestamp: 'fecha y hora',
};

function postReports(action, extra = {}) {
  const fd = new FormData();
  fd.append('action', action);
  Object.entries(extra).forEach(([k, v]) => {
    if (v !== undefined && v !== null) fd.append(k, v);
  });
  return adminFetchJson(REPORTS_ENDPOINT, { body: fd });
}

function setDefaultDates() {
  const hoy = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const end = `${hoy.getFullYear()}-${pad(hoy.getMonth() + 1)}-${pad(hoy.getDate())}`;
  const start = `${hoy.getFullYear()}-${pad(hoy.getMonth() + 1)}-01`;
  document.getElementById('dateFrom').value = start;
  document.getElementById('dateTo').value = end;
}

function prettifyFieldName(name = '') {
  const normalizedName = String(name || '').trim().toLowerCase();
  if (FIELD_EXACT_LABELS[normalizedName]) {
    return FIELD_EXACT_LABELS[normalizedName];
  }

  const raw = String(name)
    .replace(/_/g, ' ')
    .replace(/-/g, ' ')
    .replace(/\./g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  const translated = raw
    .split(' ')
    .map((part) => {
      const term = part.toLowerCase().replace(/[^a-z0-9]/g, '');
      if (ENTITY_SUFFIX_TRANSLATIONS[term]) {
        return ENTITY_SUFFIX_TRANSLATIONS[term];
      }
      if (Object.prototype.hasOwnProperty.call(FIELD_TERM_TRANSLATIONS, term)) {
        return FIELD_TERM_TRANSLATIONS[term];
      }
      return term;
    })
    .filter(Boolean)
    .join(' ');
  return (translated || raw.toLowerCase())
    .replace(/\b\w/g, (ch) => ch.toUpperCase());
}

function forceSpanishLabel(label, fallbackKey = '') {
  const source = String(label || fallbackKey || '').trim();
  if (!source) return '';
  return prettifyFieldName(source);
}

function getFriendlyTypeLabel(type = '') {
  const normalized = String(type).trim().toLowerCase();
  return FIELD_TYPE_TRANSLATIONS[normalized] || normalized || 'dato';
}

function getCurrentFieldMeta(fieldKey) {
  return state.currentFields.find((f) => f.key === fieldKey) || null;
}

function getFriendlyFieldLabel(fieldKey) {
  if (fieldKey === '*') return 'Todos los registros';
  const fieldMeta = getCurrentFieldMeta(fieldKey);
  if (!fieldMeta) return forceSpanishLabel(fieldKey, fieldKey);
  const baseLabel = (fieldMeta.label || '').trim();
  if (!baseLabel) return forceSpanishLabel(fieldMeta.key, fieldKey);
  return forceSpanishLabel(baseLabel, fieldMeta.key);
}

function refreshFieldSelects() {
  const dsKey = document.getElementById('datasetSelect').value;
  const ds = state.datasets.find((d) => d.key === dsKey);
  state.currentFields = ds ? ds.fields : [];
  const opts = state.currentFields
    .map((f) => {
      const friendlyLabel = getFriendlyFieldLabel(f.key);
      return `<option value="${f.key}">${friendlyLabel} (${getFriendlyTypeLabel(f.type)})</option>`;
    })
    .join('');
  ['dimField', 'measureField', 'filterField'].forEach((id) => {
    const el = document.getElementById(id);
    el.innerHTML = opts;
  });
  document.getElementById('measureField').innerHTML =
    '<option value="*">* (solo COUNT)</option>' + opts;
}

function renderChips() {
  const dimList = document.getElementById('dimList');
  dimList.innerHTML = state.dimensions
    .map(
      (d, i) =>
        `<span class="badge bg-primary-subtle text-primary border chip-field">${
          d.alias
        }: ${getFriendlyFieldLabel(d.field)}<button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger rm-dim" data-i="${i}">×</button></span>`
    )
    .join('');

  const measureList = document.getElementById('measureList');
  measureList.innerHTML = state.measures
    .map(
      (m, i) =>
        `<span class="badge bg-success-subtle text-success border chip-field">${m.fn}(${getFriendlyFieldLabel(
          m.field
        )}) → ${m.alias}<button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger rm-m" data-i="${i}">×</button></span>`
    )
    .join('');

  const filterList = document.getElementById('filterList');
  filterList.innerHTML = state.filters
    .map(
      (f, i) =>
        `<div class="mb-1">${getFriendlyFieldLabel(f.field)} ${f.op} <code>${String(
          f.value
        )}</code> <button type="button" class="btn btn-link btn-sm p-0 text-danger rm-f" data-i="${i}">quitar</button></div>`
    )
    .join('');

  dimList.querySelectorAll('.rm-dim').forEach((b) =>
    b.addEventListener('click', () => {
      state.dimensions.splice(Number(b.dataset.i), 1);
      renderChips();
    })
  );
  measureList.querySelectorAll('.rm-m').forEach((b) =>
    b.addEventListener('click', () => {
      state.measures.splice(Number(b.dataset.i), 1);
      renderChips();
    })
  );
  filterList.querySelectorAll('.rm-f').forEach((b) =>
    b.addEventListener('click', () => {
      state.filters.splice(Number(b.dataset.i), 1);
      renderChips();
    })
  );
}

function buildSpec() {
  return {
    dataset: document.getElementById('datasetSelect').value,
    date_from: document.getElementById('dateFrom').value,
    date_to: document.getElementById('dateTo').value,
    dimensions: state.dimensions,
    measures: state.measures,
    filters: state.filters,
    order_by: document.getElementById('orderBy').value.trim(),
    order_dir: document.getElementById('orderDir').value,
    limit: Number(document.getElementById('rowLimit').value || 2000),
  };
}

function buildColumns(keys) {
  return keys.map((k) => ({
    title: k,
    field: k,
    headerFilter: 'input',
    headerSort: true,
    resizable: true,
  }));
}

function setReportLoading(isLoading) {
  const btnRun = document.getElementById('btnRun');
  const tableLoader = document.getElementById('reportTableLoader');
  const tableContainer = document.getElementById('reportTable');

  if (btnRun) {
    btnRun.disabled = isLoading;
    btnRun.innerHTML = isLoading
      ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generando reporte...'
      : DEFAULT_RUN_BUTTON_HTML;
  }

  if (tableLoader) {
    tableLoader.classList.toggle('d-none', !isLoading);
  }

  if (tableContainer) {
    tableContainer.classList.toggle('opacity-50', isLoading);
  }
}

function renderReportMobileCards(cols, rows) {
  if (typeof renderAdminMobileRows !== 'function') return;
  const fields = cols.slice(0, 6);
  renderAdminMobileRows('reportesMobile', rows.slice(0, 200).map((row, i) => {
    const first = fields[0];
    const titleVal = first ? String(row[first.field] ?? `Fila ${i + 1}`) : `Fila ${i + 1}`;
    const fieldsHtml = fields.map(c => {
      const val = row[c.field];
      const display = val === null || val === undefined ? '—' : val;
      return `<div class="admin-mobile-row__field">
        <div class="text-muted small">${adminEscapeHtml(c.title || c.field)}</div>
        <div class="fw-medium text-truncate" title="${adminEscapeHtml(String(display))}">${adminEscapeHtml(String(display))}</div>
      </div>`;
    }).join('');
    return adminMobileRow(
      adminMobileSection(`<div class="fw-bold text-dark text-truncate" title="${adminEscapeHtml(titleVal)}">${adminEscapeHtml(titleVal)}</div>`) +
      `<div class="admin-mobile-row__fields">${fieldsHtml}</div>`
    );
  }));
}

async function runQuery() {
  const spec = buildSpec();
  if (!spec.dimensions.length && !spec.measures.length) {
    alertify.error('Agrega al menos una dimensión o una medida.');
    return;
  }
  document.getElementById('resultMeta').textContent = 'Ejecutando...';
  setReportLoading(true);
  try {
    const res = await postReports('run', { spec: JSON.stringify(spec) });
    if (!res.success) {
      alertify.error(res.message || 'Error');
      document.getElementById('resultMeta').textContent = '';
      return;
    }
    const cols = buildColumns(res.columns || []);
    const data = res.rows || [];
    state.lastResult = { columns: res.columns || [], rows: data };
    if (state.table) state.table.destroy();
    state.table = new Tabulator('#reportTable', {
      data,
      columns: cols,
      layout: 'fitDataStretch',
      pagination: 'local',
      paginationSize: 50,
      movableColumns: true,
      persistenceID: 'edts_report_table',
      persistence: { columns: ['visible', 'width'] },
      height: '520px',
      placeholder: 'Sin filas',
    });
    document.getElementById('resultMeta').textContent = `${data.length} filas · ${cols.length} columnas`;
    renderReportMobileCards(cols, data);
  } catch (e) {
    alertify.error(e instanceof Error ? e.message : 'Error de red o servidor');
    document.getElementById('resultMeta').textContent = '';
  } finally {
    setReportLoading(false);
  }
}

async function loadSchema() {
  const res = await postReports('schema');
  if (!res.success) {
    alertify.error(res.message || 'No se pudo cargar esquema');
    return;
  }
  state.datasets = res.datasets || [];
  const sel = document.getElementById('datasetSelect');
  sel.innerHTML = state.datasets.map((d) => `<option value="${d.key}">${d.label}</option>`).join('');
  refreshFieldSelects();
}

async function loadPresets() {
  const res = await postReports('presets');
  if (!res.success) return;
  const ps = document.getElementById('presetSelect');
  ps.innerHTML = '<option value="">— Cargar plantilla —</option>';
  (res.presets || []).forEach((p, i) => {
    ps.innerHTML += `<option value="${i}">${p.name}</option>`;
  });
  window.__presets = res.presets || [];
}

function applyPreset() {
  const idx = document.getElementById('presetSelect').value;
  if (idx === '' || !window.__presets) return;
  const p = window.__presets[Number(idx)];
  if (!p || !p.spec) return;
  const s = p.spec;
  document.getElementById('datasetSelect').value = s.dataset;
  refreshFieldSelects();
  document.getElementById('dateFrom').value = s.date_from || '';
  document.getElementById('dateTo').value = s.date_to || '';
  state.dimensions = JSON.parse(JSON.stringify(s.dimensions || []));
  state.measures = JSON.parse(JSON.stringify(s.measures || []));
  state.filters = JSON.parse(JSON.stringify(s.filters || []));
  document.getElementById('orderBy').value = s.order_by || '';
  document.getElementById('orderDir').value = s.order_dir || 'DESC';
  document.getElementById('rowLimit').value = s.limit || 2000;
  renderChips();
  alertify.success('Plantilla aplicada');
}

async function refreshSaved() {
  const res = await postReports('saved_list');
  const sel = document.getElementById('savedSelect');
  sel.innerHTML = '<option value="">— Reportes guardados —</option>';
  if (!res.success) return;
  (res.data || []).forEach((r) => {
    sel.innerHTML += `<option value="${r.id_saved_report}">${r.name_report}</option>`;
  });
}

async function loadSaved() {
  const id = document.getElementById('savedSelect').value;
  if (!id) return;
  const res = await postReports('saved_get', { id });
  if (!res.success || !res.spec) {
    alertify.error(res.message || 'No se pudo cargar');
    return;
  }
  const s = res.spec;
  document.getElementById('datasetSelect').value = s.dataset;
  refreshFieldSelects();
  document.getElementById('dateFrom').value = s.date_from || '';
  document.getElementById('dateTo').value = s.date_to || '';
  state.dimensions = s.dimensions || [];
  state.measures = s.measures || [];
  state.filters = s.filters || [];
  document.getElementById('orderBy').value = s.order_by || '';
  document.getElementById('orderDir').value = s.order_dir || 'DESC';
  document.getElementById('rowLimit').value = s.limit || 2000;
  document.getElementById('saveName').value = res.name || '';
  renderChips();
  alertify.success('Reporte cargado');
}

async function saveReport() {
  const name = document.getElementById('saveName').value.trim();
  if (!name) {
    alertify.error('Escribe un nombre');
    return;
  }
  const spec = buildSpec();
  const res = await postReports('saved_save', { name, spec: JSON.stringify(spec) });
  if (!res.success) {
    alertify.error(res.message || 'No se pudo guardar');
    return;
  }
  alertify.success('Guardado');
  refreshSaved();
}

async function deleteSaved() {
  const id = document.getElementById('savedSelect').value;
  if (!id) return;
  alertify.confirm(
    'Eliminar',
    '¿Eliminar este reporte guardado?',
    async () => {
      const res = await postReports('saved_delete', { id });
      if (res.success) {
        alertify.success('Eliminado');
        refreshSaved();
      } else alertify.error('No autorizado o error');
    },
    () => {}
  );
}

function exportCsv() {
  if (state.table) state.table.download('csv', 'reporte_edts.csv');
  else alertify.warning('Ejecuta una consulta primero');
}

async function exportServer(action, ext, mime) {
  if (!state.lastResult || !state.lastResult.rows || !state.lastResult.rows.length) {
    alertify.warning('Ejecuta una consulta primero');
    return;
  }
  const spec = buildSpec();
  const fd = new FormData();
  fd.append('action', action);
  fd.append('spec', JSON.stringify(spec));
  fd.append('title', document.getElementById('datasetSelect')?.selectedOptions?.[0]?.text || 'reporte');
  try {
    const res = await fetch(REPORTS_ENDPOINT, { method: 'POST', body: fd });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      alertify.error(err.message || 'No autorizado o error al exportar');
      return;
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'reporte_edts.' + ext;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    alertify.error(e instanceof Error ? e.message : 'Error al exportar');
  }
}

function exportExcel() {
  exportServer('export_excel', 'xls', 'application/vnd.ms-excel');
}

function exportPdf() {
  exportServer('export_pdf', 'pdf', 'application/pdf');
}

document.addEventListener('DOMContentLoaded', async () => {
  setDefaultDates();
  try {
    await loadSchema();
    await loadPresets();
    await refreshSaved();
  } catch (e) {
    alertify.error(e instanceof Error ? e.message : 'No se pudo inicializar reportes.');
  }
  renderChips();

  document.getElementById('datasetSelect').addEventListener('change', () => {
    refreshFieldSelects();
  });

  document.getElementById('btnAddDim').addEventListener('click', () => {
    const field = document.getElementById('dimField').value;
    let alias = document.getElementById('dimAlias').value.trim() || field;
    state.dimensions.push({ field, alias });
    document.getElementById('dimAlias').value = '';
    renderChips();
  });

  document.getElementById('btnAddMeasure').addEventListener('click', () => {
    const fn = document.getElementById('measureFn').value;
    let field = document.getElementById('measureField').value;
    if (fn !== 'COUNT' && field === '*') {
      alertify.error('COUNT(*) o elige campo');
      return;
    }
    if (fn === 'COUNT' && field === '*') field = '*';
    let alias = document.getElementById('measureAlias').value.trim();
    if (!alias) alias = fn.toLowerCase() + '_' + (field === '*' ? 'n' : field);
    state.measures.push({ fn, field, alias });
    document.getElementById('measureAlias').value = '';
    renderChips();
  });

  document.getElementById('btnAddFilter').addEventListener('click', () => {
    const field = document.getElementById('filterField').value;
    const op = document.getElementById('filterOp').value;
    const value = document.getElementById('filterValue').value;
    if (value === '') {
      alertify.error('Valor requerido');
      return;
    }
    state.filters.push({ field, op, value });
    document.getElementById('filterValue').value = '';
    renderChips();
  });

  document.getElementById('btnRun').addEventListener('click', runQuery);
  document.getElementById('btnApplyPreset').addEventListener('click', applyPreset);
  document.getElementById('btnExportCsv').addEventListener('click', exportCsv);
  document.getElementById('btnExportExcel').addEventListener('click', exportExcel);
  document.getElementById('btnExportPdf').addEventListener('click', exportPdf);
  document.getElementById('btnSave').addEventListener('click', saveReport);
  document.getElementById('btnLoadSaved').addEventListener('click', loadSaved);
  document.getElementById('btnDeleteSaved').addEventListener('click', deleteSaved);
});
