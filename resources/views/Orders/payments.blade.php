@extends('layouts.sistema')

@section('title', 'Histórico de Pagos')
@section('page', 'pagos')

@section('page-pretitle', 'Cuentas por Pagar')
@section('page-title', 'Histórico de Pagos')
@section('breadcrumb', 'Histórico de Pagos')

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
                        <label class="form-label">Tipo de orden</label>
                        <select name="format_id" class="form-control">
                            <option value="">Todos los tipos</option>
                            @foreach ($tipos as $abrev => $desc)
                                <option value="{{ $abrev }}">{{ $desc }}</option>
                            @endforeach
                        </select>
                    </div>
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
                        <label class="form-label">Empresa (cuenta origen)</label>
                        <select name="company" class="form-control">
                            <option value="">Todas las empresas</option>
                            @foreach ($companies as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row col-3">
                    <div class="form-group">
                        <label class="form-label">Rango de fecha (depósito)</label>
                        <div class="date-range" data-date-range id="date-range">
                            <input type="text" class="form-control" placeholder="Selecciona un rango..." readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Orden, empresa, N° operación..." autocomplete="off">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
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
                <div class="card-title">Abonos pagados</div>
                <div class="card-subtitle" id="pagos-count">{{ $abonos->count() }} abono(s) con constancia</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="tabla-pagos" style="width:100%">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Empresa</th>
                            <th>Programación</th>
                            <th>Cuota</th>
                            <th style="text-align:right">Monto</th>
                            <th>Vencimiento</th>
                            <th>Fecha depósito</th>
                            <th>Banco origen</th>
                            <th>Cuenta origen</th>
                            <th>N° Operación</th>
                            <th data-orderable="false">Constancia</th>
                        </tr>
                    </thead>
                    <tbody id="pagos-body">
                        @include('Orders.partials.payments-rows')
                    </tbody>
                </table>
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
        pageLength: 20,
        order: [],
        layout: { topStart: null, topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin pagos',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron pagos',
            emptyTable: 'No hay pagos para mostrar',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    };
    let table = new DataTable('#tabla-pagos', tableConfig);

    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-pagos') return true;
        // Fila desde 'settings' (tabla en dibujo), no desde 'table' del closure (reventaría en el re-init).
        const row   = settings.aoData[dataIndex].nTr;
        if (!row) return true;
        const fComp  = form.elements['company'].value;
        const fSched = form.elements['payment_schedule_id'].value;
        const fFmt   = form.elements['format_id'].value;
        const fCurr  = form.elements['currency'].value;
        if (fComp  && row.dataset.company  !== fComp)  return false;
        if (fSched && row.dataset.schedule !== fSched) return false;
        if (fFmt   && row.dataset.format   !== fFmt)   return false;
        if (fCurr  && row.dataset.currency !== fCurr)  return false;
        if (rangeFrom && rangeTo && row.dataset.date) {
            const d = new Date(row.dataset.date + 'T00:00:00');
            if (d < rangeFrom || d > rangeTo) return false;
        }
        return true;
    });

    form.querySelectorAll('select').forEach(sel => sel.addEventListener('change', () => table.draw()));
    form.elements['q'].addEventListener('input', function () { table.search(this.value).draw(); });

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
            const res = await fetch("{{ route('orders.payments.rows') }}?_=" + Date.now(), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            const j   = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) {
                table.destroy();
                document.getElementById('pagos-body').innerHTML = j.data.html;
                table = new DataTable('#tabla-pagos', tableConfig);
                const cnt = document.getElementById('pagos-count');
                if (cnt) cnt.textContent = `${j.data.count} abono(s) con constancia`;
                form.reset();
                if (dr.__clear) dr.__clear();
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
// ── Ver constancia en modal (imagen embebida o PDF en iframe) ──
(function () {
    const openM  = (id) => { const el = document.getElementById(id); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); };
    const closeM = (id) => { const el = document.getElementById(id); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); };

    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]');
        if (c) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && e.target.id === 'modal-constancia-ver') closeM('modal-constancia-ver');
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeM('modal-constancia-ver'); });

    // Delegación sobre la tabla (sobrevive a paginación y al Recargar)
    document.getElementById('tabla-pagos').addEventListener('click', (e) => {
        const cv = e.target.closest('.constancia-link');
        if (!cv) return;
        e.preventDefault();
        const url = cv.dataset.url;
        const isPdf = /\.pdf(\?|$)/i.test(url);
        document.getElementById('cv-info').textContent = `${cv.dataset.code} · Cuota ${cv.dataset.num}`;
        document.getElementById('cv-open').href = url;
        document.getElementById('cv-body').innerHTML = isPdf
            ? `<iframe src="${url}" style="width:100%;height:75vh;border:0;display:block"></iframe>`
            : `<img src="${url}" alt="Constancia" style="max-width:100%;display:block;margin:0 auto">`;
        openM('modal-constancia-ver');
    });
})();
</script>
@endpush