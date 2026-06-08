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
                <div class="row col-3">
                    <div class="form-group">
                        <label class="form-label">Empresa (cuenta origen)</label>
                        <select name="company" class="form-control">
                            <option value="">Todas las empresas</option>
                            @foreach ($companies as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rango de fecha (depósito)</label>
                        <div class="date-range" data-date-range id="date-range">
                            <input type="text" class="form-control" placeholder="Selecciona un rango..." readonly>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
                        <button type="button" id="btn-limpiar" class="btn btn-outline">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h10M6 6V4a2 2 0 014 0v2M5 6l1 8h4l1-8"/></svg>
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="row col-4">
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Orden, empresa, N° operación..." autocomplete="off">
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
                <div class="card-subtitle">{{ $abonos->count() }} abono(s) con constancia</div>
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
                            <th>Cuenta origen</th>
                            <th>N° Operación</th>
                            <th data-orderable="false">Constancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($abonos as $ab)
                            @php
                                $o   = $ab->order;
                                $cur = ($o->detail?->currency === 'USD') ? '$ ' : 'S/ ';
                            @endphp
                            <tr
                                data-company="{{ $ab->source_company_id }}"
                                data-date="{{ $ab->deposit_date ? \Carbon\Carbon::parse($ab->deposit_date)->format('Y-m-d') : '' }}"
                            >
                                <td class="cell-mono">{{ $o->code }}</td>
                                <td>{{ $o->company->name ?? '—' }}</td>
                                <td><span class="status status-blue">{{ \Illuminate\Support\Str::limit($o->paymentSchedule?->name, 14) }}</span></td>
                                <td class="cell-strong">Cuota {{ $ab->quota_number }}</td>
                                <td class="cell-strong" style="text-align:right" data-order="{{ $ab->amount }}">{{ $cur . number_format($ab->amount, 2) }}</td>
                                <td>{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}</td>
                                <td data-order="{{ $ab->deposit_date ? \Carbon\Carbon::parse($ab->deposit_date)->timestamp : 0 }}">
                                    {{ $ab->deposit_date ? \Carbon\Carbon::parse($ab->deposit_date)->format('d/m/Y') : '—' }}
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
                                <td class="cell-mono">{{ $ab->operation_number ?: '—' }}</td>
                                <td>
                                    @if ($ab->constancia)
                                        <a href="/storage/{{ $ab->constancia }}" target="_blank" class="btn btn-outline btn-sm" style="color:var(--primary)">Ver</a>
                                    @else
                                        <span style="color:var(--text-muted)">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="/src/date-range.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filtros-form');
    let rangeFrom = null, rangeTo = null;

    const table = new DataTable('#tabla-pagos', {
        pageLength: 15,
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
    });

    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-pagos') return true;
        const row   = table.row(dataIndex).node();
        const fComp = form.elements['company'].value;
        if (fComp && row.dataset.company !== fComp) return false;
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

    document.getElementById('btn-limpiar').addEventListener('click', function () {
        form.reset();
        if (dr.__clear) dr.__clear();
        rangeFrom = rangeTo = null;
        table.search('').draw();
    });
});
</script>
@endpush