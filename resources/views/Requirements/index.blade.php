@extends('layouts.sistema')

@section('title', 'Órdenes de Requerimientos')
@section('page', 'requirements')

@section('page-pretitle', 'Requerimientos')
@section('page-title', 'Órdenes de Requerimientos')
@section('breadcrumb', 'Órdenes de Requerimientos')

@if ($canCreate)
@section('page-actions')
    <a href="{{ route('requirements.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
        Crear orden
    </a>
@endsection
@endif

@push('styles')
<style>
    .filtros .row        { margin-bottom: 0; }
    .filtros .form-group { margin-bottom: 10px; }
    .dz-compact { min-height: 110px; padding: 18px; }
    .dz-compact svg { width: 26px; height: 26px; margin-bottom: 6px; }
</style>
@endpush

@section('content')

    {{-- ── Filtros ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form id="filtros-req" class="filtros" onsubmit="return false">
                <div class="row col-3">
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-control">
                            <option value="">Todos los estados</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->id }}">{{ $st->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Código, título, empresa, beneficiario..." autocomplete="off">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="button" id="btn-recargar" class="btn btn-outline" title="Trae datos frescos de la base de datos">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13.5 8a5.5 5.5 0 11-1.6-3.9M13.5 2.5V5H11"/></svg>
                            Recargar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Tabla ── --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="tabla-req" style="width:100%">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Beneficiario</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th style="text-align:center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="req-body">
                        @include('Requirements.partials.refund-rows')
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal motivo (observar / rechazar) usado por los botones del GA --}}
    <div class="modal-backdrop" id="modal-reason" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff"><span id="reason-title">Observar orden</span> <span id="reason-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-reason" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="banner banner-danger" id="reason-banner" style="margin-bottom:14px;display:none">
                    <svg class="banner-icon" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5 5l6 6M11 5l-6 6"/></svg>
                    <div class="banner-body">El rechazo es <strong>definitivo</strong>. La orden quedará como RECHAZADA.</div>
                </div>
                <div class="form-group" id="reason-type-wrap">
                    <label class="form-label">Tipo de observación <span class="required">*</span></label>
                    <select id="reason-type" class="form-control">
                        <option value="">Seleccione...</option>
                        @foreach ($obsTypes as $val => $desc)<option value="{{ $val }}">{{ $desc }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label" id="reason-label">Motivo <span class="required">*</span></label>
                    <textarea id="reason-text" class="form-control" rows="3" maxlength="1000" placeholder="Escribe el motivo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-reason">Cancelar</button>
                <button type="button" class="btn btn-primary" id="reason-confirm">Confirmar</button>
            </div>
        </div>
    </div>

    {{-- Modal de confirmación (reemplaza al confirm() nativo) --}}
    <div class="modal-backdrop" id="modal-confirm" style="display:none">
        <div class="modal-dialog modal-sm">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff" id="confirm-title">Confirmar</h3>
                <button type="button" class="modal-close" data-close="modal-confirm" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body"><p id="confirm-msg" style="margin:0;font-size:14px;line-height:1.5"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-confirm">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirm-ok">Confirmar</button>
            </div>
        </div>
    </div>

    @php $sourceCompanies = $companies ?? collect(); @endphp

    {{-- Modal: registrar abono (GF) --}}
    <div class="modal-backdrop" id="modal-abono" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff">Registrar abono <span id="ab-code" style="opacity:.7;font-weight:400"></span></h3><button type="button" class="modal-close" data-close="modal-abono" style="color:rgba(255,255,255,.7)">×</button></div>
            <div class="modal-body">
                <div id="ab-info" style="background:var(--bg-surface-secondary);border-radius:var(--radius);padding:10px 12px;margin-bottom:14px;font-size:13px"></div>
                <div class="form-group"><label class="form-label">Empresa de origen <span class="required">*</span></label>
                    <select id="ab-company" class="form-control"><option value="">Seleccione...</option>@foreach ($sourceCompanies as $co)<option value="{{ $co->id }}">{{ $co->name }} — {{ $co->source_bank }} {{ $co->source_account_number }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">Fecha de abono <span class="required">*</span></label><input type="date" id="ab-date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div class="form-group" style="margin-bottom:0"><label class="form-label">N° de operación <span class="required">*</span></label><input type="text" id="ab-txn" class="form-control" maxlength="100"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-abono">Cancelar</button><button type="button" class="btn btn-primary" id="ab-confirm">Registrar abono</button></div>
        </div>
    </div>

    {{-- Modal: adjuntar constancia (GF/AF) --}}
    <div class="modal-backdrop" id="modal-cons" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff">Adjuntar constancia <span id="co-code" style="opacity:.7;font-weight:400"></span></h3><button type="button" class="modal-close" data-close="modal-cons" style="color:rgba(255,255,255,.7)">×</button></div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">N° de operación <small style="color:var(--text-muted)">(del abono)</small></label><input type="text" id="co-op" class="form-control ro-field" readonly></div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Constancia (PDF/imagen) <span class="required">*</span></label>
                    <label class="dropzone dz-compact" id="co-dz" for="co-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Suelta el archivo o haz clic para buscar</div>
                        <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                        <input type="file" id="co-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-cons">Cancelar</button><button type="button" class="btn btn-primary" id="co-confirm">Adjuntar</button></div>
        </div>
    </div>

    {{-- Modal: registrar reembolso (GF) --}}
    <div class="modal-backdrop" id="modal-reem" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff">Registrar reembolso <span id="re-code" style="opacity:.7;font-weight:400"></span></h3><button type="button" class="modal-close" data-close="modal-reem" style="color:rgba(255,255,255,.7)">×</button></div>
            <div class="modal-body">
                <div id="re-info" style="background:var(--bg-surface-secondary);border-radius:var(--radius);padding:10px 12px;margin-bottom:14px;font-size:13px"></div>
                <div class="form-group"><label class="form-label">Empresa de origen <span class="required">*</span></label>
                    <select id="re-company" class="form-control"><option value="">Seleccione...</option>@foreach ($sourceCompanies as $co)<option value="{{ $co->id }}">{{ $co->name }} — {{ $co->source_bank }} {{ $co->source_account_number }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">Fecha <span class="required">*</span></label><input type="date" id="re-date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div class="form-group"><label class="form-label">N° de operación</label><input type="text" id="re-txn" class="form-control" maxlength="100"></div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Constancia <span class="required">*</span></label>
                    <label class="dropzone dz-compact" id="re-dz" for="re-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Suelta el archivo o haz clic para buscar</div>
                        <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                        <input type="file" id="re-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-reem">Cancelar</button><button type="button" class="btn btn-primary" id="re-confirm">Registrar reembolso</button></div>
        </div>
    </div>

    {{-- Modal: registrar devolución (AA) --}}
    <div class="modal-backdrop" id="modal-dev" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff">Registrar devolución <span id="de-code" style="opacity:.7;font-weight:400"></span></h3><button type="button" class="modal-close" data-close="modal-dev" style="color:rgba(255,255,255,.7)">×</button></div>
            <div class="modal-body">
                <div id="de-info" style="background:var(--bg-surface-secondary);border-radius:var(--radius);padding:10px 12px;margin-bottom:14px;font-size:13px"></div>
                <div class="form-group"><label class="form-label">Fecha <span class="required">*</span></label><input type="date" id="de-date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div class="form-group"><label class="form-label">N° de operación</label><input type="text" id="de-txn" class="form-control" maxlength="100"></div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Constancia <span class="required">*</span></label>
                    <label class="dropzone dz-compact" id="de-dz" for="de-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Suelta el archivo o haz clic para buscar</div>
                        <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                        <input type="file" id="de-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-dev">Cancelar</button><button type="button" class="btn btn-primary" id="de-confirm">Registrar devolución</button></div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script>
(function () {
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

    const form = document.getElementById('filtros-req');
    const tableConfig = {
        pageLength: 15,
        order: [],
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],   // columna Acciones
        layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin órdenes', infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron órdenes',
            emptyTable: 'No hay órdenes de requerimiento',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    };
    let dt = new DataTable('#tabla-req', tableConfig);

    // Filtro por estado (lee data-status de cada fila)
    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-req') return true;
        const row = settings.aoData[dataIndex].nTr;
        if (!row) return true;
        const fStat = form.elements['status'].value;
        if (fStat !== '' && row.dataset.status !== fStat) return false;
        return true;
    });
    form.elements['status'].addEventListener('change', () => dt.draw());
    form.elements['q'].addEventListener('input', function () { dt.search(this.value).draw(); });

    // Recargar: trae datos frescos de la BD (sin recargar la página)
    async function reloadRows(resetFilters = true) {
        try {
            const res = await fetch("{{ route('requirements.rows') }}?_=" + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) {
                dt.destroy();
                document.getElementById('req-body').innerHTML = j.data.html;
                dt = new DataTable('#tabla-req', tableConfig);
                if (resetFilters) { form.reset(); }
                dt.search(resetFilters ? '' : form.elements['q'].value).draw();
                return true;
            }
            showToast(j.description || 'No se pudo recargar.', 'error');
        } catch (e) { showToast('Error de red al recargar.', 'error'); }
        return false;
    }
    document.getElementById('btn-recargar').addEventListener('click', async function () {
        this.disabled = true; window.blockUI && window.blockUI('Actualizando…');
        await reloadRows(true);
        window.unblockUI && window.unblockUI(); this.disabled = false;
    });

    // ── Acciones del GA en la lista (aprobar / observar / rechazar) ──
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const BASE = "{{ url('requirements') }}";
    const $ = (id) => document.getElementById(id);
    const rmodal = $('modal-reason');
    const openReason  = () => { rmodal.style.display = 'flex'; requestAnimationFrame(() => rmodal.classList.add('show')); document.body.classList.add('modal-open'); };
    const closeReason = () => { rmodal.classList.remove('show'); setTimeout(() => rmodal.style.display = 'none', 180); document.body.classList.remove('modal-open'); };
    document.addEventListener('click', (e) => { if (e.target.closest('[data-close="modal-reason"]') || e.target === rmodal) closeReason(); });

    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
    const money = (v, cur) => (cur === 'USD' ? '$ ' : 'S/ ') + (parseFloat(v) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const openM  = (m) => { const el = $(m); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); };
    const closeM = (m) => { const el = $(m); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); };
    const INPUT_MODALS = ['modal-abono', 'modal-cons', 'modal-reem', 'modal-dev'];
    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]'); if (c && INPUT_MODALS.includes(c.dataset.close)) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && INPUT_MODALS.includes(e.target.id)) closeM(e.target.id);
    });

    // Dropzone (drag & drop) para los inputs de archivo
    function initDropzone(dzId, hintDefault) {
        const dz = document.getElementById(dzId); if (!dz) return;
        const hint = dz.querySelector('.hint');
        const input = () => dz.querySelector('input[type=file]');
        const show = () => { const i = input(); hint.textContent = (i && i.files.length) ? i.files[0].name : hintDefault; };
        dz.addEventListener('change', show);
        dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', (e) => { e.preventDefault(); dz.classList.remove('over'); const i = input(); if (i && e.dataTransfer.files.length) { i.files = e.dataTransfer.files; show(); } });
        dz.__reset = () => { hint.textContent = hintDefault; };
    }
    ['co-dz', 're-dz', 'de-dz'].forEach(id => initDropzone(id, 'Suelta el archivo o haz clic para buscar'));

    async function doAction(url, body, isForm) {
        window.blockUI && window.blockUI('Procesando…');
        try {
            const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };
            if (!isForm) headers['Content-Type'] = 'application/json';
            const res = await fetch(url, { method: 'POST', headers, body: isForm ? body : (body ? JSON.stringify(body) : '{}') });
            const j = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) { showToast(j.description, 'success'); await reloadRows(false); }
            else { showToast(j.description || (j.errors && Object.values(j.errors)[0]?.[0]) || 'No se pudo completar.', res.status === 409 ? 'warning' : 'error'); }
        } catch (e) { showToast('Error de red.', 'error'); }
        window.unblockUI && window.unblockUI();
    }

    // ── Confirmación con estilo (reemplaza al confirm() nativo) ──
    const cmodal = $('modal-confirm');
    let confirmResolver = null;
    function askConfirm(message, opts = {}) {
        $('confirm-title').textContent = opts.title || 'Confirmar';
        $('confirm-msg').textContent = message;
        const ok = $('confirm-ok');
        ok.className = 'btn btn-' + (opts.variant || 'primary');
        ok.textContent = opts.okLabel || 'Confirmar';
        cmodal.style.display = 'flex'; requestAnimationFrame(() => cmodal.classList.add('show')); document.body.classList.add('modal-open');
        return new Promise((resolve) => { confirmResolver = resolve; });
    }
    function settleConfirm(val) {
        cmodal.classList.remove('show'); setTimeout(() => cmodal.style.display = 'none', 180); document.body.classList.remove('modal-open');
        if (confirmResolver) { confirmResolver(val); confirmResolver = null; }
    }
    $('confirm-ok').addEventListener('click', () => settleConfirm(true));
    document.addEventListener('click', (e) => { if (e.target.closest('[data-close="modal-confirm"]') || e.target === cmodal) settleConfirm(false); });

    let pending = null;   // { id, act, code }
    const TODAY = "{{ now()->toDateString() }}";

    function openReasonFor(act, id, code) {
        pending = { id, act, code };
        const isReject = act === 'reject';
        $('reason-title').textContent = isReject ? 'Rechazar orden' : 'Observar orden';
        $('reason-code').textContent = code;
        $('reason-label').innerHTML = (isReject ? 'Motivo del rechazo' : 'Motivo de la observación') + ' <span class="required">*</span>';
        $('reason-banner').style.display = isReject ? 'flex' : 'none';
        $('reason-type-wrap').style.display = isReject ? 'none' : '';
        $('reason-type').value = '';
        const cf = $('reason-confirm');
        cf.className = 'btn ' + (isReject ? 'btn-danger' : 'btn-primary');
        cf.textContent = isReject ? 'Confirmar rechazo' : 'Enviar observación';
        $('reason-text').value = '';
        openReason(); setTimeout(() => $('reason-text').focus(), 100);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.act-btn');
        if (!btn) return;
        const d = btn.dataset, id = d.id, act = d.act, code = d.code;
        const url = (a) => `${BASE}/${id}/${a}`;
        switch (act) {
            case 'approve':
                askConfirm(`¿Aprobar la orden ${code}? Pasará al GF para el abono.`, { title: 'Aprobar orden', okLabel: 'Aprobar' }).then(ok => { if (ok) doAction(url('approve')); });
                break;
            case 'conforme':
                askConfirm(`¿Dar conforme a la rendición de ${code}? Pasará a cierre (UC1).`, { title: 'Dar conforme', okLabel: 'Dar conforme' }).then(ok => { if (ok) doAction(url('conforme')); });
                break;
            case 'cerrar':
                askConfirm(`¿Cerrar definitivamente la orden ${code}?`, { title: 'Cerrar orden', okLabel: 'Cerrar orden' }).then(ok => { if (ok) doAction(url('cerrar')); });
                break;
            case 'observe': case 'reject': case 'observe-rend': case 'observe-uc1':
                openReasonFor(act, id, code);
                break;
            case 'abono':
                pending = { id, act, code };
                $('ab-code').textContent = code;
                $('ab-info').innerHTML = `Se abonará <strong>${money(d.amount, d.cur)}</strong> a <strong>${esc(d.benef)}</strong> · ${esc(d.bank)} ${esc(d.account)}`;
                $('ab-company').value = ''; $('ab-date').value = TODAY; $('ab-txn').value = '';
                openM('modal-abono');
                break;
            case 'constancia':
                pending = { id, act, code };
                $('co-code').textContent = code; $('co-op').value = d.txn || '—'; $('co-file').value = ''; $('co-dz').__reset && $('co-dz').__reset();
                openM('modal-cons');
                break;
            case 'reembolso':
                pending = { id, act, code };
                $('re-code').textContent = code;
                $('re-info').innerHTML = `Faltante a reembolsar: <strong style="color:var(--red)">${money(d.amount, d.cur)}</strong> a <strong>${esc(d.benef)}</strong>`
                    + (d.bank ? `<div style="margin-top:6px">Depositar a: <strong>${esc(d.bank)}${d.account ? ' · ' + esc(d.account) : ''}</strong> <span style="color:var(--text-muted)">(cuenta del beneficiario)</span></div>` : '');
                $('re-company').value = ''; $('re-date').value = TODAY; $('re-txn').value = ''; $('re-file').value = ''; $('re-dz').__reset && $('re-dz').__reset();
                openM('modal-reem');
                break;
            case 'devolucion':
                pending = { id, act, code };
                $('de-code').textContent = code;
                $('de-info').innerHTML = `Sobrante a devolver: <strong style="color:var(--green)">${money(d.amount, d.cur)}</strong>`
                    + (d.obank ? `<div style="margin-top:6px">Devolver a: <strong>${esc(d.obank)}${d.oaccount ? ' · ' + esc(d.oaccount) : ''}</strong> <span style="color:var(--text-muted)">(cuenta origen)</span></div>` : '');
                $('de-date').value = TODAY; $('de-txn').value = ''; $('de-file').value = ''; $('de-dz').__reset && $('de-dz').__reset();
                openM('modal-dev');
                break;
        }
    });

    $('reason-confirm').addEventListener('click', () => {
        if (!pending) return;
        const txt = $('reason-text').value.trim();
        const isReject = pending.act === 'reject';
        if (!isReject && !$('reason-type').value) { showToast('Selecciona el tipo de observación.', 'warning'); return; }
        if (!txt) { showToast('Escribe el motivo.', 'warning'); return; }
        const endpoint = isReject ? 'reject' : (pending.act === 'observe-rend' ? 'observe-rendicion' : (pending.act === 'observe-uc1' ? 'observe-uc1' : 'observe'));
        const body = isReject ? { reason: txt } : { obs_type: $('reason-type').value, comment: txt };
        closeReason();
        doAction(`${BASE}/${pending.id}/${endpoint}`, body);
        pending = null;
    });

    // Confirm de los modales con archivos/datos
    $('ab-confirm').addEventListener('click', () => {
        if (!pending) return;
        if (!$('ab-company').value)     { showToast('Selecciona la empresa de origen.', 'warning'); return; }
        if (!$('ab-date').value)        { showToast('Indica la fecha de abono.', 'warning'); return; }
        if (!$('ab-txn').value.trim())  { showToast('Indica el N° de operación.', 'warning'); return; }
        closeM('modal-abono');
        doAction(`${BASE}/${pending.id}/abono`, { source_company_id: $('ab-company').value, payment_date: $('ab-date').value, transaction_code: $('ab-txn').value.trim() });
        pending = null;
    });
    $('co-confirm').addEventListener('click', () => {
        if (!pending) return;
        const file = $('co-file').files[0];
        if (!file) { showToast('Adjunta la constancia.', 'warning'); return; }
        const fd = new FormData(); fd.append('constancia', file);
        closeM('modal-cons');
        doAction(`${BASE}/${pending.id}/constancia`, fd, true);
        pending = null;
    });
    $('re-confirm').addEventListener('click', () => {
        if (!pending) return;
        const file = $('re-file').files[0];
        if (!$('re-company').value) { showToast('Selecciona la empresa de origen.', 'warning'); return; }
        if (!$('re-date').value)    { showToast('Indica la fecha.', 'warning'); return; }
        if (!file)                  { showToast('Adjunta la constancia.', 'warning'); return; }
        const fd = new FormData();
        fd.append('source_company_id', $('re-company').value); fd.append('payment_date', $('re-date').value);
        fd.append('transaction_code', $('re-txn').value.trim()); fd.append('constancia', file);
        closeM('modal-reem');
        doAction(`${BASE}/${pending.id}/reembolso`, fd, true);
        pending = null;
    });
    $('de-confirm').addEventListener('click', () => {
        if (!pending) return;
        const file = $('de-file').files[0];
        if (!$('de-date').value) { showToast('Indica la fecha.', 'warning'); return; }
        if (!file)               { showToast('Adjunta la constancia.', 'warning'); return; }
        const fd = new FormData();
        fd.append('payment_date', $('de-date').value); fd.append('transaction_code', $('de-txn').value.trim()); fd.append('constancia', file);
        closeM('modal-dev');
        doAction(`${BASE}/${pending.id}/devolucion`, fd, true);
        pending = null;
    });
})();
</script>
@endpush