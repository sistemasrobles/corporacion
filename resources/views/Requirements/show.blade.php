@extends('layouts.sistema')

@php
    $cur = $refund->currency === 'USD' ? '$ ' : 'S/ ';
    $statusClass = match ((int) $refund->status) {
        2, 7, 9, 10 => 'status-green',
        1, 4, 8     => 'status-yellow',
        3           => 'status-red',
        default     => 'status-blue',
    };
@endphp

@section('title', 'Requerimiento ' . $refund->code)
@section('page', 'requirements')
@section('page-pretitle', 'Requerimientos')
@section('page-title', $refund->code)
@section('breadcrumb', $refund->code)

@section('page-actions')
    <a href="{{ route('requirements.index') }}" class="btn btn-outline">← Volver</a>
@endsection

@push('styles')
<style>
    .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 28px; }
    .dl dt { font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-top:12px; }
    .dl dd { margin:2px 0 0; font-size:14px; color:var(--text); font-weight:500; }
    .amt { font-size:22px;font-weight:700; }
    .tl { list-style:none;margin:0;padding:0; }
    .tl li { position:relative;padding:0 0 16px 22px;border-left:2px solid var(--border-color); }
    .tl li:last-child { border-left-color:transparent; }
    .tl li::before { content:'';position:absolute;left:-7px;top:2px;width:12px;height:12px;border-radius:50%;background:var(--text);border:2px solid #fff; }
    .tl .tl-when { font-size:12px;color:var(--text-muted); }
    .tl .tl-what { font-size:13px;font-weight:600;color:var(--text); }
    .obs-item { padding:12px 14px;border:1px solid var(--border-color);border-radius:var(--radius);margin-bottom:8px;background:var(--bg-surface-secondary); }
    @media (max-width:900px){ .grid-2 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

<div class="row" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start">

    {{-- ── Columna principal ── --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <div class="card-title">{{ $refund->title }}</div>
                <span class="status {{ $statusClass }}">{{ $refund->statusInfo->name ?? $refund->status }}</span>
            </div>
            <div class="card-body">
                <dl class="dl grid-2">
                    <div><dt>Empresa</dt><dd>{{ $refund->company->name ?? '—' }}</dd></div>
                    <div><dt>Categoría</dt><dd>{{ $refund->category->name ?? '—' }}</dd></div>
                    <div><dt>Área</dt><dd>{{ $refund->area->description ?? '—' }}</dd></div>
                    <div><dt>Centro de costo</dt><dd>{{ $refund->costCenter->description ?? '—' }}</dd></div>
                    <div><dt>AA responsable</dt><dd>{{ $refund->responsible->name ?? '—' }}</dd></div>
                    <div><dt>Creado por</dt><dd>{{ $refund->creator->name ?? '—' }}@if($refund->creator && $refund->creator->id !== $refund->responsible_id) <span class="status status-blue" style="margin-left:4px">{{ $refund->creator->user_type }}</span>@endif</dd></div>
                    <div><dt>Necesita el fondo</dt><dd>{{ $refund->needed_date?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Fecha de creación</dt><dd>{{ $refund->created_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
                <dt style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-top:16px">Justificación</dt>
                <p style="margin:4px 0 0;white-space:pre-wrap;font-size:14px;line-height:1.5">{{ $refund->purpose }}</p>

                @if ($refund->details->isNotEmpty())
                    <dt style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-top:16px">Ítems del fondo (estimado, sin IGV)</dt>
                    <div class="table-responsive" style="margin-top:6px">
                        <table class="table" style="margin:0">
                            <thead><tr><th>Descripción</th><th style="text-align:right">Monto</th></tr></thead>
                            <tbody>
                                @foreach ($refund->details as $d)
                                    <tr><td>{{ $d->description }}</td><td style="text-align:right" class="cell-strong">{{ $cur . number_format($d->estimated_amount, 2) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><td style="text-align:right;font-weight:600">Total</td><td style="text-align:right;font-weight:700">{{ $cur . number_format($refund->details->sum('estimated_amount'), 2) }}</td></tr></tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Beneficiario --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">Beneficiario</div></div>
            <div class="card-body">
                <dl class="dl grid-2">
                    <div><dt>Nombre / Razón social</dt><dd>{{ $refund->beneficiary_name ?? ($refund->beneficiary->name ?? '—') }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ $refund->beneficiary_doc ?: '—' }}</dd></div>
                    <div><dt>Banco destino</dt><dd>{{ $refund->beneficiary_bank ?? '—' }}</dd></div>
                    <div><dt>Cuenta destino</dt><dd class="cell-mono">{{ $refund->beneficiary_account ?? '—' }}</dd></div>
                </dl>
            </div>
        </div>

        {{-- Movimientos de dinero (abono, reembolso, devolución) --}}
        @if ($refund->payments->isNotEmpty())
        @php
            $payLabels = [
                'ABONO_INICIAL'        => ['Abono al beneficiario', 'status-blue'],
                'REEMBOLSO_TRABAJADOR' => ['Reembolso (faltante)', 'status-yellow'],
                'DEVOLUCION_EMPRESA'   => ['Devolución (sobrante)', 'status-green'],
            ];
        @endphp
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">Movimientos de dinero</div></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table" style="margin:0">
                        <thead><tr><th>Concepto</th><th>Fecha</th><th>Origen</th><th>N° operación</th><th style="text-align:right">Monto</th><th>Constancia</th></tr></thead>
                        <tbody>
                            @foreach ($refund->payments as $p)
                                @php $lbl = $payLabels[$p->payment_type] ?? [$p->payment_type, 'status-blue']; @endphp
                                <tr>
                                    <td><span class="status {{ $lbl[1] }}">{{ $lbl[0] }}</span></td>
                                    <td>{{ $p->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $p->bank_origin ?? '—' }}</td>
                                    <td class="cell-mono">{{ $p->transaction_code ?? '—' }}</td>
                                    <td style="text-align:right" class="cell-strong">{{ $cur . number_format($p->amount, 2) }}</td>
                                    <td>@if($p->file_path)<a href="{{ asset('storage/' . $p->file_path) }}" target="_blank" class="link">ver</a>@else<span style="color:var(--text-muted)">—</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Observaciones --}}
        @if ($refund->observations->isNotEmpty())
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">Observaciones</div></div>
            <div class="card-body">
                @foreach ($refund->observations as $o)
                    <div class="obs-item">
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
                            <span><strong>{{ $o->observer->name ?? $o->role }}</strong> · {{ $o->role }}</span>
                            <span>{{ $o->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div style="margin-top:4px;font-size:14px">{{ $o->comment }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if ((int) $refund->status === 3 && $refund->rejection_reason)
        <div class="card" style="margin-bottom:16px;border-color:var(--red)">
            <div class="card-header"><div class="card-title" style="color:var(--red)">Motivo del rechazo</div></div>
            <div class="card-body"><p style="margin:0;font-size:14px">{{ $refund->rejection_reason }}</p></div>
        </div>
        @endif

        {{-- Rendición de comprobantes (solo lectura; se gestiona desde la lista → "Rendir") --}}
        @if ($refund->files->isNotEmpty())
        @php
            $rendido  = $refund->files->sum('amount');
            $abonado  = (float) ($refund->paid_amount ?? 0);
            $devuelto = (float) $refund->payments->where('payment_type', 'DEVOLUCION_EMPRESA')->sum('amount');
            // Balance real: lo disponible (abonado − ya devuelto) menos lo rendido.
            // >0 sobrante por devolver · <0 faltante por reembolsar · 0 cuadra.
            $balance  = round(($abonado - $devuelto) - $rendido, 2);
        @endphp
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">Rendición de gastos</div></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table" style="margin:0">
                        <thead><tr><th>Tipo</th><th>Proveedor</th><th>Documento</th><th>Fecha</th><th style="text-align:right">Monto</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($refund->files as $cf)
                                <tr>
                                    <td>{{ $voucherTypes[$cf->type_file] ?? $cf->type_file }}</td>
                                    <td>{{ $cf->supplier ?? '—' }}</td>
                                    <td class="cell-mono">{{ $cf->document_number }}</td>
                                    <td>{{ $cf->issue_date?->format('d/m/Y') }}</td>
                                    <td style="text-align:right" class="cell-strong">{{ $cur . number_format($cf->amount, 2) }}</td>
                                    <td>@if($cf->file_path)<a href="{{ asset('storage/' . $cf->file_path) }}" target="_blank" class="link">ver</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:24px;padding:12px 16px;border-top:1px solid var(--border-color);font-size:14px">
                    <span>Abonado: <strong>{{ $cur . number_format($abonado, 2) }}</strong></span>
                    @if ($devuelto > 0)<span>Devuelto: <strong>{{ $cur . number_format($devuelto, 2) }}</strong></span>@endif
                    <span>Rendido: <strong>{{ $cur . number_format($rendido, 2) }}</strong></span>
                    @if ($balance > 0)
                        <span>Pendiente por devolver: <strong style="color:var(--red)">{{ $cur . number_format($balance, 2) }}</strong></span>
                    @elseif ($balance < 0)
                        <span>Pendiente por reembolsar: <strong style="color:var(--red)">{{ $cur . number_format(abs($balance), 2) }}</strong></span>
                    @else
                        <span><strong style="color:var(--green)">Cuadra exacto</strong></span>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Columna lateral ── --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="text-align:center">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted)">Monto solicitado</div>
                <div class="amt">{{ $cur . number_format($refund->requested_amount, 2) }}</div>
                @if ($refund->approved_amount)
                    <div style="margin-top:10px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted)">Monto aprobado</div>
                    <div style="font-size:16px;font-weight:600;color:var(--green)">{{ $cur . number_format($refund->approved_amount, 2) }}</div>
                @endif
                @if ($refund->rendered_amount !== null && (int) $refund->status >= 7)
                    <div style="margin-top:10px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted)">Rendido</div>
                    <div style="font-size:16px;font-weight:600">{{ $cur . number_format($refund->rendered_amount, 2) }}</div>
                    @php $d = (float) $refund->difference_amount; @endphp
                    <div style="margin-top:8px;font-size:13px">
                        @if ($d > 0)<span class="status status-green">Sobrante {{ $cur . number_format($d, 2) }}</span>
                        @elseif ($d < 0)<span class="status status-red">Reembolso {{ $cur . number_format(abs($d), 2) }}</span>
                        @else <span class="status status-blue">Cuadra exacto</span>@endif
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Historial de estados</div></div>
            <div class="card-body">
                <ul class="tl">
                    @forelse ($refund->statusLogs as $log)
                        <li>
                            <div class="tl-what">{{ $log->toStatus->name ?? $log->to_status }}</div>
                            <div class="tl-when">{{ $log->changer->name ?? '—' }} · {{ $log->changed_at?->format('d/m/Y H:i') }}</div>
                            @if ($log->notes)<div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ $log->notes }}</div>@endif
                        </li>
                    @empty
                        <li style="border:none">Sin movimientos.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection
