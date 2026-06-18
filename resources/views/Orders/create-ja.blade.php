@extends('layouts.sistema')

@section('title', 'Nueva Solicitud')
@section('page', 'orders')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Nueva Solicitud')
@section('breadcrumb', 'Nueva Solicitud')

@section('page-actions')
    <a href="{{ route('orders.view') }}" class="btn btn-outline">← Cancelar</a>
    <button type="submit" form="ja-form" class="btn btn-primary">Crear solicitud →</button>
@endsection

@push('styles')
<style>
    .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .form-group { margin-bottom: 14px; }
    .sec-card   { margin-bottom: 16px; }
    .sec-num {
        display:inline-flex;align-items:center;justify-content:center;
        width:22px;height:22px;border-radius:6px;background:var(--text);
        color:#fff;font-size:12px;font-weight:700;margin-right:8px;
    }
    .ro-field { background: var(--bg-surface-secondary) !important; color: var(--text-muted); cursor: not-allowed; }
    .ro-field:focus { outline: none; box-shadow: none; border-color: var(--border-color); }
    /* Select buscable deshabilitado (Categoría hasta elegir Tipo de Orden) */
    .ss-disabled .ms-input { background: var(--bg-surface-secondary); cursor: not-allowed; opacity: .7; }
    .ss-disabled .ms-search { cursor: not-allowed; }
    .resp-box {
        display:flex;align-items:center;gap:10px;
        background:var(--bg-surface-secondary);border:1px solid var(--border-color);
        border-radius:var(--radius-lg);padding:10px 14px;
    }
    .resp-box .avatar {
        width:34px;height:34px;border-radius:50%;flex:none;
        background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;
        font-weight:700;font-size:13px;
    }
    .resp-box .nm   { font-weight:600;color:var(--text);font-size:14px;line-height:1.2; }
    .resp-box .lbl  { font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600; }
    .dz { min-height:120px; }
    .dz .fname { font-size:13px;color:var(--primary);font-weight:600;margin-top:6px; }
    @media (max-width: 900px) { .grid-2,.grid-3 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

<form id="ja-form" method="POST" enctype="multipart/form-data" action="{{ route('orders.store-ja') }}">
    @csrf

    {{-- ── CARD 1: Clasificación ── --}}
    <div class="card sec-card">
        <div class="card-header">
            <div class="card-title"><span class="sec-num">1</span>Clasificación
                <small class="card-subtitle">(Tipo de orden, categoría y gestión)</small>
            </div>
        </div>
        <div class="card-body">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Tipo de Orden <span class="required">*</span></label>
                    <select name="format_id" id="format_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($formats as $id => $desc)
                            <option value="{{ $id }}">{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría <span class="required">*</span></label>
                    <select name="category_id" id="category_id" class="form-control searchable" required disabled>
                        <option value="">Seleccione tipo de orden primero</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Gestión <span class="required">*</span></label>
                    <select name="type_id" id="type_id" class="form-control searchable" required>
                        <option value="">Seleccione una opción</option>
                        @foreach ($gestiones as $g)
                            <option value="{{ $g['id'] }}" data-responsible="{{ $g['responsible'] }}">{{ $g['descripcion'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid-3" style="align-items:start">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Empresa <span class="required">*</span></label>
                    <select name="company_id" class="form-control searchable" required>
                        <option value="">Seleccione una opción</option>
                        @foreach ($companies as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Fecha de Vencimiento <span class="required">*</span></label>
                    <input type="date" name="expiration_date" class="form-control" min="{{ now()->toDateString() }}" required>
                    <small style="font-size:12px;color:var(--text-muted)">Fecha límite de la orden</small>
                </div>
                {{-- Responsable: se pinta automáticamente según la Gestión (type_user) --}}
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Responsable de la orden</label>
                    <div class="resp-box">
                        <div class="avatar" id="resp-avatar">—</div>
                        <div>
                            <div class="nm" id="resp-name">Selecciona una Gestión</div>
                            <div class="lbl">Según la Gestión elegida</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CARD 2: Detalle del Requerimiento y Documentación ── --}}
    <div class="card sec-card">
        <div class="card-header">
            <div class="card-title"><span class="sec-num">2</span>Detalle del Requerimiento y Documentación
                <small class="card-subtitle">(Título, justificación y cotización)</small>
            </div>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Título de la Orden <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" maxlength="250" required
                       placeholder="Ej. Renovación de licencias anuales de software">
            </div>

            <div class="grid-2">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Justificación <span class="required">*</span></label>
                    <textarea name="justification" id="justification" class="form-control" rows="5" maxlength="250" required
                              placeholder="Describa el motivo y sustento del requerimiento..."></textarea>
                    <div style="text-align:right;font-size:12px;color:var(--text-muted);margin-top:2px"><span id="just-count">0</span>/250</div>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Cotización <small style="color:var(--text-muted)">(Opcional)</small></label>
                    <label class="dropzone dz" id="cotiz-dz" for="cotiz-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Arrastra y suelta tu archivo o <span style="color:var(--primary)">Examina</span></div>
                        <div class="sub">PDF, JPG, PNG — Máx. 5 MB</div>
                        <div class="fname" id="cotiz-name" style="display:none"></div>
                        <input type="file" name="cotizacion" id="cotiz-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script src="/src/searchable-select.js?v={{ @filemtime(public_path('src/searchable-select.js')) ?: time() }}"></script>
<script>
// Toast del tema (esta vista no lo trae globalmente; sin él, un error tras blockUI deja el overlay colgado)
function showToast(message, variant = 'success', timeout = 3200) {
    let host = document.querySelector('.toast-host');
    if (!host) { host = document.createElement('div'); host.className = 'toast-host'; host.style.zIndex = 2000; document.body.appendChild(host); }
    const t = document.createElement('div');
    t.className = 'toast toast-' + variant;
    t.textContent = message;
    host.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    const close = () => { t.classList.remove('show'); setTimeout(() => t.remove(), 200); };
    const timer = setTimeout(close, timeout);
    t.addEventListener('click', () => { clearTimeout(timer); close(); });
}
window.showToast = showToast;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Categoría dependiente del Tipo de Orden ──
    const formatSel = document.getElementById('format_id');
    const catSel    = document.getElementById('category_id');
    const CAT_BASE  = "{{ url('orders/categories') }}";
    formatSel.addEventListener('change', async () => {
        catSel.disabled = true;
        catSel.innerHTML = '<option value="">Cargando...</option>';
        if (catSel.__ss) catSel.__ss.setDisabled(true);
        const f = formatSel.value;
        if (!f) {
            catSel.innerHTML = '<option value="">Seleccione tipo de orden primero</option>';
            return;
        }
        try {
            const res  = await fetch(`${CAT_BASE}/${f}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const opts = json.data || [];
            catSel.innerHTML = '<option value="">Seleccione...</option>'
                + opts.map(c => `<option value="${c.id}">${c.description}</option>`).join('');
            catSel.disabled = false;
            if (catSel.__ss) catSel.__ss.setDisabled(false);
        } catch (e) {
            catSel.innerHTML = '<option value="">Error al cargar</option>';
        }
    });

    // ── Responsable pintado por la Gestión (type_user) ──
    const typeSel  = document.getElementById('type_id');
    const respName = document.getElementById('resp-name');
    const respAv   = document.getElementById('resp-avatar');
    typeSel.addEventListener('change', () => {
        const opt = typeSel.options[typeSel.selectedIndex];
        const name = (opt && opt.dataset.responsible) ? opt.dataset.responsible : '';
        if (name) {
            respName.textContent = name;
            respName.style.color = 'var(--text)';
            respAv.textContent = name.trim().charAt(0).toUpperCase() || '—';
        } else {
            respName.textContent = 'Selecciona una Gestión';
            respName.style.color = '';
            respAv.textContent = '—';
        }
    });

    // ── Contador de justificación ──
    const just = document.getElementById('justification');
    const jc   = document.getElementById('just-count');
    const upd  = () => { jc.textContent = just.value.length; };
    just.addEventListener('input', upd); upd();

    // ── Cotización: muestra el nombre del archivo elegido ──
    const cotizInput = document.getElementById('cotiz-file');
    const cotizName  = document.getElementById('cotiz-name');
    cotizInput.addEventListener('change', () => {
        if (cotizInput.files.length) {
            cotizName.textContent = cotizInput.files[0].name;
            cotizName.style.display = 'block';
        } else {
            cotizName.style.display = 'none';
        }
    });
});
</script>

<script>
// ── Envío del formulario (AJAX multipart) ──
(function () {
    const form = document.getElementById('ja-form');
    const btn  = document.querySelector('button[form="ja-form"]');
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const original = btn.innerHTML;
        btn.disabled = true; btn.textContent = 'Guardando...';
        window.blockUI && window.blockUI('Procesando…');
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: new FormData(form),
            });
            if (res.status === 422) {
                const j = await res.json();
                const first = Object.values(j.errors || {})[0]?.[0] || j.message || 'Revisa los campos obligatorios.';
                showToast(first, 'warning');
            } else if (res.ok) {
                const j = await res.json();
                if (j.status === 1) {
                    showToast(`${j.description} (${j.data.code})`, 'success');
                    setTimeout(() => { window.location = j.data.redirect; }, 900);
                    return;
                }
                showToast(j.description || 'No se pudo crear la solicitud.', 'error');
            } else {
                showToast('Error al guardar la solicitud.', 'error');
            }
        } catch (err) {
            showToast('Error de red al guardar.', 'error');
        }
        window.unblockUI && window.unblockUI();
        btn.disabled = false; btn.innerHTML = original;
    });
})();
</script>
@endpush