@extends('layouts.sistema')

@section('title', 'Cuentas por Pagar')
@section('page', 'cuentas')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Cuentas por Pagar')
@section('breadcrumb', 'Cuentas por Pagar')

@php
    $abClass = fn (int $s) => match ($s) {
        200 => 'status-yellow',
        201 => 'status-blue',
        202 => 'status-green',
        default => 'status-blue',
    };
@endphp

@push('styles')
<style>
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
</style>
@endpush

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Abonos en proceso de pago</div>
                <div class="card-subtitle">{{ count($rows) }} abono(s)</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Programación</th>
                            <th>Cuota</th>
                            <th>Monto</th>
                            <th>Vencimiento</th>
                            <th>Estado abono</th>
                            <th>Cuenta origen</th>
                            <th>Constancia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $o = $row['order']; $ab = $row['abono']; $acts = $row['acts'];
                                $cur = ($o->detail?->currency === 'USD') ? '$ ' : 'S/ ';
                            @endphp
                            <tr>
                                <td class="cell-mono">{{ $o->code }}</td>
                                <td><span class="status status-blue">{{ \Illuminate\Support\Str::limit($o->paymentSchedule?->name, 14) }}</span></td>
                                <td class="cell-strong">Cuota {{ $ab->quota_number }}</td>
                                <td class="cell-strong">{{ $cur . number_format($ab->amount, 2) }}</td>
                                <td>{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}</td>
                                <td>
                                    <span class="status {{ $abClass((int) $ab->status) }}">
                                        {{ $abStatus[$ab->status] ?? $ab->status }}
                                    </span>
                                    @if ($ab->monto_ok)
                                        <span class="status status-green" style="margin-left:4px">✓ Conforme</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($ab->source_account_number)
                                        <div style="font-size:12px;line-height:1.35">
                                            <div class="cell-strong">{{ $ab->source_bank ?: '—' }}</div>
                                            <div class="cell-mono" style="color:var(--text-muted)">{{ $ab->source_account_number }}</div>
                                        </div>
                                    @else
                                        <span style="color:var(--text-muted)">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($ab->constancia)
                                        <a href="/storage/{{ $ab->constancia }}" target="_blank" style="color:var(--primary)">Ver</a>
                                        @if ($ab->operation_number)
                                            <div class="cell-mono" style="font-size:12px;color:var(--text-muted)">Op. {{ $ab->operation_number }}</div>
                                        @endif
                                    @else
                                        <span style="color:var(--text-muted)">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if ($acts['deposit'])
                                        <button type="button" class="btn btn-outline btn-sm ab-deposit" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" data-amount="{{ $cur . number_format($ab->amount, 2) }}" data-due="{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}" data-sched="{{ $o->paymentSchedule?->name }}" title="Registrar depósito" style="color:var(--green)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['constancia'])
                                        <button type="button" class="btn btn-outline btn-sm ab-constancia" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" data-amount="{{ $cur . number_format($ab->amount, 2) }}" data-due="{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}" data-sched="{{ $o->paymentSchedule?->name }}" title="Subir constancia" style="color:var(--primary)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['verify'])
                                        <button type="button" class="btn btn-outline btn-sm ab-verify" data-id="{{ $ab->id }}" title="Marcar conforme" style="color:var(--green)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['observe'])
                                        <button type="button" class="btn btn-outline btn-sm ab-observe" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" title="Observar abono" style="color:#b45309">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" style="padding:40px;text-align:center;color:var(--text-muted)">No hay abonos para gestionar.</td></tr>
                        @endforelse
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

@endsection

@push('scripts')
<script>
(function () {
    const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
    const table = document.querySelector('table.table');
    const BASE  = "{{ url('orders/abono') }}";
    let currentId = null;

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
        if (e.target.classList.contains('modal-backdrop') && ['modal-constancia', 'modal-deposit', 'modal-abono-obs'].includes(e.target.id)) closeM(e.target.id);
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

    // Delegación de botones
    table.addEventListener('click', async (e) => {
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