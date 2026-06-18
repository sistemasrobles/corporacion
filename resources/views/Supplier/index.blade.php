@extends('layouts.sistema')

@section('title', 'Proveedores')
@section('page', 'suppliers')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Proveedores')
@section('breadcrumb', 'Proveedores')

@section('page-actions')
    <button type="button" id="btn-nuevo" class="btn btn-primary">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
        Añadir nuevo
    </button>
@endsection

@push('styles')
<style>
    .filtros .row        { margin-bottom: 0; }
    .filtros .form-group { margin-bottom: 10px; }
    .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .fs { border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:18px 18px 4px; margin-bottom:16px; }
    .fs > legend { font-size:11px; font-weight:700; color:var(--text-secondary); padding:0 8px; text-transform:uppercase; letter-spacing:.4px; }
    .btn-remove { border:none;background:transparent;color:var(--red);cursor:pointer;font-size:18px;line-height:1; }
    #tabla-sup { min-width: 1100px; }
    #tabla-sup th, #tabla-sup td { white-space: nowrap; vertical-align: middle; }
    #modal-supplier .modal-dialog { max-width: 1000px; }
    #modal-supplier .grid-3 { gap: 16px 22px; }
    #modal-supplier .form-group { margin-bottom: 16px; }
</style>
@endpush

@section('content')

    {{-- ── Filtros ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form id="filtros-sup" class="filtros" onsubmit="return false">
                <div class="row col-3">
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="1" selected>Activos</option>
                            <option value="0">Inactivos</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="RUC, razón social, contacto..." autocomplete="off">
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
            <div class="table-responsive" style="overflow-x:auto">
                <table class="table" id="tabla-sup" style="width:100%">
                    <thead>
                        <tr>
                            <th>RUC</th>
                            <th>Razón Social</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody id="sup-body">
                        @include('Supplier.partials.supplier-rows')
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Registrar nuevo proveedor (clon del de Órdenes) ── --}}
    <div class="modal-backdrop" id="modal-supplier" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff" id="modal-sup-title">Registrar nuevo proveedor</h3>
                <button type="button" class="modal-close" data-close="modal-supplier" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <fieldset class="fs">
                    <legend>Datos generales</legend>
                    <div class="grid-3">
                        <div class="form-group"><label class="form-label">RUC <span class="required">*</span></label><input id="ns_ruc" class="form-control" inputmode="numeric" maxlength="11" placeholder="11 dígitos"></div>
                        <div class="form-group" style="grid-column:span 2"><label class="form-label">Razón social <span class="required">*</span></label><input id="ns_name" class="form-control" maxlength="250"></div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group"><label class="form-label">Domicilio fiscal</label><input id="ns_address" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Provincia</label><input id="ns_provincia" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Distrito</label><input id="ns_district" class="form-control"></div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group"><label class="form-label">Contacto</label><input id="ns_contact" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Teléfono</label><input id="ns_phone" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Correo</label><input id="ns_email" type="email" class="form-control"></div>
                    </div>
                </fieldset>

                <label class="switch" style="margin-bottom:16px">
                    <input type="checkbox" id="ns_active" value="1" checked>
                    <span class="track"></span>
                    <span class="switch-label">Proveedor activo</span>
                </label>

                <fieldset class="fs">
                    <legend>Cuentas bancarias</legend>
                    <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
                        <button type="button" id="ns-add-account" class="btn btn-outline btn-sm">+ Agregar cuenta</button>
                    </div>
                    <div id="ns-accounts"></div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-supplier">Cancelar</button>
                <button type="button" id="ns-save" class="btn btn-primary">Guardar proveedor</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Ver cuentas bancarias (solo lectura) ── --}}
    <div class="modal-backdrop" id="modal-accounts" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Cuentas bancarias <span id="acc-sup-name" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-accounts" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Banco</th><th>Moneda</th><th>N° Cuenta</th><th>CCI</th></tr></thead>
                        <tbody id="acc-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-close="modal-accounts">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Confirmación ── --}}
    <div class="modal-backdrop" id="modal-confirm" style="display:none">
        <div class="modal-dialog" style="max-width:440px">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff" id="confirm-title">Confirmar</h3>
                <button type="button" class="modal-close" id="confirm-x" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <p id="confirm-msg" style="margin:0;font-size:14px;line-height:1.5;color:var(--text)"></p>
            </div>
            <div class="modal-footer">
                <button type="button" id="confirm-cancel" class="btn btn-outline">Cancelar</button>
                <button type="button" id="confirm-ok" class="btn btn-primary">Confirmar</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const $    = (id) => document.getElementById(id);
    // Token CSRF fresco: usa la cookie XSRF-TOKEN (el servidor la refresca en cada respuesta,
    // p.ej. al abrir el modal de editar) en vez del <meta> de la carga inicial, que puede quedar viejo.
    function csrfHeaders() {
        const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : { 'X-CSRF-TOKEN': CSRF };
    }
    const BANK_OPTIONS = `@foreach($banks as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach`;
    let currentId = null;   // null = crear ; id = editar

    // ── Toast ──
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
    window.showToast = showToast;

    // ── Modal helpers ──
    const openM  = (id) => { const el = $(id); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); };
    const closeM = (id) => { const el = $(id); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); };
    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]');
        if (c) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && ['modal-supplier', 'modal-accounts'].includes(e.target.id)) closeM(e.target.id);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeM('modal-supplier'); closeM('modal-accounts'); settleConfirm(false); } });
    const esc = (t) => String(t ?? '—').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    // ── Confirmación estilizada (reemplaza window.confirm) ──
    let confirmResolver = null;
    function askConfirm(message, { title = 'Confirmar', confirmText = 'Confirmar', danger = false } = {}) {
        $('confirm-title').textContent = title;
        $('confirm-msg').textContent = message;
        const ok = $('confirm-ok');
        ok.textContent = confirmText;
        ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
        openM('modal-confirm');
        return new Promise((resolve) => { confirmResolver = resolve; });
    }
    function settleConfirm(val) {
        if ($('modal-confirm').style.display === 'none') return;
        closeM('modal-confirm');
        if (confirmResolver) { confirmResolver(val); confirmResolver = null; }
    }
    $('confirm-ok').addEventListener('click', () => settleConfirm(true));
    $('confirm-cancel').addEventListener('click', () => settleConfirm(false));
    $('confirm-x').addEventListener('click', () => settleConfirm(false));
    $('modal-confirm').addEventListener('click', (e) => { if (e.target.id === 'modal-confirm') settleConfirm(false); });

    // ── DataTables ──
    const form = $('filtros-sup');
    const tableConfig = {
        pageLength: 15,
        order: [],
        layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin proveedores', infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron proveedores',
            emptyTable: 'No hay proveedores registrados',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    };
    let dt = new DataTable('#tabla-sup', tableConfig);

    // Filtro por estado (activos/inactivos) — lee data-active de cada fila
    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-sup') return true;
        const row = settings.aoData[dataIndex].nTr;
        if (!row) return true;
        const fEstado = form.elements['estado'].value;
        if (fEstado !== '' && row.dataset.active !== fEstado) return false;
        return true;
    });
    form.elements['estado'].addEventListener('change', () => dt.draw());
    form.elements['q'].addEventListener('input', function () { dt.search(this.value).draw(); });
    dt.draw();   // aplica el filtro por defecto (Activos) en la carga inicial

    // ── Recargar / refrescar la tabla (reusa el partial) ──
    async function reloadTable(clearSearch = false) {
        window.blockUI && window.blockUI('Actualizando…');
        try {
            const res = await fetch("{{ route('suppliers.rows') }}?_=" + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) {
                dt.destroy();
                $('sup-body').innerHTML = j.data.html;
                dt = new DataTable('#tabla-sup', tableConfig);
                if (clearSearch) { form.reset(); }
                dt.search(clearSearch ? '' : form.elements['q'].value).draw();
            } else {
                showToast(j.description || 'No se pudo recargar.', 'error');
            }
        } catch (e) { showToast('Error de red al recargar.', 'error'); }
        window.unblockUI && window.unblockUI();
    }

    $('btn-recargar').addEventListener('click', async function () {
        this.disabled = true;
        await reloadTable(true);   // limpia el buscador
        this.disabled = false;
    });

    // ── Modal: registrar / editar proveedor ──
    function nsAccountRow(acc = null) {
        const div = document.createElement('div');
        div.className = 'ns-acc-row';
        if (acc && acc.id) div.dataset.accId = acc.id;   // id de cuenta (upsert al editar)
        div.style = 'display:grid;grid-template-columns:1fr .8fr 1.7fr 1.7fr auto;gap:8px;align-items:end;margin-bottom:8px';
        div.innerHTML = `
            <div><label class="form-label">Banco</label><select class="ns-bank form-control">${BANK_OPTIONS}</select></div>
            <div><label class="form-label">Moneda</label><select class="ns-cur form-control"><option value="PEN">Soles (PEN)</option><option value="USD">Dólares (USD)</option></select></div>
            <div><label class="form-label">N° cuenta</label><input class="ns-num form-control"></div>
            <div><label class="form-label">CCI</label><input class="ns-cci form-control"></div>
            <button type="button" class="btn-remove ns-rm" title="Quitar" style="height:34px">×</button>`;
        if (acc) {
            const bankSel = div.querySelector('.ns-bank');
            const opt = [...bankSel.options].find(o => o.textContent === acc.bank);
            if (opt) bankSel.value = opt.value;
            div.querySelector('.ns-cur').value = acc.currency || 'PEN';
            div.querySelector('.ns-num').value = acc.account_number || '';
            div.querySelector('.ns-cci').value = acc.cci || '';
        }
        return div;
    }

    function fillModal(d) {
        $('ns_ruc').value = d?.ruc || '';
        $('ns_name').value = d?.name || '';
        $('ns_address').value = d?.address || '';
        $('ns_provincia').value = d?.provincia || '';
        $('ns_district').value = d?.district || '';
        $('ns_contact').value = d?.contact || '';
        $('ns_phone').value = d?.phone || '';
        $('ns_email').value = d?.email || '';
        $('ns_active').checked = d ? (d.active !== false) : true;   // nuevo = activo por defecto
        $('ns-accounts').innerHTML = '';
        const accs = (d?.accounts && d.accounts.length) ? d.accounts : [null];
        accs.forEach(a => $('ns-accounts').appendChild(nsAccountRow(a)));
    }

    // Crear
    $('btn-nuevo').addEventListener('click', () => {
        currentId = null;
        $('modal-sup-title').textContent = 'Registrar nuevo proveedor';
        $('ns-save').textContent = 'Guardar proveedor';
        fillModal(null);
        openM('modal-supplier'); $('ns_ruc').focus();
    });

    // Acciones por fila (delegación, sobrevive a paginación y Recargar)
    $('tabla-sup').addEventListener('click', async (e) => {
        // Activar / inactivar (soft-delete)
        const togBtn = e.target.closest('.sup-toggle');
        if (togBtn) {
            const isActive = togBtn.dataset.active === '1';
            const ok = await askConfirm(`¿Seguro que deseas ${isActive ? 'inactivar' : 'activar'} a "${togBtn.dataset.name}"?`, {
                title: isActive ? 'Inactivar proveedor' : 'Activar proveedor',
                confirmText: isActive ? 'Inactivar' : 'Activar',
                danger: isActive,
            });
            if (!ok) return;
            try {
                const res = await fetch(`{{ url('suppliers') }}/${togBtn.dataset.id}/toggle`, {
                    method: 'POST', headers: { 'Accept': 'application/json', ...csrfHeaders() },
                });
                const j = await res.json().catch(() => ({}));
                if (res.ok && j.status === 1) { showToast(j.description, 'success'); await reloadTable(); }
                else if (res.status === 419) { showToast('Tu sesión expiró. Recarga la página (F5) y vuelve a intentar.', 'error'); }
                else { showToast(j.description || 'No se pudo cambiar el estado.', 'error'); }
            } catch (err) { showToast('Error de red.', 'error'); }
            return;
        }

        // Ver cuentas (solo lectura)
        const accBtn = e.target.closest('.sup-accounts');
        if (accBtn) {
            $('acc-sup-name').textContent = accBtn.dataset.name || '';
            const w = ['55%', '40%', '70%', '80%'];
            $('acc-body').innerHTML = Array.from({ length: 3 }).map(() =>
                `<tr>${w.map(width => `<td><span class="skeleton skeleton-text" style="width:${width}"></span></td>`).join('')}</tr>`).join('');
            openM('modal-accounts');
            try {
                const res = await fetch(`{{ url('suppliers') }}/${accBtn.dataset.id}?_=` + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                const j   = await res.json();
                const accs = (res.ok && j.status === 1) ? (j.data.accounts || []) : [];
                $('acc-body').innerHTML = accs.length
                    ? accs.map(a => `<tr><td class="cell-strong">${esc(a.bank)}</td><td>${esc(a.currency)}</td><td class="cell-mono">${esc(a.account_number)}</td><td class="cell-mono">${a.cci ? esc(a.cci) : '—'}</td></tr>`).join('')
                    : '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:14px">Este proveedor no tiene cuentas registradas.</td></tr>';
            } catch (err) {
                $('acc-body').innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--red);padding:14px">Error al cargar las cuentas.</td></tr>';
            }
            return;
        }

        // Editar (lápiz)
        const btn = e.target.closest('.sup-edit');
        if (!btn) return;
        window.blockUI && window.blockUI('Cargando…');
        try {
            const res = await fetch(`{{ url('suppliers') }}/${btn.dataset.id}?_=` + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json();
            if (res.ok && j.status === 1) {
                currentId = j.data.id;
                $('modal-sup-title').textContent = 'Editar proveedor';
                $('ns-save').textContent = 'Guardar cambios';
                fillModal(j.data);
                openM('modal-supplier');
            } else { showToast('No se pudo cargar el proveedor.', 'error'); }
        } catch (err) { showToast('Error al cargar el proveedor.', 'error'); }
        window.unblockUI && window.unblockUI();
    });
    $('ns-add-account').addEventListener('click', () => $('ns-accounts').appendChild(nsAccountRow()));
    $('ns-accounts').addEventListener('click', (e) => {
        if (e.target.classList.contains('ns-rm')) {
            const rows = $('ns-accounts').querySelectorAll('.ns-acc-row');
            if (rows.length > 1) e.target.closest('.ns-acc-row').remove();
        }
    });

    $('ns-save').addEventListener('click', async function () {
        const payload = {
            ruc:       $('ns_ruc').value.trim(),
            name:      $('ns_name').value.trim(),
            address:   $('ns_address').value.trim(),
            provincia: $('ns_provincia').value.trim(),
            district:  $('ns_district').value.trim(),
            contact:   $('ns_contact').value.trim(),
            phone:     $('ns_phone').value.trim(),
            email:     $('ns_email').value.trim(),
            active:    $('ns_active').checked,
            accounts:  [...$('ns-accounts').querySelectorAll('.ns-acc-row')].map(r => ({
                id:             r.dataset.accId || null,
                bank:           r.querySelector('.ns-bank').value,
                currency:       r.querySelector('.ns-cur').value,
                account_number: r.querySelector('.ns-num').value.trim(),
                cci:            r.querySelector('.ns-cci').value.trim(),
            })).filter(a => a.account_number),
        };
        if (!payload.ruc || !payload.name) { showToast('RUC y Razón social son obligatorios.', 'warning'); return; }
        if (!/^\d{11}$/.test(payload.ruc)) { showToast('El RUC debe tener exactamente 11 dígitos.', 'warning'); return; }

        const url     = currentId ? `{{ url('suppliers') }}/${currentId}` : "{{ route('suppliers.store') }}";
        const okMsg   = currentId ? 'Proveedor actualizado' : 'Proveedor registrado';
        const saveTxt = this.textContent;
        this.disabled = true; this.textContent = 'Guardando...';
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...csrfHeaders() },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.status === 1) {
                closeM('modal-supplier');
                showToast(okMsg, 'success');
                await reloadTable();   // refresca la tabla
            } else if (res.status === 419) {
                showToast('Tu sesión expiró. Recarga la página (F5) y vuelve a intentar.', 'error');
            } else {
                const msg = json.description
                    || (json.errors && Object.values(json.errors)[0]?.[0])
                    || json.message || 'No se pudo registrar.';
                showToast(msg, 'error');
            }
        } catch (e) {
            showToast('Error al guardar el proveedor.', 'error');
        }
        this.disabled = false; this.textContent = saveTxt;
    });
})();
</script>
@endpush