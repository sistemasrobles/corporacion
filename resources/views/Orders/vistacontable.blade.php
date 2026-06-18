@extends('layouts.sistema')

@section('title', 'Vista Contable · ' . $o->code)
@section('page', 'orders')

@section('page-pretitle', 'Contabilidad')
@section('page-title', 'Vista Contable · ' . $o->code)
@section('breadcrumb', 'Vista Contable')

@section('page-subtitle')
    {{ $vm['general']['titulo'] }}
    <span class="status status-blue" style="margin-left:8px">{{ $statusLabel }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('orders.view') }}" class="btn btn-outline">Volver</a>
    <button type="button" id="btn-timeline" class="btn btn-outline">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Línea de tiempo
    </button>
    @if ($observe)
        <button type="button" id="btn-observe" class="btn btn-outline" style="color:#b45309">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Observar
        </button>
    @endif
    @if ($isCode)
        <button type="button" id="btn-registro" class="btn btn-primary">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 7a4 4 0 11-4 4M7 15l-3 3M9.5 12.5L7 15M11 11l6.5-6.5"/><circle cx="16.5" cy="7.5" r="2.5"/></svg>
            {{ $codeLabel }}
        </button>
    @endif
    @if ($isClose)
        <button type="button" id="btn-close" class="btn btn-primary" style="background:var(--green);border-color:var(--green)">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Terminar flujo
        </button>
    @endif
@endsection

@push('styles')
<style>
    /* Código de Registro tipeado pero aún no guardado */
    .reg-dirty .reg-input { border-color: #f59e0b; background: #fffbeb; }
    .sum-fs { border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:16px 18px 10px; margin-bottom:16px; }
    .sum-fs > legend { font-size:11px; font-weight:700; color:var(--text-secondary); padding:0 8px; text-transform:uppercase; letter-spacing:.4px; }
    .sum-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px 18px; }
    .sum-l { display:block; font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); font-weight:600; margin-bottom:2px; }
    .sum-v { display:block; font-size:13.5px; color:var(--text); font-weight:500; line-height:1.4; }
    .sum-just { margin-top:14px; }
    #modal-registro .modal-dialog { max-width: 1040px; }
    #modal-registro .table td, #modal-registro .table th { white-space: nowrap; }
    #modal-registro .reg-input { min-width: 200px; height:34px; }
</style>
@endpush

@section('content')

@php
    $g = $vm['general']; $cond = $vm['condicion']; $t = $vm['totales']; $p = $vm['proveedor'];
    $items = $vm['items']; $comprobantes = $vm['comprobantes']; $anexos = $vm['anexos']; $cuotas = $vm['cuotas'];
    $cell  = fn ($l, $v) => '<div><span class="sum-l">'.e($l).'</span><span class="sum-v">'.e($v).'</span></div>';
    $abCls = fn ($id) => $id === 202 ? 'status-green' : ($id === 201 ? 'status-blue' : 'status-yellow');
@endphp

<div class="card"><div class="card-body">

    {{-- Datos generales --}}
    <fieldset class="sum-fs"><legend>Datos generales</legend>
        <div class="sum-grid">
            {!! $cell('Empresa', $g['empresa']) !!}{!! $cell('Tipo de orden', $g['tipo']) !!}{!! $cell('Categoría', $g['categoria']) !!}{!! $cell('Moneda', $g['moneda']) !!}
            {!! $cell('Título', $g['titulo']) !!}{!! $cell('Sede', $g['sede']) !!}{!! $cell('Área', $g['area']) !!}{!! $cell('Centros de costo', $g['cc']) !!}
        </div>
        <div class="sum-just"><span class="sum-l">Justificación</span><span class="sum-v">{{ $g['justificacion'] }}</span></div>
    </fieldset>

    {{-- Proveedor --}}
    @if ($p)
        <fieldset class="sum-fs"><legend>Proveedor</legend>
            <div class="sum-grid">
                {!! $cell('RUC', $p['ruc']) !!}{!! $cell('Razón social', $p['razon_social']) !!}{!! $cell('Contacto', $p['contacto']) !!}{!! $cell('Celular', $p['celular'] ?? '—') !!}
                {!! $cell('Correo', $p['email']) !!}{!! $cell('Domicilio fiscal', $p['direccion']) !!}{!! $cell('Distrito', $p['distrito']) !!}
                @if ($p['cuenta'])
                    {!! $cell('Banco', $p['cuenta']['banco']) !!}{!! $cell('N° de cuenta', $p['cuenta']['numero']) !!}{!! $cell('CCI', $p['cuenta']['cci']) !!}{!! $cell('Moneda cuenta', $p['cuenta']['moneda']) !!}
                @endif
            </div>
        </fieldset>
    @endif

    {{-- Detalle de la orden --}}
    <fieldset class="sum-fs"><legend>Detalle de la orden</legend>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Descripción</th><th style="text-align:right">Cant.</th><th style="text-align:right">P. Unitario</th><th style="text-align:right">Subtotal</th></tr></thead>
            <tbody>
                @forelse ($items as $i)
                    <tr><td>{{ $i['descripcion'] }}</td><td style="text-align:right">{{ $i['cantidad'] }}</td><td style="text-align:right">{{ $i['precio'] }}</td><td class="cell-strong" style="text-align:right">{{ $i['subtotal'] }}</td></tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:10px">Sin ítems</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if (!empty($vm['observacion']))
            <div class="sum-just"><span class="sum-l">Observaciones</span><span class="sum-v">{{ $vm['observacion'] }}</span></div>
        @endif
    </fieldset>

    {{-- Condición de pago --}}
    <fieldset class="sum-fs"><legend>Condición de pago</legend>
        <div class="sum-grid">
            {!! $cell('Forma de pago', $cond['forma_pago']) !!}{!! $cell('Condición', $cond['condicion']) !!}{!! $cell('Programación', $cond['programacion']) !!}
            @unless ($vm['es_fraccionado']){!! $cell('Vencimiento', $cond['vencimiento']) !!}@endunless
            {!! $cell('Subtotal', $t['subtotal']) !!}{!! $cell('IGV', $t['igv']) !!}{!! $cell('Descuento', $t['descuento']) !!}{!! $cell('Total', $t['total']) !!}
            @if (!empty($vm['codigo_banco'])){!! $cell('Código de banco', $vm['codigo_banco']) !!}@endif
        </div>
    </fieldset>

    {{-- Cronograma de pagos --}}
    <fieldset class="sum-fs"><legend>Cronograma de pagos</legend>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Cuota</th><th>Vencimiento</th><th style="text-align:right">Monto</th><th>Estado abono</th><th>Documento</th><th>F. Subida</th><th>N° Operación</th><th>Subido por</th></tr></thead>
            <tbody>
                @forelse ($cuotas as $q)
                    <tr>
                        <td class="cell-strong">Cuota {{ $q['numero'] }}</td>
                        <td>{{ $q['fecha'] }}</td>
                        <td class="cell-strong" style="text-align:right">{{ $q['monto'] }}</td>
                        <td><span class="status {{ $abCls($q['estado_id']) }}">{{ $q['estado'] }}</span></td>
                        <td>@if ($q['constancia'])<a href="{{ $q['constancia'] }}" target="_blank" style="color:var(--primary)">Ver</a>@else <span style="color:var(--text-muted)">—</span>@endif</td>
                        <td>{{ $q['const_fecha'] ?: '—' }}</td>
                        <td class="cell-mono">{{ $q['operacion'] ?: '—' }}</td>
                        <td>{{ $q['subido_por'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:10px">Sin cronograma</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </fieldset>

    {{-- Comprobantes de pago --}}
    @if (count($comprobantes))
        <fieldset class="sum-fs"><legend>Comprobantes de pago</legend>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Tipo</th><th>N° Doc</th><th style="text-align:right">Monto</th><th>Emisión</th><th>Cód. Registro</th><th>Comentario</th><th>F. Subida</th><th>Subido por</th><th>Archivo</th></tr></thead>
                <tbody>
                    @foreach ($comprobantes as $x)
                        <tr>
                            <td>{{ $x['tipo'] }}</td>
                            <td class="cell-mono">{{ $x['numero'] }}</td>
                            <td style="text-align:right">{{ $x['monto'] }}</td>
                            <td>{{ $x['fecha'] }}</td>
                            <td class="cell-mono">{{ $x['cod_registro'] ?: '—' }}</td>
                            <td>{{ $x['comentario'] ?: '—' }}</td>
                            <td>{{ $x['subida'] }}</td>
                            <td>{{ $x['uploader'] ?? '—' }}</td>
                            <td>@if ($x['path'])<a href="{{ $x['path'] }}" target="_blank" style="color:var(--primary)">Ver</a>@else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </fieldset>
    @endif

    {{-- Documentos anexos --}}
    @if (count($anexos))
        <fieldset class="sum-fs" style="margin-bottom:0"><legend>Documentos anexos</legend>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Tipo</th><th>Comentario</th><th>F. Subida</th><th>Subido por</th><th>Archivo</th></tr></thead>
                <tbody>
                    @foreach ($anexos as $x)
                        <tr>
                            <td>{{ $x['tipo'] }}</td>
                            <td>{{ $x['comentario'] }}</td>
                            <td>{{ $x['subida'] }}</td>
                            <td>{{ $x['uploader'] ?? '—' }}</td>
                            <td>@if ($x['path'])<a href="{{ $x['path'] }}" target="_blank" style="color:var(--primary)">Ver</a>@else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </fieldset>
    @endif

</div></div>

@if ($isCode)
@if ($codeMode === 'perdoc')
{{-- ── MODAL: Código de Registro por documento de pago (UC1) ── --}}
<div class="modal-backdrop" id="modal-registro" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Código de Registro <span style="opacity:.7;font-weight:400">{{ $o->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-registro" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:13px;margin:0 0 12px">
                Asigna el código de registro a cada documento de pago. Una vez guardado, el documento ya no podrá eliminarse.
            </p>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>N° Documento</th><th style="text-align:right">Monto</th><th>Emisión</th><th>Archivo</th><th style="min-width:230px">Código de Registro</th></tr></thead>
                    <tbody id="reg-list"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-registro">Cerrar</button>
            <button type="button" id="reg-advance" class="btn btn-primary">Pasar a Código de Banco →</button>
        </div>
    </div>
</div>
@else
{{-- ── MODAL: Código de Banco (a nivel de orden) — UC2 ── --}}
<div class="modal-backdrop" id="modal-codebanco" style="display:none">
    <div class="modal-dialog modal-sm">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Código de Banco <span style="opacity:.7;font-weight:400">{{ $o->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-codebanco" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Código de Banco <span class="required">*</span></label>
                <input type="text" id="cb-input" class="form-control" maxlength="100" placeholder="Ej. BCO-2026-00045" value="{{ $vm['codigo_banco'] }}">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-codebanco">Cancelar</button>
            <button type="button" id="cb-save" class="btn btn-primary">Guardar y pasar a Cierre</button>
        </div>
    </div>
</div>
@endif
@endif

@if ($isClose)
{{-- ── MODAL: Terminar flujo (cierre) — UC3/UC4 ── --}}
<div class="modal-backdrop" id="modal-close" style="display:none">
    <div class="modal-dialog modal-sm">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Terminar flujo <span style="opacity:.7;font-weight:400">{{ $o->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-close" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <p style="margin:0;color:var(--text-secondary);font-size:14px;line-height:1.5">
                Esta acción <strong>cierra la orden</strong> y finaliza todo el flujo. Esta operación no se puede deshacer. ¿Deseas continuar?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-close">Cancelar</button>
            <button type="button" id="close-confirm" class="btn btn-primary" style="background:var(--green);border-color:var(--green)">Sí, terminar flujo</button>
        </div>
    </div>
</div>
@endif

{{-- ── MODAL: Observar ── --}}
<div class="modal-backdrop" id="modal-observe" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Observar orden <span style="opacity:.7;font-weight:400">{{ $o->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-observe" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Tipo de observación <span class="required">*</span></label>
                <select id="obs_type" class="form-control">
                    <option value="">Seleccione...</option>
                    @foreach ($obsTypes as $val => $desc)
                        <option value="{{ $val }}">{{ $desc }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Comentario <span class="required">*</span></label>
                <textarea id="obs_comment" class="form-control" rows="3" placeholder="Detalla la observación..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-observe">Cancelar</button>
            <button type="button" id="observe-save" class="btn btn-primary">Enviar observación</button>
        </div>
    </div>
</div>

{{-- ── MODAL: Línea de tiempo ── --}}
<div class="modal-backdrop" id="modal-timeline" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Historial de movimientos <span style="opacity:.7;font-weight:400">{{ $o->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-timeline" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body" id="tl-body" style="min-height:120px"></div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const OID  = "{{ $o->id }}";
    const BASE = "{{ url('orders') }}";

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
        if (e.target.classList.contains('modal-backdrop') && ['modal-registro', 'modal-codebanco', 'modal-close', 'modal-observe', 'modal-timeline'].includes(e.target.id)) closeM(e.target.id);
    });

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : null,
        });
        return { ok: res.ok, json: await res.json().catch(() => ({})) };
    }

    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

@if ($isCode)
@if ($codeMode === 'perdoc')
    // ── Código de Registro por documento (UC1) ──
    const regList = document.getElementById('reg-list');

    function regSkeleton(n = 3) {
        const w = ['60%', '70%', '50%', '65%', '40%', '90%'];
        regList.innerHTML = Array.from({ length: n }).map(() => `<tr>${w.map(width => `<td><span class="skeleton skeleton-text" style="width:${width}"></span></td>`).join('')}</tr>`).join('');
    }
    async function loadRegistro() {
        try {
            const res = await fetch(`${BASE}/${OID}/comprobantes`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            renderRegistro(json.data || []);
        } catch (e) {
            regList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--red);padding:14px">Error al cargar.</td></tr>`;
        }
    }
    function renderRegistro(list) {
        if (!list.length) { regList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:14px;font-style:italic">No hay documentos de pago.</td></tr>`; return; }
        regList.innerHTML = list.map(d => `
            <tr data-fid="${d.id}">
                <td>${esc(d.type_label)}</td>
                <td class="cell-mono">${esc(d.document_number)}</td>
                <td class="cell-strong" style="text-align:right">${esc(d.amount)}</td>
                <td>${esc(d.emission_date)}</td>
                <td>${d.path ? `<a href="${esc(d.path)}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="text" class="form-control reg-input" data-fid="${d.id}" data-saved="${esc(d.registration_code)}" maxlength="100" value="${esc(d.registration_code)}" placeholder="REG-2026-00123">
                        <button type="button" class="btn btn-primary btn-sm reg-save" data-fid="${d.id}" title="Guardar código">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    document.getElementById('btn-registro').addEventListener('click', () => { regSkeleton(); openM('modal-registro'); loadRegistro(); });

    regList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.reg-save');
        if (!btn) return;
        const input = regList.querySelector(`.reg-input[data-fid="${btn.dataset.fid}"]`);
        const codigo = (input.value || '').trim();
        if (!codigo) { showToast('Ingresa el código.', 'warning'); return; }
        btn.disabled = true;
        const r = await post(`${BASE}/${OID}/registro/${btn.dataset.fid}`, { codigo });
        if (r.ok && r.json.status === 1) {
            input.dataset.saved = codigo;                 // marca como guardado
            input.closest('tr').classList.remove('reg-dirty');
            showToast(r.json.description, 'success');
        } else { showToast(r.json.description || 'No se pudo guardar.', 'error'); }
        btn.disabled = false;
    });

    // Marca la fila como "sin guardar" mientras se edita sin presionar ✓
    regList.addEventListener('input', (e) => {
        const input = e.target.closest('.reg-input');
        if (!input) return;
        input.closest('tr').classList.toggle('reg-dirty', input.value.trim() !== (input.dataset.saved || '').trim());
    });

    document.getElementById('reg-advance').addEventListener('click', async function () {
        // Pre-chequeo: todos los documentos con código y GUARDADO (no solo tipeado).
        const inputs = [...regList.querySelectorAll('.reg-input')];
        if (!inputs.length || inputs.some(i => !i.value.trim())) {
            showToast('Ingresa el Código de Registro de todos los documentos de pago.', 'warning'); return;
        }
        if (inputs.some(i => i.value.trim() !== (i.dataset.saved || '').trim())) {
            showToast('Hay códigos sin guardar. Guarda cada uno con el botón ✓ antes de continuar.', 'warning'); return;
        }
        if (this.disabled) return;                                  // evita dobles envíos
        this.disabled = true; this.textContent = 'Avanzando...';
        window.blockUI && window.blockUI('Procesando…');            // bloquea toda la pantalla
        const r = await post(`${BASE}/${OID}/registro-advance`);
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = "{{ route('orders.view') }}", 900); return; }
        const msg = r.json.description || (r.json.errors && Object.values(r.json.errors)[0]?.[0]) || r.json.message || 'No se pudo avanzar.';
        showToast(msg, 'error');
        window.unblockUI && window.unblockUI();
        this.disabled = false; this.innerHTML = 'Pasar a Código de Banco →';
    });
@else
    // ── Código de Banco a nivel de orden (UC2): guarda en order_details y avanza ──
    document.getElementById('btn-registro').addEventListener('click', () => { openM('modal-codebanco'); document.getElementById('cb-input').focus(); });

    document.getElementById('cb-save').addEventListener('click', async function () {
        const codigo = (document.getElementById('cb-input').value || '').trim();
        if (!codigo) { showToast('Ingresa el código de banco.', 'warning'); return; }
        if (this.disabled) return;                                  // evita dobles envíos
        this.disabled = true; this.textContent = 'Guardando...';
        window.blockUI && window.blockUI('Procesando…');            // bloquea toda la pantalla
        const r = await post("{{ route('orders.code', $o->id) }}", { codigo });
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = "{{ route('orders.view') }}", 900); return; }
        const msg = r.json.description || (r.json.errors && Object.values(r.json.errors)[0]?.[0]) || r.json.message || 'No se pudo guardar.';
        showToast(msg, 'error');
        window.unblockUI && window.unblockUI();
        this.disabled = false; this.textContent = 'Guardar y pasar a Cierre';
    });
@endif
@endif

@if ($isClose)
    // ── Terminar flujo (cierre) — UC3/UC4 ──
    document.getElementById('btn-close').addEventListener('click', () => openM('modal-close'));
    document.getElementById('close-confirm').addEventListener('click', async function () {
        if (this.disabled) return;                                  // evita dobles envíos
        this.disabled = true; this.textContent = 'Cerrando...';
        window.blockUI && window.blockUI('Procesando…');            // bloquea toda la pantalla
        const r = await post("{{ route('orders.approve', $o->id) }}");
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = "{{ route('orders.view') }}", 900); return; }
        const msg = r.json.description || (r.json.errors && Object.values(r.json.errors)[0]?.[0]) || r.json.message || 'No se pudo cerrar.';
        showToast(msg, 'error');
        window.unblockUI && window.unblockUI();
        this.disabled = false; this.textContent = 'Sí, terminar flujo';
    });
@endif

    // ── Línea de tiempo ──
    const tlBody = document.getElementById('tl-body');
    function tlDot(s) {
        if (s === 5) return 'is-yellow';
        if ([3, 7, 9].includes(s)) return 'is-green';
        if (s === 4) return 'is-red';
        if ([1, 6].includes(s)) return 'is-blue';
        return 'is-primary';
    }
    function tlSkeleton(n = 4) {
        return `<div class="timeline">` + Array.from({ length: n }).map(() => `
            <div class="timeline-item">
                <span class="skeleton skeleton-text" style="width:42%"></span>
                <span class="skeleton skeleton-text-lg" style="width:58%"></span>
                <span class="skeleton skeleton-text" style="width:78%"></span>
            </div>`).join('') + `</div>`;
    }
    function tlRender(items) {
        if (!items.length) { tlBody.innerHTML = `<div style="text-align:center;color:var(--text-muted);padding:30px">Esta orden aún no tiene movimientos registrados.</div>`; return; }
        tlBody.innerHTML = `<div class="timeline">` + items.map(it => `
            <div class="timeline-item ${tlDot(it.to_status_id)}">
                <div class="ti-time">${esc(it.date)} · ${esc(it.user)} <span style="color:var(--text-disabled)">(${esc(it.from_role)})</span></div>
                <div class="ti-title">${esc(it.from_status)} → ${esc(it.to_status)}</div>
                ${it.comment ? `<div class="ti-desc">${esc(it.comment)}</div>` : ''}
            </div>`).join('') + `</div>`;
    }
    document.getElementById('btn-timeline').addEventListener('click', async () => {
        tlBody.innerHTML = tlSkeleton();
        openM('modal-timeline');
        try {
            const res = await fetch(`{{ url('orders/timeline') }}/${OID}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            tlRender(json.data || []);
        } catch (err) {
            tlBody.innerHTML = `<div style="text-align:center;color:var(--red);padding:30px">Error al cargar el historial.</div>`;
        }
    });

    // ── Observar ──
    @if ($observe)
    document.getElementById('btn-observe').addEventListener('click', () => {
        document.getElementById('obs_type').value = '';
        document.getElementById('obs_comment').value = '';
        openM('modal-observe');
    });
    document.getElementById('observe-save').addEventListener('click', async function () {
        const obs_type = document.getElementById('obs_type').value;
        const obs_comment = document.getElementById('obs_comment').value.trim();
        if (!obs_type || !obs_comment) { showToast('Completa el tipo y el comentario.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Enviando...';
        const r = await post("{{ route('orders.observe', $o->id) }}", { obs_type, obs_comment });
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = "{{ route('orders.view') }}", 900); return; }
        showToast(r.json.description || 'No se pudo registrar.', 'error');
        this.disabled = false; this.textContent = 'Enviar observación';
    });
    @endif
})();
</script>
@endpush