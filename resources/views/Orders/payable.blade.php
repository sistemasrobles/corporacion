@extends('layouts.sistema')

@section('title', 'Cuentas por Pagar')
@section('page', 'cuentas')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Cuentas por Pagar')
@section('breadcrumb', 'Cuentas por Pagar')

@push('styles')
<style>
    .filtros .row        { margin-bottom: 0; }
    .filtros .form-group { margin-bottom: 10px; }
    /* Tabla de abonos: ancho mínimo + scroll horizontal para que no se apilen las columnas */
    #tabla-cxp { min-width: 1280px; }
    #tabla-cxp th, #tabla-cxp td { white-space: nowrap; vertical-align: middle; }
    #tabla-cxp td.obs-cell { white-space: normal; }   /* la observación sí puede envolver */
    .dep-acc { display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border-color);border-radius:var(--radius);cursor:pointer;transition:border-color .12s, background .12s; }
    .dep-acc:hover { border-color:var(--primary); background:#f8fafc; }
    .dep-acc input { width:18px;height:18px;flex-shrink:0;accent-color:var(--primary); }
    .dep-acc-body { display:flex;flex-direction:column;gap:2px; }
    .dep-acc-name { font-weight:600;font-size:14px; }
    .dep-acc-meta { font-size:12px;color:var(--text-muted); }
    .dep-acc:has(input:checked) { border-color:var(--primary);background:#eff6ff; }
    .dep-acc-off { opacity:.55;cursor:not-allowed; }
    .dep-acc-off:hover { border-color:var(--border-color);background:transparent; }
    /* Caja informativa de la cuota (modales) */
    .cuota-info { display:grid;grid-template-columns:repeat(2,1fr);gap:10px 16px;background:var(--bg-surface-secondary);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:12px 14px;margin-bottom:16px; }
    .cuota-info .ci-item { display:flex;flex-direction:column;gap:1px; }
    .cuota-info .ci-l { font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);font-weight:600; }
    .cuota-info .ci-v { font-size:13.5px;font-weight:600;color:var(--text); }
    /* Dropzone compacto */
    .dz-compact { min-height: 120px; padding: 20px; }
    .dz-compact svg { width: 28px; height: 28px; margin-bottom: 6px; }
    /* Modal de detalle de orden (solo lectura) */
    #modal-order-detail .modal-dialog { max-width: 880px; }
    .sum-fs { border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:16px 18px 10px; margin-bottom:16px; }
    .sum-fs > legend { font-size:11px; font-weight:700; color:var(--text-secondary); padding:0 8px; text-transform:uppercase; letter-spacing:.4px; }
    .sum-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px 18px; }
    .sum-l { display:block; font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); font-weight:600; margin-bottom:2px; }
    .sum-v { display:block; font-size:13.5px; color:var(--text); font-weight:500; line-height:1.4; }
    .sum-just { margin-top:14px; }
    @media (max-width: 720px) { .sum-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
@endpush

@section('content')

    {{-- ── Filtros ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form id="filtros-cxp" class="filtros" onsubmit="return false">
                <div class="row col-4">
                    <div class="form-group">
                        <label class="form-label">Programación</label>
                        <select name="payment_schedule_id" class="form-control">
                            <option value="">Todas las programaciones</option>
                            @foreach ($schedules as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de orden</label>
                        <select name="format_id" class="form-control">
                            <option value="">Todos los tipos</option>
                            @foreach ($tipos as $abrev => $desc)
                                <option value="{{ $abrev }}">{{ $desc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Empresa</label>
                        <select name="company" class="form-control">
                            <option value="">Todas las empresas</option>
                            @foreach ($companies as $co)
                                <option value="{{ $co->id }}">{{ $co->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado del abono</label>
                        <select name="status" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="200">Pendiente por depósito</option>
                            <option value="201">Depositado</option>
                            <option value="202">Constancia adjuntada</option>
                        </select>
                    </div>
                </div>

                <div class="row col-4">
                    <div class="form-group">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-control">
                            <option value="">Todas las monedas</option>
                            @foreach ($currencies as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rango de vencimiento</label>
                        <div class="date-range" data-date-range id="date-range">
                            <input type="text" class="form-control" placeholder="Selecciona un rango..." readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Orden, cuota, cuenta..." autocomplete="off">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="button" id="btn-recargar" class="btn btn-outline" title="Limpia los filtros y trae datos frescos de la base de datos">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13.5 8a5.5 5.5 0 11-1.6-3.9M13.5 2.5V5H11"/></svg>
                            Recargar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="overflow-x:auto">
                <table class="table" id="tabla-cxp">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Programación</th>
                            <th>Cuota</th>
                            <th>Monto</th>
                            <th>Vencimiento</th>
                            <th>Estado abono</th>
                            <th>Observación</th>
                            <th>Banco origen</th>
                            <th>Cuenta origen</th>
                            <th>N° Operación</th>
                            <th>Constancia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cxp-body">
                        @include('Orders.partials.payable-rows')
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Subir constancia ── --}}
    <div class="modal-backdrop" id="modal-constancia" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Subir constancia <span id="cons-info" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-constancia" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="cuota-info" id="cons-cuota-info">
                    <div class="ci-item"><span class="ci-l">Orden</span><span class="ci-v" data-fld="code">—</span></div>
                    <div class="ci-item"><span class="ci-l">Cuota</span><span class="ci-v" data-fld="num">—</span></div>
                    <div class="ci-item"><span class="ci-l">Monto</span><span class="ci-v" data-fld="amount">—</span></div>
                    <div class="ci-item"><span class="ci-l">Vencimiento</span><span class="ci-v" data-fld="due">—</span></div>
                    <div class="ci-item" style="grid-column:span 2"><span class="ci-l">Programación</span><span class="ci-v" data-fld="sched">—</span></div>
                </div>
                <div class="form-group">
                    <label class="form-label">N° de operación <span class="required">*</span></label>
                    <input type="text" id="cons-op" class="form-control" placeholder="Ej. 0098765432" autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Constancia / Voucher del depósito <span class="required">*</span></label>
                    <label class="dropzone dz-compact" id="cons-dz" for="cons-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Suelta el archivo o haz clic para buscar</div>
                        <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                        <input type="file" id="cons-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-constancia">Cancelar</button>
                <button type="button" id="cons-save" class="btn btn-primary">Subir constancia</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Registrar depósito (cuenta de origen) ── --}}
    <div class="modal-backdrop" id="modal-deposit" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Registrar depósito <span id="dep-info" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-deposit" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="cuota-info" id="dep-cuota-info">
                    <div class="ci-item"><span class="ci-l">Orden</span><span class="ci-v" data-fld="code">—</span></div>
                    <div class="ci-item"><span class="ci-l">Cuota</span><span class="ci-v" data-fld="num">—</span></div>
                    <div class="ci-item"><span class="ci-l">Monto</span><span class="ci-v" data-fld="amount">—</span></div>
                    <div class="ci-item"><span class="ci-l">Vencimiento</span><span class="ci-v" data-fld="due">—</span></div>
                    <div class="ci-item" style="grid-column:span 2"><span class="ci-l">Programación</span><span class="ci-v" data-fld="sched">—</span></div>
                </div>
                <label class="form-label">Cuenta de origen — ¿desde qué empresa sale el dinero? <span class="required">*</span></label>
                <div id="dep-accounts" style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
                    @forelse ($companies as $co)
                        @php $hasAcc = (bool) $co->source_account_number; @endphp
                        <label class="dep-acc {{ $hasAcc ? '' : 'dep-acc-off' }}">
                            <input type="radio" name="dep-company" value="{{ $co->id }}" {{ $hasAcc ? '' : 'disabled' }}>
                            <span class="dep-acc-body">
                                <span class="dep-acc-name">{{ $co->name }}</span>
                                @if ($hasAcc)
                                    <span class="dep-acc-meta">{{ $co->source_bank ?: 'Banco no especificado' }} · Cta. {{ $co->source_account_number }}@if ($co->source_cci) · CCI {{ $co->source_cci }}@endif</span>
                                @else
                                    <span class="dep-acc-meta" style="color:var(--red)">Sin cuenta bancaria registrada</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <div style="padding:14px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">
                            No hay empresas con datos bancarios registrados.
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-deposit">Cancelar</button>
                <button type="button" id="dep-save" class="btn btn-primary">Confirmar depósito</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Observar abono ── --}}
    <div class="modal-backdrop" id="modal-abono-obs" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Observar abono <span id="abobs-info" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-abono-obs" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Motivo de la observación <span class="required">*</span></label>
                    <textarea id="abobs-reason" class="form-control" rows="3" placeholder="Detalla el problema con el depósito/constancia..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-abono-obs">Cancelar</button>
                <button type="button" id="abobs-save" class="btn btn-primary">Enviar observación</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Ver constancia (visor de archivo) ── --}}
    <div class="modal-backdrop" id="modal-constancia-ver" style="display:none">
        <div class="modal-dialog" style="max-width:900px">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Constancia <span id="cv-info" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-constancia-ver" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body" id="cv-body" style="min-height:200px;background:var(--bg-surface-secondary)"></div>
            <div class="modal-footer">
                <a id="cv-open" href="#" target="_blank" rel="noopener" class="btn btn-outline">Abrir en pestaña</a>
                <button type="button" class="btn btn-primary" data-close="modal-constancia-ver">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Detalle de la orden (solo lectura) ── --}}
    <div class="modal-backdrop" id="modal-order-detail" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Detalle de la orden <span id="od-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-order-detail" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body" id="od-body" style="min-height:160px"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-order-detail">Cerrar</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="/src/date-range.js?v={{ @filemtime(public_path('src/date-range.js')) ?: time() }}"></script>
<script>
(function () {
    const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
    const tableEl = document.getElementById('tabla-cxp');
    const BASE    = "{{ url('orders/abono') }}";
    let currentId = null;

    // DataTables: paginación de 20 + buscador
    const tableConfig = {
        pageLength: 20,
        order: [],
        columnDefs: [{ targets: -1, orderable: false }],
        layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin abonos', infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron abonos',
            emptyTable: 'No hay abonos para gestionar',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    };
    let dt = new DataTable('#tabla-cxp', tableConfig);

    // ── Filtros client-side (empresa, programación, tipo, rango de vencimiento) ──
    const form = document.getElementById('filtros-cxp');
    let rangeFrom = null, rangeTo = null;

    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-cxp') return true;
        const row    = settings.aoData[dataIndex].nTr;   // tabla en dibujo (sobrevive al re-init)
        if (!row) return true;
        const fSched = form.elements['payment_schedule_id'].value;
        const fFmt   = form.elements['format_id'].value;
        const fComp  = form.elements['company'].value;
        const fStat  = form.elements['status'].value;
        const fCurr  = form.elements['currency'].value;
        if (fSched && row.dataset.schedule !== fSched) return false;
        if (fFmt   && row.dataset.format   !== fFmt)   return false;
        if (fComp  && row.dataset.company  !== fComp)  return false;
        if (fStat  && row.dataset.status   !== fStat)  return false;
        if (fCurr  && row.dataset.currency !== fCurr)  return false;
        if (rangeFrom && rangeTo && row.dataset.due) {
            const d = new Date(row.dataset.due + 'T00:00:00');
            if (d < rangeFrom || d > rangeTo) return false;
        }
        return true;
    });

    form.querySelectorAll('select').forEach(sel => sel.addEventListener('change', () => dt.draw()));
    form.elements['q'].addEventListener('input', function () { dt.search(this.value).draw(); });

    // Rango de fecha → filtra por vencimiento de la cuota
    const dr = document.getElementById('date-range');
    dr.addEventListener('change', (e) => {
        const f = e.detail.from, t = e.detail.to;
        rangeFrom = f ? new Date(f.getFullYear(), f.getMonth(), f.getDate()) : null;
        rangeTo   = t ? new Date(t.getFullYear(), t.getMonth(), t.getDate(), 23, 59, 59) : null;
        dt.draw();
    });

    function showToast(message, variant = 'success', timeout = 3200) {
        let host = document.querySelector('.toast-host');
        if (!host) { host = document.createElement('div'); host.className = 'toast-host'; host.style.zIndex = 2000; document.body.appendChild(host); }
        const t = document.createElement('div');
        t.className = 'toast toast-' + variant; t.textContent = message;
        host.appendChild(t); requestAnimationFrame(() => t.classList.add('show'));
        const close = () => { t.classList.remove('show'); setTimeout(() => t.remove(), 200); };
        const timer = setTimeout(close, timeout);
        t.addEventListener('click', () => { clearTimeout(timer); close(); });
    }
    function openM(id) { const el = document.getElementById(id); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); }
    function closeM(id) { const el = document.getElementById(id); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); }
    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]');
        if (c) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && ['modal-constancia', 'modal-deposit', 'modal-abono-obs', 'modal-order-detail', 'modal-constancia-ver'].includes(e.target.id)) closeM(e.target.id);
    });

    async function post(url, opts = {}) {
        const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, ...(opts.headers || {}) }, body: opts.body ?? null });
        return { ok: res.ok, json: await res.json().catch(() => ({})) };
    }
    function done(r, errMsg) {
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location.reload(), 900); return true; }
        showToast(r.json.description || errMsg, 'error'); return false;
    }

    // Rellena la caja informativa de la cuota desde los data-* del botón
    function fillCuotaInfo(infoId, btn) {
        const box = document.getElementById(infoId);
        box.querySelector('[data-fld=code]').textContent   = btn.dataset.code  || '—';
        box.querySelector('[data-fld=num]').textContent    = 'Cuota ' + (btn.dataset.num || '—');
        box.querySelector('[data-fld=amount]').textContent = btn.dataset.amount || '—';
        box.querySelector('[data-fld=due]').textContent    = btn.dataset.due    || '—';
        box.querySelector('[data-fld=sched]').textContent  = btn.dataset.sched  || '—';
    }

    // Dropzone del tema: arrastrar/soltar + mostrar nombre del archivo
    function initDropzone(dzId, defaultHint) {
        const dz = document.getElementById(dzId);
        if (!dz) return;
        const hint = dz.querySelector('.hint');
        const getInput = () => dz.querySelector('input[type=file]');
        const showName = () => { const i = getInput(); hint.textContent = (i && i.files.length) ? i.files[0].name : defaultHint; };
        dz.addEventListener('change', showName);
        dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', (e) => {
            e.preventDefault(); dz.classList.remove('over');
            const i = getInput();
            if (i && e.dataTransfer.files.length) { i.files = e.dataTransfer.files; showName(); }
        });
        dz.__resetHint = () => { hint.textContent = defaultHint; };
    }
    initDropzone('cons-dz', 'Suelta el archivo o haz clic para buscar');

    // Recargar: trae abonos frescos de la BD y refresca la tabla (sin recargar la página)
    document.getElementById('btn-recargar').addEventListener('click', async function () {
        this.disabled = true;
        window.blockUI && window.blockUI('Actualizando…');
        try {
            const res = await fetch("{{ route('orders.payable.rows') }}?_=" + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) {
                dt.destroy();
                document.getElementById('cxp-body').innerHTML = j.data.html;
                dt = new DataTable('#tabla-cxp', tableConfig);   // re-inicializa PRIMERO
                form.reset();                                    // luego limpia los filtros
                if (dr.__clear) dr.__clear();                    // su 'change' ya golpea a la tabla nueva
                rangeFrom = rangeTo = null;
                dt.search('').draw();
            } else if (res.status === 419) {
                showToast('Tu sesión expiró. Recarga la página (F5).', 'error');
            } else {
                showToast(j.description || 'No se pudo recargar.', 'error');
            }
        } catch (e) { showToast('Error de red al recargar.', 'error'); }
        window.unblockUI && window.unblockUI();
        this.disabled = false;
    });

    // ── Detalle de la orden (solo lectura) ──
    const esc = (x) => String(x ?? '—').replace(/[&<>"]/g, ch => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ch]));
    function renderOrderDetail(d) {
        const g = d.general, c = d.condicion, t = d.totales, p = d.proveedor, cuotas = d.cuotas || [];
        const cell = (l, v) => `<div><span class="sum-l">${l}</span><span class="sum-v">${esc(v)}</span></div>`;

        let prov = '';
        if (p) {
            const cta = p.cuenta ? cell('Banco', p.cuenta.banco) + cell('N° de cuenta', p.cuenta.numero) + cell('Moneda', p.cuenta.moneda) : '';
            prov = `<fieldset class="sum-fs"><legend>Proveedor</legend><div class="sum-grid">${cell('RUC', p.ruc)}${cell('Razón social', p.razon_social)}${cell('Celular', p.celular || '—')}${cta}</div></fieldset>`;
        }

        let cond = cell('Forma de pago', c.forma_pago) + cell('Condición', c.condicion) + cell('Programación', c.programacion);
        if (!d.es_fraccionado) cond += cell('Vencimiento', c.vencimiento);

        const abCls = (id) => id === 202 ? 'status-green' : (id === 201 ? 'status-blue' : 'status-yellow');
        const filas = cuotas.length
            ? cuotas.map(q => `<tr><td class="cell-strong">Cuota ${q.numero}</td><td>${esc(q.fecha)}</td><td class="cell-strong" style="text-align:right">${esc(q.monto)}</td><td><span class="status ${abCls(q.estado_id)}">${esc(q.estado)}</span></td><td class="cell-mono">${q.operacion ? esc(q.operacion) : '—'}</td></tr>`).join('')
            : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:10px">Sin cronograma</td></tr>`;

        return `
            <fieldset class="sum-fs"><legend>Datos generales</legend>
                <div class="sum-grid">
                    ${cell('Empresa', g.empresa)}${cell('Tipo de orden', g.tipo)}${cell('Categoría', g.categoria)}${cell('Moneda', g.moneda)}
                    ${cell('Título', g.titulo)}${cell('Sede', g.sede)}${cell('Área', g.area)}${cell('Centros de costo', g.cc)}
                </div>
                <div class="sum-just"><span class="sum-l">Justificación</span><span class="sum-v">${esc(g.justificacion)}</span></div>
            </fieldset>
            ${prov}
            <fieldset class="sum-fs"><legend>Condición de pago</legend>
                <div class="sum-grid">${cond}${cell('Subtotal', t.subtotal)}${cell('IGV', t.igv)}${cell('Descuento', t.descuento)}${cell('Total', t.total)}</div>
            </fieldset>
            <fieldset class="sum-fs" style="margin-bottom:0"><legend>Cronograma de pagos</legend>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Cuota</th><th>Vencimiento</th><th style="text-align:right">Monto</th><th>Estado abono</th><th>N° Operación</th></tr></thead>
                    <tbody>${filas}</tbody>
                </table></div>
            </fieldset>`;
    }

    // Ver constancia en modal (imagen embebida o PDF en iframe)
    function openConstancia(url, code, num) {
        const isPdf = /\.pdf(\?|$)/i.test(url);
        document.getElementById('cv-info').textContent = `${code} · Cuota ${num}`;
        document.getElementById('cv-open').href = url;
        document.getElementById('cv-body').innerHTML = isPdf
            ? `<iframe src="${url}" style="width:100%;height:75vh;border:0;display:block"></iframe>`
            : `<img src="${url}" alt="Constancia" style="max-width:100%;display:block;margin:0 auto">`;
        openM('modal-constancia-ver');
    }

    // Delegación de botones
    tableEl.addEventListener('click', async (e) => {
        const cv = e.target.closest('.constancia-link');
        if (cv) {
            e.preventDefault();
            openConstancia(cv.dataset.url, cv.dataset.code, cv.dataset.num);
            return;
        }

        const link = e.target.closest('.order-link');
        if (link) {
            e.preventDefault();
            document.getElementById('od-code').textContent = link.dataset.code || '';
            document.getElementById('od-body').innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted)">Cargando…</div>';
            openM('modal-order-detail');
            try {
                const res = await fetch(`{{ url('orders') }}/${link.dataset.id}/summary?_=` + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                const j   = await res.json();
                document.getElementById('od-body').innerHTML = (res.ok && j.status === 1)
                    ? renderOrderDetail(j.data)
                    : '<div style="padding:30px;text-align:center;color:var(--red)">No se pudo cargar el detalle.</div>';
            } catch (err) {
                document.getElementById('od-body').innerHTML = '<div style="padding:30px;text-align:center;color:var(--red)">Error al cargar el detalle.</div>';
            }
            return;
        }

        const dep = e.target.closest('.ab-deposit');
        if (dep) {
            currentId = dep.dataset.id;
            document.getElementById('dep-info').textContent = `${dep.dataset.code} · Cuota ${dep.dataset.num}`;
            fillCuotaInfo('dep-cuota-info', dep);
            document.querySelectorAll('input[name="dep-company"]').forEach(r => { r.checked = false; });
            openM('modal-deposit');
            return;
        }

        const ver = e.target.closest('.ab-verify');
        if (ver) { ver.disabled = true; const r = await post(`${BASE}/${ver.dataset.id}/verify`); if (!done(r, 'No se pudo verificar.')) ver.disabled = false; return; }

        const con = e.target.closest('.ab-constancia');
        if (con) {
            currentId = con.dataset.id;
            document.getElementById('cons-info').textContent = `${con.dataset.code} · Cuota ${con.dataset.num}`;
            fillCuotaInfo('cons-cuota-info', con);
            document.getElementById('cons-file').value = '';
            document.getElementById('cons-op').value = '';
            const dz = document.getElementById('cons-dz'); dz.__resetHint && dz.__resetHint();
            openM('modal-constancia');
            return;
        }

        const obs = e.target.closest('.ab-observe');
        if (obs) { currentId = obs.dataset.id; document.getElementById('abobs-info').textContent = `${obs.dataset.code} · Cuota ${obs.dataset.num}`; document.getElementById('abobs-reason').value = ''; openM('modal-abono-obs'); return; }
    });

    // Registrar depósito (cuenta de origen)
    document.getElementById('dep-save').addEventListener('click', async function () {
        const sel = document.querySelector('input[name="dep-company"]:checked');
        if (!sel) { showToast('Selecciona la cuenta de origen.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Registrando...';
        const r = await post(`${BASE}/${currentId}/deposit`, {
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ source_company_id: sel.value }),
        });
        if (!done(r, 'No se pudo registrar el depósito.')) { this.disabled = false; this.textContent = 'Confirmar depósito'; }
    });

    // Subir constancia
    document.getElementById('cons-save').addEventListener('click', async function () {
        const file = document.getElementById('cons-file').files[0];
        const op   = document.getElementById('cons-op').value.trim();
        if (!op) { showToast('Indica el N° de operación.', 'warning'); return; }
        if (!file) { showToast('Adjunta la constancia.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Subiendo...';
        const fd = new FormData(); fd.append('constancia', file); fd.append('operation_number', op);
        const r = await post(`${BASE}/${currentId}/constancia`, { body: fd });
        if (!done(r, 'No se pudo subir la constancia.')) { this.disabled = false; this.textContent = 'Subir constancia'; }
    });

    // Observar abono
    document.getElementById('abobs-save').addEventListener('click', async function () {
        const reason = document.getElementById('abobs-reason').value.trim();
        if (!reason) { showToast('Indica el motivo.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Enviando...';
        const r = await post(`${BASE}/${currentId}/observe`, { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ observacion: reason }) });
        if (!done(r, 'No se pudo observar.')) { this.disabled = false; this.textContent = 'Enviar observación'; }
    });
})();
</script>
@endpush