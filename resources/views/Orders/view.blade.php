@extends('layouts.sistema')

@section('title', 'Mis Órdenes')
@section('page', 'orders')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Mis Órdenes')
@section('breadcrumb', 'Mis Órdenes')

@section('page-actions')
    @if (in_array(auth()->user()->user_type, ['JA', 'AA', 'GA']))
        <a href="{{ \Illuminate\Support\Facades\Route::has('orders.create') ? route('orders.create') : '#' }}" class="btn btn-primary">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
            Crear orden
        </a>
    @endif
@endsection

@push('styles')
<style>
    .filtros .row        { margin-bottom: 0; }
    .filtros .form-group { margin-bottom: 10px; }
    /* Resumen en el modal de aprobación */
    #modal-approve .modal-dialog { max-width: 940px; }
    /* Modal de Código de Registro (tabla de documentos) */
    #modal-registro .modal-dialog { max-width: 1040px; }
    #modal-registro .table td, #modal-registro .table th { white-space: nowrap; }
    #modal-registro .reg-input { min-width: 200px; }
    .sum-fs { border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:14px 16px 8px; margin-bottom:14px; }
    .sum-fs > legend { font-size:11px; font-weight:700; color:var(--text-secondary); padding:0 8px; text-transform:uppercase; letter-spacing:.4px; }
    .sum-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px 16px; }
    .sum-l { display:block; font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); font-weight:600; margin-bottom:2px; }
    .sum-v { display:block; font-size:13px; color:var(--text); font-weight:500; line-height:1.4; }
    .sum-just { margin-top:10px; padding-top:10px; border-top:1px solid var(--border-color-light); }
    @media (max-width:760px) { .sum-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:480px) { .sum-grid { grid-template-columns:1fr; } }
</style>
@endpush

@php
    // Color de badge por estado (mismo criterio que Filament)
    $statusClass = fn (int $s) => match ($s) {
        3, 7, 9 => 'status-green',
        2, 5    => 'status-yellow',
        4       => 'status-red',
        default => 'status-blue',
    };
@endphp

@section('content')

    {{-- ── Filtros (DataTables, client-side) ── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form id="filtros-form" class="filtros" onsubmit="return false">
                <div class="row col-4">

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
                        <label class="form-label">Tipo de Orden</label>
                        <select name="format_id" class="form-control">
                            <option value="">Todos los tipos</option>
                            @foreach ($formats as $f)
                                <option value="{{ $f->abrev }}">{{ $f->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Área</label>
                        <select name="area_id" class="form-control">
                            <option value="">Todas las áreas</option>
                            @foreach ($areas as $a)
                                <option value="{{ $a->id }}">{{ $a->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Programación</label>
                        <select name="payment_schedule_id" class="form-control">
                            <option value="">Todas las programaciones</option>
                            @foreach ($schedules as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="row col-4">
                    <div class="form-group">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Código, título, empresa..." autocomplete="off">
                    </div>

                    <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
                        <button type="button" id="btn-limpiar" class="btn btn-outline">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h10M6 6V4a2 2 0 014 0v2M5 6l1 8h4l1-8"/></svg>
                            Limpiar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Tabla (DataTables) ── --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Listado de órdenes</div>
                <div class="card-subtitle">{{ $orders->count() }} orden(es)</div>
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
                    <tbody>
                        @foreach ($orders as $order)
                            @php
                                $detail = $order->detail;
                                $amount = floatval($detail?->amount_neto ?? 0) > 0
                                    ? floatval($detail->amount_neto)
                                    : floatval($detail?->amount_ref ?? 0);
                                $cur = ($detail?->currency === 'USD') ? '$ ' : 'S/ ';
                            @endphp
                            <tr
                                data-status="{{ $order->status }}"
                                data-format="{{ $order->format_id }}"
                                data-area="{{ $detail?->area_id }}"
                                data-schedule="{{ $order->payment_schedule_id }}"
                            >
                                <td class="cell-mono">{{ $order->code }}</td>
                                <td>{{ $order->company->name ?? '—' }}</td>
                                <td>{{ $order->responsible->name ?? '—' }}</td>
                                <td class="cell-strong">{{ \Illuminate\Support\Str::limit($order->title, 35) }}</td>
                                <td><span class="status status-blue">{{ $order->format_id }}</span></td>
                                <td data-order="{{ $order->status }}">
                                    <span class="status {{ $statusClass((int) $order->status) }}">
                                        {{ $statusNames[$order->status] ?? $order->status }}
                                    </span>
                                </td>
                                <td data-order="{{ $order->created_at?->timestamp }}">
                                    {{ $order->created_at?->format('d/m/Y') }}
                                </td>
                                <td class="cell-strong" data-order="{{ $amount }}">
                                    {{ $amount > 0 ? $cur . number_format($amount, 2) : '—' }}
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    @php $acts = $actions($order); @endphp
                                    @if ($canEdit($order))
                                        <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-outline btn-sm" title="Editar" style="color:var(--primary)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                    @endif
                                    @if ($acts['approve'] && !$acts['close'])
                                        <button type="button" class="btn btn-outline btn-sm btn-approve" data-id="{{ $order->id }}" data-code="{{ $order->code }}" data-label="{{ $acts['approveLabel'] }}" title="{{ $acts['approveLabel'] }}" style="color:var(--green)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['code'] || $acts['close'])
                                        {{-- Código (UC1/UC2) o Cierre (UC3/UC4): abre la Vista Contable (lectura + acción + observar) --}}
                                        <a href="{{ route('orders.vistacontable', $order->id) }}" class="btn btn-outline btn-sm" title="{{ $acts['close'] ? 'Terminar flujo' : $acts['codeLabel'] }}" style="color:var(--primary)">
                                            @if ($acts['close'])
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 7a4 4 0 11-4 4M7 15l-3 3M9.5 12.5L7 15M11 11l6.5-6.5"/><circle cx="16.5" cy="7.5" r="2.5"/></svg>
                                            @endif
                                        </a>
                                    @endif
                                    {{-- Los pasos de Código (UC1/UC2) y Cierre (UC3/UC4) observan desde la Vista Contable --}}
                                    @if ($acts['observe'] && !$acts['code'] && !$acts['close'])
                                        <button type="button" class="btn btn-outline btn-sm btn-observe" data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Observar" style="color:#b45309">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['reject'])
                                        <button type="button" class="btn btn-outline btn-sm btn-reject" data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Rechazar" style="color:var(--red)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        </button>
                                    @endif
                                    @if ($acts['docs'])
                                        <button type="button" class="btn btn-outline btn-sm btn-docs" data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Cargar documentos" style="color:var(--primary)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6M9 15h6"/></svg>
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-outline btn-sm btn-timeline"
                                            data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Línea de tiempo">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
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
            <div class="modal-body" id="tl-body" style="min-height:120px">
                <div style="text-align:center;color:var(--text-muted);padding:30px">Cargando…</div>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Aprobar (con resumen de la orden) ── --}}
    <div class="modal-backdrop" id="modal-approve" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff"><span id="approve-action">Aprobar</span> orden <span id="approve-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-approve" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div id="approve-summary"></div>
                <div style="margin-top:6px;font-size:13px;color:var(--text-secondary)">La orden avanzará al siguiente paso del flujo. ¿Confirmas?</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-approve">Cancelar</button>
                <button type="button" id="approve-confirm" class="btn btn-primary">Aprobar</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Observar ── --}}
    <div class="modal-backdrop" id="modal-observe" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Registrar observación <span id="observe-code" style="opacity:.7;font-weight:400"></span></h3>
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
                    <textarea id="obs_comment" class="form-control" rows="3" placeholder="Detalla la observación encontrada..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-observe">Cancelar</button>
                <button type="button" id="observe-save" class="btn btn-primary">Enviar observación</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Rechazar ── --}}
    <div class="modal-backdrop" id="modal-reject" style="display:none">
        <div class="modal-dialog">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Rechazar orden <span id="reject-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-reject" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="banner banner-danger" style="margin-bottom:14px">
                    <svg class="banner-icon" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5 5l6 6M11 5l-6 6"/></svg>
                    <div class="banner-body">El rechazo es <strong>definitivo</strong>. La orden quedará como RECHAZADA.</div>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Motivo del rechazo <span class="required">*</span></label>
                    <textarea id="reject_reason" class="form-control" rows="3" placeholder="Indica el motivo del rechazo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-reject">Cancelar</button>
                <button type="button" id="reject-confirm" class="btn btn-danger">Confirmar rechazo</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Ingresar código ── --}}
    <div class="modal-backdrop" id="modal-code" style="display:none">
        <div class="modal-dialog modal-sm">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff"><span id="code-title">Código</span> <span id="code-order" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-code" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label" id="code-label">Código <span class="required">*</span></label>
                    <input type="text" id="code-input" class="form-control" maxlength="100" placeholder="Ej. REG-2026-00123">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-code">Cancelar</button>
                <button type="button" id="code-confirm" class="btn btn-primary">Guardar y continuar</button>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Código de Registro por documento de pago — UC1 ── --}}
    <div class="modal-backdrop" id="modal-registro" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Código de Registro <span id="reg-code" style="opacity:.7;font-weight:400"></span></h3>
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

    {{-- ── MODAL: Cargar documentos (comprobantes) — AA en [102] ── --}}
    <div class="modal-backdrop" id="modal-docs" style="display:none">
        <div class="modal-dialog modal-lg">
            <div class="modal-header" style="background:var(--text);border-bottom:none">
                <h3 class="modal-title" style="color:#fff">Comprobantes de pago <span id="docs-code" style="opacity:.7;font-weight:400"></span></h3>
                <button type="button" class="modal-close" data-close="modal-docs" style="color:rgba(255,255,255,.7)">×</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="margin-bottom:14px">
                    <table class="table">
                        <thead><tr><th>Tipo</th><th>N° Documento</th><th>Monto</th><th>Emisión</th><th>Archivo</th><th></th></tr></thead>
                        <tbody id="docs-list"></tbody>
                    </table>
                </div>
                <fieldset class="sum-fs">
                    <legend>Agregar comprobante</legend>
                    <div class="sum-grid" style="grid-template-columns:repeat(4,1fr)">
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label">Tipo <span class="required">*</span></label>
                            <select id="dc-type" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach ($voucherTypes as $val => $desc)
                                    <option value="{{ $val }}">{{ $desc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:8px"><label class="form-label">N° Documento <span class="required">*</span></label><input type="text" id="dc-num" class="form-control" placeholder="F001-00012345"></div>
                        <div class="form-group" style="margin-bottom:8px"><label class="form-label">Monto <span class="required">*</span></label><input type="number" id="dc-amount" step="0.01" min="0" class="form-control"></div>
                        <div class="form-group" style="margin-bottom:8px"><label class="form-label">Emisión <span class="required">*</span></label><input type="date" id="dc-date" class="form-control"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:8px"><label class="form-label">Archivo (PDF/imagen) <span class="required">*</span></label><input type="file" id="dc-file" class="form-control" accept="application/pdf,image/*"></div>
                    <div style="text-align:right"><button type="button" id="docs-add" class="btn btn-primary">+ Agregar comprobante</button></div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close="modal-docs">Cerrar</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
{{-- DataTables 2.x (el CSS ya viene en gentelella.css) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filtros-form');

    const table = new DataTable('#tabla-ordenes', {
        pageLength: 15,
        order: [],                      // respeta el orden del servidor (latest)
        layout: {
            topStart: null,             // ocultamos la búsqueda por defecto (usamos la nuestra)
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        language: {
            info: 'Mostrando _START_–_END_ de _TOTAL_',
            infoEmpty: 'Sin órdenes',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron órdenes',
            emptyTable: 'No hay órdenes para mostrar',
            paginate: { previous: '← Anterior', next: 'Siguiente →' },
        },
    });

    // ── Filtro personalizado por dropdowns (lee data-* de cada fila) ──
    DataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-ordenes') return true;

        const row    = table.row(dataIndex).node();
        const fStat  = form.elements['status'].value;
        const fForm  = form.elements['format_id'].value;
        const fArea  = form.elements['area_id'].value;
        const fSched = form.elements['payment_schedule_id'].value;

        if (fStat  && row.dataset.status   !== fStat)  return false;
        if (fForm  && row.dataset.format   !== fForm)  return false;
        if (fArea  && row.dataset.area     !== fArea)  return false;
        if (fSched && row.dataset.schedule !== fSched) return false;
        return true;
    });

    // Selects → redibujan (aplican el filtro personalizado)
    form.querySelectorAll('select').forEach(sel =>
        sel.addEventListener('change', () => table.draw()));

    // Búsqueda → search global de DataTables (mientras escribes)
    form.elements['q'].addEventListener('input', function () {
        table.search(this.value).draw();
    });

    // Limpiar
    document.getElementById('btn-limpiar').addEventListener('click', function () {
        form.reset();
        table.search('').draw();
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

    // Color del punto según el estado destino
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

    // Delegación: botón de timeline en cualquier fila
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

    // Cerrar
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-close="modal-timeline"]') || e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>

<script>
// ── Acciones de flujo del GA: Aprobar / Observar ──
(function () {
    const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
    const table = document.getElementById('tabla-ordenes');

    // Toast del tema
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

    function openM(id) { const el = document.getElementById(id); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); }
    function closeM(id) { const el = document.getElementById(id); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); }
    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]');
        if (c) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && ['modal-approve', 'modal-observe', 'modal-reject', 'modal-code', 'modal-registro', 'modal-docs'].includes(e.target.id)) closeM(e.target.id);
    });

    let currentId = null;
    let currentLabel = 'Aprobar';

    async function post(url, body) {
        const isFD = body instanceof FormData;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, ...(body && !isFD ? { 'Content-Type': 'application/json' } : {}) },
            body: isFD ? body : (body ? JSON.stringify(body) : null),
        });
        return { ok: res.ok, status: res.status, json: await res.json().catch(() => ({})) };
    }

    // Delegación: botones en cada fila
    const escc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    function summarySkeleton() {
        const ln = (w) => `<span class="skeleton skeleton-text" style="width:${w}"></span>`;
        const blk = (n) => `<fieldset class="sum-fs"><div class="sum-grid">${Array.from({ length: n }).map(() => `<div>${ln('55%')}${ln('85%')}</div>`).join('')}</div></fieldset>`;
        return blk(8) + blk(8) + `<fieldset class="sum-fs">${ln('100%')}${ln('100%')}${ln('100%')}</fieldset>`;
    }

    function renderSummary(d) {
        const g = d.general, c = d.condicion, t = d.totales, cuotas = d.cuotas || [];
        const cell = (l, v) => `<div><span class="sum-l">${l}</span><span class="sum-v">${escc(v)}</span></div>`;

        // Condición: la fecha de vencimiento solo aplica AL CONTADO (fraccionado usa el cronograma)
        let cond = cell('Forma de pago', c.forma_pago) + cell('Condición', c.condicion) + cell('Programación', c.programacion);
        if (!d.es_fraccionado) cond += cell('Vencimiento', c.vencimiento);
        if (d.codigo_banco) cond += cell('Código de banco', d.codigo_banco);

        const abCls = (id) => id === 202 ? 'status-green' : (id === 201 ? 'status-blue' : 'status-yellow');
        const doc = (q) => q.constancia
            ? `<a href="${q.constancia}" target="_blank" style="color:var(--primary)">Ver</a>`
            : '<span style="color:var(--text-muted)">—</span>';
        const op = (q) => q.operacion
            ? `<span class="cell-mono">${escc(q.operacion)}</span>`
            : '<span style="color:var(--text-muted)">—</span>';
        const cfecha = (q) => q.const_fecha ? escc(q.const_fecha) : '<span style="color:var(--text-muted)">—</span>';
        const filas = cuotas.length
            ? cuotas.map(q => `<tr><td class="cell-strong">Cuota ${q.numero}</td><td>${escc(q.fecha)}</td><td class="cell-strong" style="text-align:right">${escc(q.monto)}</td><td><span class="status ${abCls(q.estado_id)}">${escc(q.estado)}</span></td><td>${doc(q)}</td><td>${cfecha(q)}</td><td>${op(q)}</td></tr>`).join('')
            : `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:10px">Sin cronograma</td></tr>`;

        // ── Proveedor (cuando hay datos) ──
        const p = d.proveedor;
        let proveedorFs = '';
        if (p) {
            let cuenta = '';
            if (p.cuenta) {
                cuenta = cell('Banco', p.cuenta.banco) + cell('N° de cuenta', p.cuenta.numero) + cell('CCI', p.cuenta.cci) + cell('Moneda cuenta', p.cuenta.moneda);
            }
            proveedorFs = `
                <fieldset class="sum-fs"><legend>Proveedor</legend>
                    <div class="sum-grid">
                        ${cell('RUC', p.ruc)}${cell('Razón social', p.razon_social)}${cell('Contacto', p.contacto)}${cell('Correo', p.email)}
                        ${cell('Domicilio fiscal', p.direccion)}${cell('Distrito', p.distrito)}${cuenta}
                    </div>
                </fieldset>`;
        }

        // ── Detalle de la orden (ítems) ──
        const items = d.items || [];
        const itemRows = items.length
            ? items.map(i => `<tr><td>${escc(i.descripcion)}</td><td style="text-align:right">${escc(i.cantidad)}</td><td style="text-align:right">${escc(i.precio)}</td><td class="cell-strong" style="text-align:right">${escc(i.subtotal)}</td></tr>`).join('')
            : `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:10px">Sin ítems</td></tr>`;
        const detalleFs = `
            <fieldset class="sum-fs"><legend>Detalle de la orden</legend>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Descripción</th><th style="text-align:right">Cant.</th><th style="text-align:right">P. Unitario</th><th style="text-align:right">Subtotal</th></tr></thead>
                    <tbody>${itemRows}</tbody>
                </table></div>
            </fieldset>`;

        // ── Comprobantes de pago ──
        const comps = d.comprobantes || [];
        let compsFs = '';
        if (comps.length) {
            const rows = comps.map(x => `<tr>
                <td>${escc(x.tipo)}</td>
                <td class="cell-mono">${escc(x.numero)}</td>
                <td style="text-align:right">${escc(x.monto)}</td>
                <td>${escc(x.fecha)}</td>
                <td class="cell-mono">${x.cod_registro ? escc(x.cod_registro) : '—'}</td>
                <td>${x.path ? `<a href="${x.path}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
            </tr>`).join('');
            compsFs = `
                <fieldset class="sum-fs"><legend>Comprobantes de pago</legend>
                    <div class="table-responsive"><table class="table">
                        <thead><tr><th>Tipo</th><th>N° Doc</th><th style="text-align:right">Monto</th><th>Emisión</th><th>Cód. Registro</th><th>Archivo</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table></div>
                </fieldset>`;
        }

        // ── Documentos anexos ──
        const anexos = d.anexos || [];
        let anexosFs = '';
        if (anexos.length) {
            const rows = anexos.map(x => `<tr>
                <td>${escc(x.tipo)}</td>
                <td>${escc(x.comentario)}</td>
                <td>${x.path ? `<a href="${x.path}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
            </tr>`).join('');
            anexosFs = `
                <fieldset class="sum-fs"><legend>Documentos anexos</legend>
                    <div class="table-responsive"><table class="table">
                        <thead><tr><th>Tipo</th><th>Comentario</th><th>Archivo</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table></div>
                </fieldset>`;
        }

        document.getElementById('approve-summary').innerHTML = `
            <fieldset class="sum-fs"><legend>Datos generales</legend>
                <div class="sum-grid">
                    ${cell('Empresa', g.empresa)}${cell('Tipo de orden', g.tipo)}${cell('Categoría', g.categoria)}${cell('Moneda', g.moneda)}
                    ${cell('Título', g.titulo)}${cell('Sede', g.sede)}${cell('Área', g.area)}${cell('Centros de costo', g.cc)}
                </div>
                <div class="sum-just"><span class="sum-l">Justificación</span><span class="sum-v">${escc(g.justificacion)}</span></div>
            </fieldset>
            ${proveedorFs}
            ${detalleFs}
            <fieldset class="sum-fs"><legend>Condición de pago</legend>
                <div class="sum-grid">
                    ${cond}
                    ${cell('Subtotal', t.subtotal)}${cell('IGV', t.igv)}${cell('Descuento', t.descuento)}${cell('Total', t.total)}
                </div>
            </fieldset>
            <fieldset class="sum-fs"><legend>Cronograma de pagos</legend>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Cuota</th><th>Vencimiento</th><th style="text-align:right">Monto</th><th>Estado abono</th><th>Documento</th><th>F. Subida</th><th>N° Operación</th></tr></thead>
                    <tbody>${filas}</tbody>
                </table></div>
            </fieldset>
            ${compsFs}
            ${anexosFs}`;
    }

    table.addEventListener('click', async (e) => {
        const ap = e.target.closest('.btn-approve');
        if (ap) {
            currentId = ap.dataset.id;
            currentLabel = ap.dataset.label || 'Aprobar';
            document.getElementById('approve-code').textContent = ap.dataset.code;
            document.getElementById('approve-action').textContent = currentLabel;
            document.getElementById('approve-confirm').textContent = currentLabel;
            document.getElementById('approve-summary').innerHTML = summarySkeleton();
            openM('modal-approve');
            try {
                const res = await fetch(`{{ url('orders') }}/${currentId}/summary`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                renderSummary(json.data);
            } catch (err) {
                document.getElementById('approve-summary').innerHTML = `<div style="padding:20px;text-align:center;color:var(--red)">No se pudo cargar el resumen.</div>`;
            }
            return;
        }
        const cd = e.target.closest('.btn-code');
        if (cd) {
            currentId = cd.dataset.id;
            // Código de Registro = por documento de pago (modal nueva); el resto, modal simple
            if (cd.dataset.mode === 'perdoc') {
                document.getElementById('reg-code').textContent = cd.dataset.code;
                regSkeleton();
                openM('modal-registro');
                loadRegistro();
                return;
            }
            const label = cd.dataset.label || 'Código';
            document.getElementById('code-title').textContent = label;
            document.getElementById('code-label').innerHTML = label + ' <span class="required">*</span>';
            document.getElementById('code-order').textContent = cd.dataset.code;
            document.getElementById('code-input').value = '';
            openM('modal-code');
            return;
        }
        const dc = e.target.closest('.btn-docs');
        if (dc) {
            currentId = dc.dataset.id;
            document.getElementById('docs-code').textContent = dc.dataset.code;
            ['dc-type','dc-num','dc-amount','dc-date','dc-file'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('docs-list').innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:14px">Cargando…</td></tr>`;
            openM('modal-docs');
            loadDocs();
            return;
        }
        const ob = e.target.closest('.btn-observe');
        if (ob) {
            currentId = ob.dataset.id;
            document.getElementById('observe-code').textContent = ob.dataset.code;
            document.getElementById('obs_type').value = '';
            document.getElementById('obs_comment').value = '';
            openM('modal-observe');
            return;
        }
        const rj = e.target.closest('.btn-reject');
        if (rj) {
            currentId = rj.dataset.id;
            document.getElementById('reject-code').textContent = rj.dataset.code;
            document.getElementById('reject_reason').value = '';
            openM('modal-reject');
        }
    });

    // Confirmar avance (aprobar/sustentar/conforme/cerrar/reenviar)
    document.getElementById('approve-confirm').addEventListener('click', async function () {
        this.disabled = true; this.textContent = 'Procesando...';
        try {
            const r = await post(`{{ url('orders') }}/${currentId}/approve`);
            if (r.ok && r.json.status === 1) {
                showToast(r.json.description, 'success');
                setTimeout(() => location.reload(), 900);
                return;
            }
            showToast(r.json.description || 'No se pudo completar la acción.', 'error');
        } catch (err) { showToast('Error al procesar.', 'error'); }
        this.disabled = false; this.textContent = currentLabel;
    });

    // Guardar código (registro/banco)
    document.getElementById('code-confirm').addEventListener('click', async function () {
        const codigo = document.getElementById('code-input').value.trim();
        if (!codigo) { showToast('Ingresa el código.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Guardando...';
        try {
            const r = await post(`{{ url('orders') }}/${currentId}/code`, { codigo });
            if (r.ok && r.json.status === 1) {
                showToast(r.json.description, 'success');
                setTimeout(() => location.reload(), 900);
                return;
            }
            showToast(r.json.description || 'No se pudo guardar el código.', 'error');
        } catch (err) { showToast('Error al guardar el código.', 'error'); }
        this.disabled = false; this.textContent = 'Guardar y continuar';
    });

    // Enviar observación
    document.getElementById('observe-save').addEventListener('click', async function () {
        const obs_type = document.getElementById('obs_type').value;
        const obs_comment = document.getElementById('obs_comment').value.trim();
        if (!obs_type || !obs_comment) { showToast('Completa el tipo y el comentario.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Enviando...';
        try {
            const r = await post(`{{ url('orders') }}/${currentId}/observe`, { obs_type, obs_comment });
            if (r.ok && r.json.status === 1) {
                showToast(r.json.description, 'success');
                setTimeout(() => location.reload(), 900);
                return;
            }
            showToast(r.json.description || 'No se pudo registrar.', 'error');
        } catch (err) { showToast('Error al observar.', 'error'); }
        this.disabled = false; this.textContent = 'Enviar observación';
    });

    // Confirmar rechazo
    document.getElementById('reject-confirm').addEventListener('click', async function () {
        const reason = document.getElementById('reject_reason').value.trim();
        if (!reason) { showToast('Indica el motivo del rechazo.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Rechazando...';
        try {
            const r = await post(`{{ url('orders') }}/${currentId}/reject`, { reject_reason: reason });
            if (r.ok && r.json.status === 1) {
                showToast(r.json.description, 'success');
                setTimeout(() => location.reload(), 900);
                return;
            }
            showToast(r.json.description || 'No se pudo rechazar.', 'error');
        } catch (err) { showToast('Error al rechazar.', 'error'); }
        this.disabled = false; this.textContent = 'Confirmar rechazo';
    });

    // ── Cargar documentos (comprobantes) ──
    const docsList = document.getElementById('docs-list');

    async function loadDocs() {
        try {
            const res = await fetch(`{{ url('orders') }}/${currentId}/comprobantes`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            renderDocs(json.data || []);
        } catch (e) {
            docsList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--red);padding:14px">Error al cargar.</td></tr>`;
        }
    }
    function renderDocs(list) {
        if (!list.length) { docsList.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:14px;font-style:italic">Sin comprobantes aún.</td></tr>`; return; }
        const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
        docsList.innerHTML = list.map(d => `
            <tr>
                <td>${esc(d.type_label)}</td>
                <td class="cell-mono">${esc(d.document_number)}</td>
                <td class="cell-strong">${esc(d.amount)}</td>
                <td>${esc(d.emission_date)}</td>
                <td>${d.path ? `<a href="${esc(d.path)}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
                <td style="text-align:center"><button type="button" class="btn-remove docs-rm" data-fid="${d.id}" style="border:none;background:none;color:var(--red);cursor:pointer;font-size:18px">×</button></td>
            </tr>`).join('');
    }

    // Agregar comprobante
    document.getElementById('docs-add').addEventListener('click', async function () {
        const type = document.getElementById('dc-type').value;
        const num  = document.getElementById('dc-num').value.trim();
        const amt  = document.getElementById('dc-amount').value;
        const date = document.getElementById('dc-date').value;
        const file = document.getElementById('dc-file').files[0];
        if (!type || !num || !amt || !date || !file) { showToast('Completa todos los campos del comprobante.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Subiendo...';
        const fd = new FormData();
        fd.append('type_file', type); fd.append('document_number', num); fd.append('amount', amt);
        fd.append('emission_date', date); fd.append('file', file);
        const r = await post(`{{ url('orders') }}/${currentId}/comprobante`, fd);
        if (r.ok && r.json.status === 1) {
            showToast(r.json.description, 'success');
            ['dc-type','dc-num','dc-amount','dc-date','dc-file'].forEach(id => document.getElementById(id).value = '');
            loadDocs();
        } else { showToast(r.json.description || 'No se pudo cargar.', 'error'); }
        this.disabled = false; this.textContent = '+ Agregar comprobante';
    });

    // Eliminar comprobante
    docsList.addEventListener('click', async (e) => {
        const rm = e.target.closest('.docs-rm');
        if (!rm) return;
        rm.disabled = true;
        const r = await post(`{{ url('orders') }}/${currentId}/comprobante/${rm.dataset.fid}/delete`);
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); loadDocs(); }
        else { showToast(r.json.description || 'No se pudo eliminar.', 'error'); rm.disabled = false; }
    });

    // ── Código de Registro por documento de pago (UC1) ──
    const regList = document.getElementById('reg-list');
    const escr = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    function regSkeleton(n = 3) {
        const w = ['60%', '70%', '50%', '65%', '40%', '90%'];
        regList.innerHTML = Array.from({ length: n }).map(() => `
            <tr>${w.map(width => `<td><span class="skeleton skeleton-text" style="width:${width}"></span></td>`).join('')}</tr>`).join('');
    }

    async function loadRegistro() {
        try {
            const res = await fetch(`{{ url('orders') }}/${currentId}/comprobantes`, { headers: { 'Accept': 'application/json' } });
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
                <td>${escr(d.type_label)}</td>
                <td class="cell-mono">${escr(d.document_number)}</td>
                <td class="cell-strong" style="text-align:right">${escr(d.amount)}</td>
                <td>${escr(d.emission_date)}</td>
                <td>${d.path ? `<a href="${escr(d.path)}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="text" class="form-control reg-input" data-fid="${d.id}" maxlength="100" value="${escr(d.registration_code)}" placeholder="REG-2026-00123" style="height:34px">
                        <button type="button" class="btn btn-primary btn-sm reg-save" data-fid="${d.id}" title="Guardar código">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    // Guardar código de una fila
    regList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.reg-save');
        if (!btn) return;
        const input = regList.querySelector(`.reg-input[data-fid="${btn.dataset.fid}"]`);
        const codigo = (input.value || '').trim();
        if (!codigo) { showToast('Ingresa el código de registro.', 'warning'); return; }
        btn.disabled = true;
        const r = await post(`{{ url('orders') }}/${currentId}/registro/${btn.dataset.fid}`, {
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo }),
        });
        if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); }
        else { showToast(r.json.description || 'No se pudo guardar.', 'error'); }
        btn.disabled = false;
    });

    // Avanzar a Código de Banco
    document.getElementById('reg-advance').addEventListener('click', async function () {
        this.disabled = true; this.textContent = 'Avanzando...';
        const r = await post(`{{ url('orders') }}/${currentId}/registro-advance`);
        if (r.ok && r.json.status === 1) {
            showToast(r.json.description, 'success');
            setTimeout(() => location.reload(), 900);
            return;
        }
        showToast(r.json.description || 'No se pudo avanzar.', 'error');
        this.disabled = false; this.innerHTML = 'Pasar a Código de Banco →';
    });
})();
</script>
@endpush