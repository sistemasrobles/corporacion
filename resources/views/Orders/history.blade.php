@extends('layouts.sistema')

@section('title', 'Órdenes Históricas')
@section('page', 'historicas')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Órdenes Históricas')
@section('breadcrumb', 'Órdenes Históricas')

@php
    $statusClass = fn (int $s) => match ($s) {
        3, 7, 9 => 'status-green',
        2, 5    => 'status-yellow',
        4       => 'status-red',
        default => 'status-blue',
    };
@endphp

@push('styles')
<style>
    .filtros .row        { margin-bottom: 0; }
    .filtros .form-group { margin-bottom: 10px; }
</style>
@endpush

@section('content')

    {{-- ── Filtros ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form id="filtros-form" class="filtros" onsubmit="return false">
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
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-control">
                            <option value="">Todos los estados</option>
                            @foreach ($status as $id => $desc)
                                <option value="{{ $id }}">{{ $desc }}</option>
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
                        <label class="form-label">Responsable</label>
                        <select name="user_responsible" class="form-control">
                            <option value="">Todos los responsables</option>
                            @foreach ($responsibles as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
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
                        <label class="form-label">Empresa</label>
                        <select name="company_id" class="form-control">
                            <option value="">Todas las empresas</option>
                            @foreach ($companies as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rango de fecha (creación)</label>
                        <div class="date-range" data-date-range id="date-range">
                            <input type="text" class="form-control" placeholder="Selecciona un rango..." readonly>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
                        <div style="flex:1">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="q" class="form-control" placeholder="Código, título, empresa..." autocomplete="off">
                        </div>
                        <button type="button" id="btn-recargar" class="btn btn-outline" title="Limpia los filtros y trae datos frescos de la base de datos">
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
        <div class="card-header">
            <div>
                <div class="card-title">Todas las órdenes</div>
                <div class="card-subtitle" id="ordenes-count">{{ $orders->count() }} orden(es)</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="tabla-ordenes" style="width:100%">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Empresa</th>
                            <th>Responsable</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Creación</th>
                            <th>Monto</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody id="ordenes-body">
                        @include('Orders.partials.history-rows')
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Línea de tiempo ── --}}
    <div class="modal-backdrop" id="modal-timeline" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Historial de movimientos <span id="tl-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-timeline" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body" id="tl-body" style="min-height:120px"></div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="/src/date-range.js?v={{ @filemtime(public_path('src/date-range.js')) ?: time() }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filtros-form');
    let rangeFrom = null, rangeTo = null;

    const tableConfig = {
        pageLength: 10,
        order: [],
        layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin órdenes',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron órdenes',
            emptyTable: 'No hay órdenes para mostrar',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    };
    let table = new DataTable('#tabla-ordenes', tableConfig);

    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-ordenes') return true;
        // Tomamos la fila desde 'settings' (tabla que se dibuja AHORA), no desde la variable
        // 'table' del closure: durante el re-init aún apunta a la instancia vieja y reventaría.
        const row    = settings.aoData[dataIndex].nTr;
        if (!row) return true;
        const fStat  = form.elements['status'].value;
        const fSched = form.elements['payment_schedule_id'].value;
        const fFmt   = form.elements['format_id'].value;
        const fResp  = form.elements['user_responsible'].value;
        const fComp  = form.elements['company_id'].value;
        const fCurr  = form.elements['currency'].value;
        if (fStat  && row.dataset.status      !== fStat)  return false;
        if (fSched && row.dataset.schedule    !== fSched) return false;
        if (fFmt   && row.dataset.format      !== fFmt)   return false;
        if (fResp  && row.dataset.responsible !== fResp)  return false;
        if (fComp  && row.dataset.company     !== fComp)  return false;
        if (fCurr  && row.dataset.currency    !== fCurr)  return false;
        if (rangeFrom && rangeTo && row.dataset.date) {
            const d = new Date(row.dataset.date + 'T00:00:00');
            if (d < rangeFrom || d > rangeTo) return false;
        }
        return true;
    });

    form.querySelectorAll('select').forEach(sel => sel.addEventListener('change', () => table.draw()));
    form.elements['q'].addEventListener('input', function () { table.search(this.value).draw(); });

    // Date range → filtra por fecha de creación
    const dr = document.getElementById('date-range');
    dr.addEventListener('change', (e) => {
        const f = e.detail.from, t = e.detail.to;
        rangeFrom = f ? new Date(f.getFullYear(), f.getMonth(), f.getDate()) : null;
        rangeTo   = t ? new Date(t.getFullYear(), t.getMonth(), t.getDate(), 23, 59, 59) : null;
        table.draw();
    });

    // Recargar: limpia filtros + trae datos frescos de la BD (emula AJAX reusando el partial)
    document.getElementById('btn-recargar').addEventListener('click', async function () {
        this.disabled = true;
        window.blockUI && window.blockUI('Actualizando…');
        try {
            const res = await fetch("{{ route('orders.history-rows') }}?_=" + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) {
                table.destroy();                                          // libera DataTables (conserva el DOM)
                document.getElementById('ordenes-body').innerHTML = j.data.html;   // filas frescas
                const cnt = document.getElementById('ordenes-count');
                if (cnt) cnt.textContent = `${j.data.count} orden(es)`;   // actualiza el contador
                table = new DataTable('#tabla-ordenes', tableConfig);     // re-inicializa
                form.reset();                                            // limpia los inputs de filtro
                if (dr.__clear) dr.__clear();                            // limpia el rango de fecha
                rangeFrom = rangeTo = null;
                table.search('').draw();
            } else if (res.status === 419) {
                (window.showToast || alert)('Tu sesión expiró. Recarga la página (F5).', 'error');
            } else {
                (window.showToast || alert)(j.description || 'No se pudo recargar.', 'error');
            }
        } catch (e) { (window.showToast || alert)('Error de red al recargar.', 'error'); }
        window.unblockUI && window.unblockUI();
        this.disabled = false;
    });
});
</script>

<script>
// ── Línea de tiempo (historial de movimientos) ──
(function () {
    const TL_BASE = "{{ url('orders/timeline') }}";
    const modal   = document.getElementById('modal-timeline');
    const body    = document.getElementById('tl-body');
    const codeEl  = document.getElementById('tl-code');

    function open()  { modal.style.display = 'flex'; requestAnimationFrame(() => modal.classList.add('show')); document.body.classList.add('modal-open'); }
    function close() { modal.classList.remove('show'); setTimeout(() => modal.style.display = 'none', 180); document.body.classList.remove('modal-open'); }

    function dotClass(s) {
        if (s === 5) return 'is-yellow';
        if ([3, 7, 9].includes(s)) return 'is-green';
        if (s === 4) return 'is-red';
        if ([1, 6].includes(s)) return 'is-blue';
        return 'is-primary';
    }
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    function skeleton(n = 4) {
        return `<div class="timeline">` + Array.from({ length: n }).map(() => `
            <div class="timeline-item">
                <span class="skeleton skeleton-text" style="width:42%"></span>
                <span class="skeleton skeleton-text-lg" style="width:58%"></span>
                <span class="skeleton skeleton-text" style="width:78%"></span>
            </div>`).join('') + `</div>`;
    }

    function render(items) {
        if (!items.length) {
            body.innerHTML = `<div style="text-align:center;color:var(--text-muted);padding:30px">Esta orden aún no tiene movimientos registrados.</div>`;
            return;
        }
        body.innerHTML = `<div class="timeline">` + items.map(it => `
            <div class="timeline-item ${dotClass(it.to_status_id)}">
                <div class="ti-time">${esc(it.date)} · ${esc(it.user)} <span style="color:var(--text-disabled)">(${esc(it.from_role)})</span></div>
                <div class="ti-title">${esc(it.from_status)} → ${esc(it.to_status)}</div>
                ${it.comment ? `<div class="ti-desc">${esc(it.comment)}</div>` : ''}
            </div>`).join('') + `</div>`;
    }

    document.getElementById('tabla-ordenes').addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-timeline');
        if (!btn) return;
        codeEl.textContent = btn.dataset.code || '';
        body.innerHTML = skeleton();
        open();
        try {
            const res  = await fetch(`${TL_BASE}/${btn.dataset.id}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            render(json.data || []);
        } catch (err) {
            body.innerHTML = `<div style="text-align:center;color:var(--red);padding:30px">Error al cargar el historial.</div>`;
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-close="modal-timeline"]') || e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
@endpush