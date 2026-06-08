@extends('layouts.sistema')

@section('title', 'Orden ' . $order->code)
@section('page', 'orders')

@section('page-pretitle', 'Gestión')
@section('page-title', 'Orden · ' . $order->code)
@section('breadcrumb', $order->code)

@php
    $backUrl = request('from') === 'history' ? route('orders.history') : route('orders.view');
@endphp

@section('page-actions')
    <a href="{{ $backUrl }}" class="btn btn-outline">Volver</a>
@endsection

@push('styles')
<style>
    .sec-card { margin-bottom: 16px; }
    .sec-num {
        display:inline-flex;align-items:center;justify-content:center;
        width:22px;height:22px;border-radius:6px;background:var(--text);color:#fff;
        font-size:12px;font-weight:700;margin-right:8px;
    }
    .info-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px 24px; }
    .info-grid.cols-3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .info { min-width:0; }
    .info-label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); font-weight:600; margin-bottom:3px; }
    .info-value { font-size:13.5px; color:var(--text); font-weight:500; word-break:break-word; }
    .info-value.muted { color:var(--text-muted); font-weight:400; }
    .chip-tag { display:inline-block; background:var(--primary-lt); color:var(--primary); font-size:12px; font-weight:500; padding:2px 10px; border-radius:12px; margin:2px 4px 2px 0; }
    .totals-box { display:flex;align-items:center;justify-content:flex-end;gap:24px;flex-wrap:wrap;padding:12px 16px;background:var(--bg-surface-secondary);border:1px solid var(--border-color);border-radius:var(--radius-lg);margin-top:8px; }
    .tot-item { display:flex;flex-direction:column;align-items:flex-end;gap:1px; }
    .tot-label { font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);font-weight:600; }
    .tot-val { font-size:13.5px;font-weight:600;color:var(--text); }
    .tot-main { padding-left:20px;border-left:1px solid var(--border-color); }
    .tot-main .tot-val { font-size:20px;font-weight:700; }
    @media (max-width: 900px) { .info-grid, .info-grid.cols-3 { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@section('content')

    {{-- Cabecera con estado --}}
    <div class="card sec-card">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <div>
                    <div class="info-label">Código</div>
                    <div class="cell-mono" style="font-size:15px;font-weight:600">{{ $order->code }}</div>
                </div>
                <div>
                    <div class="info-label">Estado</div>
                    <span class="status {{ $statusClass }}" style="font-size:13px">{{ $statusLabel }}</span>
                </div>
                <div>
                    <div class="info-label">Solicitante</div>
                    <div class="info-value">{{ $vm['creador'] }}</div>
                </div>
                <div>
                    <div class="info-label">Responsable</div>
                    <div class="info-value">{{ $vm['responsable'] }}</div>
                </div>
                <div>
                    <div class="info-label">Fecha de creación</div>
                    <div class="info-value">{{ $order->created_at?->format('d/m/Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 1: Datos generales ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">1</span>Datos generales</div></div>
        <div class="card-body">
            <div class="info-grid" style="margin-bottom:16px">
                <div class="info"><span class="info-label">Empresa</span><span class="info-value">{{ $vm['empresa'] }}</span></div>
                <div class="info"><span class="info-label">Tipo de Orden</span><span class="info-value">{{ $vm['formato'] }}</span></div>
                <div class="info"><span class="info-label">Categoría</span><span class="info-value">{{ $vm['categoria'] }}</span></div>
                <div class="info"><span class="info-label">Moneda</span><span class="info-value">{{ $vm['moneda'] }}</span></div>
                <div class="info"><span class="info-label">Título / Asunto</span><span class="info-value">{{ $vm['titulo'] }}</span></div>
                <div class="info"><span class="info-label">Sede</span><span class="info-value">{{ $vm['sede'] }}</span></div>
                <div class="info"><span class="info-label">Área</span><span class="info-value">{{ $vm['area'] }}</span></div>
                <div class="info">
                    <span class="info-label">Centros de Costo</span>
                    <span class="info-value">
                        @forelse ($vm['centros'] as $cc)
                            <span class="chip-tag">{{ $cc }}</span>
                        @empty — @endforelse
                    </span>
                </div>
            </div>
            <div class="info">
                <span class="info-label">Justificación</span>
                <span class="info-value muted">{{ $vm['justificacion'] }}</span>
            </div>
        </div>
    </div>

    {{-- ── 2: Proveedor ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">2</span>Proveedor</div></div>
        <div class="card-body">
            @if ($supplier)
                <div class="info-grid" style="margin-bottom:16px">
                    <div class="info"><span class="info-label">RUC</span><span class="info-value cell-mono">{{ $supplier->ruc }}</span></div>
                    <div class="info" style="grid-column:span 2"><span class="info-label">Razón social</span><span class="info-value">{{ $supplier->name }}</span></div>
                    <div class="info"><span class="info-label">Distrito</span><span class="info-value">{{ $supplier->district ?? '—' }}</span></div>
                    <div class="info"><span class="info-label">Domicilio fiscal</span><span class="info-value">{{ $supplier->address ?? '—' }}</span></div>
                    <div class="info"><span class="info-label">Contacto</span><span class="info-value">{{ $supplier->contact ?? '—' }}</span></div>
                    <div class="info"><span class="info-label">Correo</span><span class="info-value">{{ $supplier->email ?? '—' }}</span></div>
                </div>

                <span class="info-label" style="margin-bottom:6px">Cuentas bancarias</span>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Banco</th><th>Moneda</th><th>N° cuenta</th><th>CCI</th><th>Seleccionada</th></tr></thead>
                        <tbody>
                            @forelse ($supplier->accounts as $a)
                                <tr>
                                    <td class="cell-strong">{{ $a->bank }}</td>
                                    <td><span class="status status-blue">{{ $a->currency }}</span></td>
                                    <td>{{ $a->account_number }}</td>
                                    <td>{{ $a->cci ?? '—' }}</td>
                                    <td>@if ($selectedAccount == $a->id)<span class="status status-green">Sí</span>@else — @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:14px">Sin cuentas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div style="color:var(--text-muted);font-style:italic">Esta orden no tiene proveedor asignado.</div>
            @endif
        </div>
    </div>

    {{-- ── 3: Detalle de la orden ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">3</span>Detalle de la orden</div></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Descripción</th><th style="text-align:right">Cant.</th><th style="text-align:right">P. Unitario</th><th style="text-align:right">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse ($order->products as $p)
                            <tr>
                                <td class="cell-strong">{{ $p->description }}</td>
                                <td style="text-align:right">{{ rtrim(rtrim(number_format($p->quantity, 2), '0'), '.') }}</td>
                                <td style="text-align:right">{{ $cur }} {{ number_format($p->unit_price, 2) }}</td>
                                <td style="text-align:right" class="cell-strong">{{ $cur }} {{ number_format($p->sub_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:14px">Sin ítems.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($d?->observation)
                <div class="info" style="margin-top:14px">
                    <span class="info-label">Observaciones</span>
                    <span class="info-value muted">{{ $d->observation }}</span>
                </div>
            @endif

            <div class="totals-box">
                <div class="tot-item"><span class="tot-label">Subtotal</span><span class="tot-val">{{ $cur }} {{ number_format($d?->sub_total ?? 0, 2) }}</span></div>
                @if ($d?->grabable)
                    <div class="tot-item"><span class="tot-label">IGV (18%)</span><span class="tot-val">{{ $cur }} {{ number_format($d?->igv ?? 0, 2) }}</span></div>
                @endif
                @if (floatval($d?->discount ?? 0) > 0)
                    <div class="tot-item"><span class="tot-label">Descuento{{ $vm['descuentoTipo'] ? ' · ' . $vm['descuentoTipo'] : '' }}</span><span class="tot-val" style="color:var(--red)">− {{ $cur }} {{ number_format($d->discount, 2) }}</span></div>
                @endif
                <div class="tot-item tot-main"><span class="tot-label">Total</span><span class="tot-val">{{ $cur }} {{ number_format($d?->amount_neto ?? $d?->total ?? 0, 2) }}</span></div>
            </div>
        </div>
    </div>

    {{-- ── 4: Condición de pago ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">4</span>Condición de pago</div></div>
        <div class="card-body">
            <div class="info-grid">
                @php $esFraccionado = stripos($vm['condicion'] ?? '', 'fraccionado') !== false; @endphp
                <div class="info"><span class="info-label">Forma de pago</span><span class="info-value">{{ $vm['formaPago'] }}</span></div>
                <div class="info"><span class="info-label">Condición</span><span class="info-value">{{ $vm['condicion'] }}</span></div>
                @unless ($esFraccionado)
                    <div class="info"><span class="info-label">Fecha de vencimiento</span><span class="info-value">{{ $vm['vencimiento'] }}</span></div>
                @endunless
                <div class="info"><span class="info-label">Programación</span><span class="info-value">{{ $vm['programacion'] }}</span></div>
            </div>

            @if ($cuotas->count())
                @php
                    $abStatus = [200 => 'Pendiente', 201 => 'Depositado', 202 => 'Constancia adjuntada'];
                    $abClass  = fn ($s) => $s === 202 ? 'status-green' : ($s === 201 ? 'status-blue' : 'status-yellow');
                @endphp
                <span class="info-label" style="margin:16px 0 6px">Plan de cuotas</span>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Cuota</th><th>Vencimiento</th><th style="text-align:right">Monto</th><th>Estado abono</th><th>N° Operación</th><th>Constancia</th><th>F. Subida</th></tr></thead>
                        <tbody>
                            @foreach ($cuotas as $q)
                                <tr>
                                    <td class="cell-strong">Cuota {{ $q->quota_number }}</td>
                                    <td>{{ $q->due_date ? \Carbon\Carbon::parse($q->due_date)->format('d/m/Y') : '—' }}</td>
                                    <td style="text-align:right" class="cell-strong">{{ $cur }} {{ number_format($q->amount, 2) }}</td>
                                    <td>
                                        @if (isset($abStatus[(int) $q->status]))
                                            <span class="status {{ $abClass((int) $q->status) }}">{{ $abStatus[(int) $q->status] }}</span>
                                        @else
                                            <span style="color:var(--text-muted)">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-mono">{{ $q->operation_number ?: '—' }}</td>
                                    <td>@if ($q->constancia)<a href="/storage/{{ $q->constancia }}" target="_blank" style="color:var(--primary)">Ver</a>@else <span style="color:var(--text-muted)">—</span>@endif</td>
                                    <td>{{ $q->constancia_date ? \Carbon\Carbon::parse($q->constancia_date)->format('d/m/Y H:i') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ── 5: Documentos ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">5</span>Documentos</div></div>
        <div class="card-body">
            <strong style="font-size:13px;display:block;margin-bottom:8px">Comprobantes de pago</strong>
            <div class="table-responsive" style="margin-bottom:20px">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>N° Documento</th><th style="text-align:right">Monto</th><th>Emisión</th><th>Cód. Registro</th><th>F. Subida</th><th>Archivo</th></tr></thead>
                    <tbody>
                        @forelse ($comprobantes as $c)
                            <tr>
                                <td>{{ $c['label'] }}</td>
                                <td class="cell-mono">{{ $c['document'] ?? '—' }}</td>
                                <td style="text-align:right" class="cell-strong">{{ $c['amount'] !== null ? $cur . ' ' . number_format($c['amount'], 2) : '—' }}</td>
                                <td>{{ $c['date'] ?? '—' }}</td>
                                <td class="cell-mono">{{ $c['cod_reg'] ?: '—' }}</td>
                                <td>{{ $c['subida'] ?? '—' }}</td>
                                <td>@if ($c['path'])<a href="/storage/{{ $c['path'] }}" target="_blank" style="color:var(--primary)">Ver</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:14px">Sin comprobantes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <strong style="font-size:13px;display:block;margin-bottom:8px">Documentos anexos</strong>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>Comentario</th><th>F. Subida</th><th>Archivo</th></tr></thead>
                    <tbody>
                        @forelse ($documentos as $doc)
                            <tr>
                                <td>{{ $doc['label'] }}</td>
                                <td>{{ $doc['comentario'] ?? '—' }}</td>
                                <td>{{ $doc['subida'] ?? '—' }}</td>
                                <td>@if ($doc['path'])<a href="/storage/{{ $doc['path'] }}" target="_blank" style="color:var(--primary)">Ver</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:14px">Sin documentos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection