@extends('layouts.sistema')

@php
    $mode        = $mode ?? 'create';
    $isEdit      = $mode === 'edit';
    $isRendicion = $mode === 'rendicion';
    $f           = $refund ?? null;
    $hasData     = $isEdit || $isRendicion;            // hay orden cargada (prefill)
    $ro          = $isRendicion ? 'disabled' : '';     // campos congelados en rendición
    // El GA, al crear, debe asignar el AA responsable. (En edición no aplica.)
    $isGaForm = $mode === 'create' && auth()->user()->user_type === 'GA';
    // GA editando una orden "Por revisar": además de guardar (que aprueba), puede observar/rechazar.
    $gaReview = $isEdit && auth()->user()->user_type === 'GA' && (int) $f->status === 1;
@endphp

@section('title', $hasData ? ($isRendicion ? 'Rendición ' : 'Editar ') . $f->code : 'Nueva Orden de Requerimiento')
@section('page', 'requirements')

@section('page-pretitle', 'Requerimientos')
@section('page-title', $isRendicion ? 'Rendición · ' . $f->code : ($isEdit ? 'Editar ' . $f->code : 'Nueva Orden de Requerimiento'))
@section('breadcrumb', $hasData ? $f->code : 'Nueva Orden')

@section('page-actions')
    <a href="{{ route('requirements.index') }}" class="btn btn-outline">← {{ $isRendicion ? 'Volver' : 'Cancelar' }}</a>
    @if ($isRendicion)
        <button type="button" id="btn-add-comp" class="btn btn-outline">+ Agregar comprobante</button>
        <button type="button" id="btn-rendir" class="btn btn-primary">Finalizar rendición →</button>
    @else
        @if ($gaReview)
            <button type="button" id="btn-observe" class="btn btn-outline" style="border-color:var(--yellow);color:#b45309">Observar</button>
            <button type="button" id="btn-reject" class="btn btn-outline" style="border-color:var(--red);color:var(--red)">Rechazar</button>
        @endif
        <button type="submit" form="req-form" class="btn btn-primary">{{ $isEdit ? (in_array((int) $f->status, [0, 4], true) ? 'Guardar y reenviar →' : 'Guardar y aprobar →') : 'Crear orden →' }}</button>
    @endif
@endsection

@push('styles')
<style>
    .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .grid-4 { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
    .form-group { margin-bottom: 14px; }
    .sec-card   { margin-bottom: 16px; }
    .sec-num { display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:var(--text);color:#fff;font-size:12px;font-weight:700;margin-right:8px; }
    .ro-field { background: var(--bg-surface-secondary) !important; color: var(--text-muted); cursor: not-allowed; }
    .dz-compact { min-height: 110px; padding: 18px; }
    .dz-compact svg { width: 26px; height: 26px; margin-bottom: 6px; }
    .btn-remove { border:none;background:transparent;color:var(--red);cursor:pointer;font-size:18px;line-height:1; }
    .fs { border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 18px 4px; margin-bottom: 16px; }
    .fs > legend { font-size: 11px; font-weight: 700; color: var(--text-secondary); padding: 0 8px; text-transform: uppercase; letter-spacing: .4px; }
    #modal-beneficiary .modal-dialog { max-width: 1000px; }
    #modal-beneficiary .grid-3 { gap: 16px 22px; } #modal-beneficiary .form-group { margin-bottom: 16px; }
    @media (max-width: 900px) { .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

@if (($isEdit || $isRendicion) && !empty($observation))
    <div class="card sec-card" style="border-color:var(--yellow);background:#fffdf3">
        <div class="card-body" style="display:flex;gap:10px;align-items:flex-start">
            <svg viewBox="0 0 16 16" width="18" height="18" fill="none" stroke="#b8860b" stroke-width="1.5" style="flex:none;margin-top:1px"><path d="M8 1.5l6.5 11.5h-13z"/><path d="M8 6.5v3M8 11.2v.3"/></svg>
            <div style="flex:1">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div style="font-weight:600;font-size:13px;color:#8a6d00">Esta orden fue observada</div>
                    <div style="font-size:12px;color:var(--text-muted);white-space:nowrap">{{ $observation['by'] }} · {{ $observation['role'] }} · {{ $observation['date'] }}</div>
                </div>
                <div style="font-size:14px;margin-top:3px">{{ $observation['comment'] }}</div>
            </div>
        </div>
    </div>
@endif

<form id="req-form" method="POST" action="{{ $isEdit ? route('requirements.update', $f) : route('requirements.store') }}">
    @csrf

    {{-- ── CARD 1: Datos del requerimiento ── --}}
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">1</span>Datos del requerimiento</div></div>
        <div class="card-body">
            <div class="grid-4">
                <div class="form-group">
                    <label class="form-label">Empresa <span class="required">*</span></label>
                    <select name="company_id" class="form-control @unless($isRendicion)searchable @endunless" required @disabled($isRendicion)>
                        <option value="">Seleccione...</option>
                        @foreach ($companies as $id => $name)<option value="{{ $id }}" @selected($hasData && $f->company_id == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Área <span class="required">*</span></label>
                    <select name="area_id" id="area_id" class="form-control @unless($isRendicion)searchable @endunless" required @disabled($isRendicion)>
                        <option value="">Seleccione...</option>
                        @foreach ($areas as $id => $desc)<option value="{{ $id }}" @selected($hasData && $f->area_id == $id)>{{ $desc }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Centro de costo <span class="required">*</span></label>
                    <select name="cost_center_id" id="cost_center_id" class="form-control @unless($isRendicion)searchable @endunless" required @unless($hasData) disabled @endunless @disabled($isRendicion)>
                        @if ($hasData)
                            <option value="">Seleccione...</option>
                            @foreach ($costCenters as $id => $desc)<option value="{{ $id }}" @selected($f->cost_center_id == $id)>{{ $desc }}</option>@endforeach
                        @else
                            <option value="">Seleccione área primero</option>
                        @endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría <span class="required">*</span></label>
                    <select name="category_id" class="form-control @unless($isRendicion)searchable @endunless" required @disabled($isRendicion)>
                        <option value="">Seleccione...</option>
                        @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected($hasData && $f->category_id == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="grid-4">
                <div class="form-group">
                    <label class="form-label">Moneda <span class="required">*</span></label>
                    <select name="currency" class="form-control @unless($isRendicion)searchable @endunless" required @disabled($isRendicion)>
                        <option value="">Seleccione...</option>
                        @foreach ($monedas as $m)<option value="{{ $m->value }}" @selected($hasData && $f->currency == $m->value)>{{ $m->description }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha en que se necesita el fondo <span class="required">*</span></label>
                    <input type="date" name="needed_date" class="form-control" required @unless($isRendicion) min="{{ now()->toDateString() }}" @endunless @disabled($isRendicion)
                           value="{{ $hasData ? optional($f->needed_date)->format('Y-m-d') : '' }}">
                </div>
                @if ($isGaForm)
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">AA responsable <span class="required">*</span></label>
                    <select name="responsible_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($aaUsers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <small style="color:var(--text-muted);font-size:12px">Queda a cargo del AA que elijas.</small>
                </div>
                @endif
            </div>
            <div class="form-group">
                <label class="form-label">Título <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" maxlength="250" required placeholder="Ej. Caja para gastos operativos de junio" value="{{ $hasData ? $f->title : '' }}" @disabled($isRendicion)>
            </div>

            <div class="form-group">
                <label class="form-label">Justificación <span class="required">*</span></label>
                <textarea name="purpose" id="purpose" class="form-control" rows="3" maxlength="1000" required placeholder="Describe el motivo del fondo y los gastos a cubrir..." @disabled($isRendicion)>{{ $hasData ? $f->purpose : '' }}</textarea>
                @unless($isRendicion)<div style="text-align:right;font-size:12px;color:var(--text-muted);margin-top:2px"><span id="just-count">0</span>/1000</div>@endunless
            </div>

            {{-- Ítems del fondo (estimado, sin IGV) --}}
            <fieldset class="fs" style="margin-bottom:0">
                <legend>Ítems del fondo (estimado, sin IGV)</legend>
                @unless ($isRendicion)
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <small style="color:var(--text-muted);font-size:12px">Desglosa en qué se usará el fondo. El monto solicitado es la suma de estos ítems.</small>
                    <button type="button" id="add-item" class="btn btn-outline btn-sm">+ Agregar ítem</button>
                </div>
                @endunless
                <div class="table-responsive">
                    <table class="table" style="margin:0">
                        <thead><tr><th style="width:60%">Descripción</th><th style="text-align:right">Monto (sin IGV)</th>@unless($isRendicion)<th></th>@endunless</tr></thead>
                        <tbody id="items-body">
                            @if ($isRendicion)
                                @foreach ($items as $it)
                                    <tr><td>{{ $it['description'] }}</td><td style="text-align:right" class="cell-strong">{{ number_format((float) $it['amount'], 2) }}</td></tr>
                                @endforeach
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="text-align:right;font-weight:600">Total</td>
                                <td style="text-align:right;font-weight:700" id="items-total">{{ $isRendicion ? number_format(collect($items)->sum(fn($i) => (float) $i['amount']), 2) : '0.00' }}</td>
                                @unless($isRendicion)<td></td>@endunless
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </fieldset>
        </div>
    </div>

    {{-- ── CARD 2: Beneficiario ── --}}
    <div class="card sec-card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
            <div class="card-title"><span class="sec-num">2</span>Beneficiario <small class="card-subtitle">(quien recibe el fondo)</small></div>
            <button type="button" id="btn-open-benef" class="btn btn-outline btn-sm" style="display:none">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
                Registrar nuevo beneficiario
            </button>
        </div>
        <div class="card-body">
            @if ($isRendicion)
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Beneficiario</label><input type="text" class="form-control ro-field" readonly value="{{ $f->beneficiary_name ?? ($f->beneficiary->name ?? '—') }}{{ $f->beneficiary_doc ? ' — ' . $f->beneficiary_doc : '' }}"></div>
                    <div class="form-group"><label class="form-label">Banco destino</label><input type="text" class="form-control ro-field" readonly value="{{ $f->beneficiary_bank ?? '—' }}"></div>
                    <div class="form-group"><label class="form-label">Cuenta destino</label><input type="text" class="form-control ro-field" readonly value="{{ $f->beneficiary_account ?? '—' }}"></div>
                </div>
            @else
                <input type="hidden" name="beneficiary_id" id="beneficiary_id">
                <input type="hidden" name="beneficiary_account_id" id="beneficiary_account_id">

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Documento (DNI/RUC)</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="doc_search" class="form-control" placeholder="N° de documento" autocomplete="off">
                            <button type="button" id="btn-buscar-doc" class="btn btn-primary" title="Buscar"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg></button>
                        </div>
                        <div id="doc-msg" style="font-size:12px;margin-top:4px"></div>
                    </div>
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Beneficiario</label>
                        <input type="text" id="benef_name" class="form-control ro-field" readonly placeholder="Busca por documento o registra uno nuevo">
                    </div>
                </div>

                <label class="form-label" style="margin-bottom:6px">Cuenta de destino del abono <span class="required">*</span></label>
                <div id="benef-accounts">
                    <div style="padding:14px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Busca un beneficiario para ver sus cuentas.</div>
                </div>
            @endif
        </div>
    </div>

    @if ($isRendicion)
    {{-- ── CARD 3: Documentos de pago (rendición) ── --}}
    @php
        $rendido  = $f->files->sum('amount');
        $abonado  = (float) ($f->paid_amount ?? 0);
        $devuelto = (float) $f->payments->where('payment_type', 'DEVOLUCION_EMPRESA')->sum('amount');
        $balance  = round(($abonado - $devuelto) - $rendido, 2);   // >0 por devolver · <0 por reembolsar · 0 cuadra
        $curr = $f->currency === 'USD' ? '$ ' : 'S/ ';
    @endphp
    <div class="card sec-card">
        <div class="card-header"><div class="card-title"><span class="sec-num">3</span>Documentos de pago <small class="card-subtitle">(comprobantes de gasto)</small></div></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" style="margin:0">
                    <thead><tr><th>Tipo</th><th>Proveedor</th><th>Documento</th><th>Fecha</th><th style="text-align:right">Monto</th><th></th><th></th></tr></thead>
                    <tbody id="comp-body">
                        @forelse ($f->files as $cf)
                            <tr>
                                <td>{{ $voucherTypes[$cf->type_file] ?? $cf->type_file }}</td>
                                <td>{{ $cf->supplier ?? '—' }}</td>
                                <td class="cell-mono">{{ $cf->document_number }}</td>
                                <td>{{ $cf->issue_date?->format('d/m/Y') }}</td>
                                <td style="text-align:right" class="cell-strong">{{ $curr . number_format($cf->amount, 2) }}</td>
                                <td>@if($cf->file_path)<a href="{{ \App\Support\FileStorage::url($cf->file_path) }}" target="_blank" class="link">ver</a>@endif</td>
                                <td style="text-align:center"><button type="button" class="btn-remove btn-del-comp" data-id="{{ $cf->id }}" title="Eliminar">×</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);font-style:italic;padding:18px">Aún no hay comprobantes cargados. Usa "Agregar comprobante".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:24px;padding:12px 16px;border-top:1px solid var(--border-color);font-size:14px">
                <span>Abonado: <strong>{{ $curr . number_format($abonado, 2) }}</strong></span>
                @if ($devuelto > 0)<span>Devuelto: <strong>{{ $curr . number_format($devuelto, 2) }}</strong></span>@endif
                <span>Rendido: <strong>{{ $curr . number_format($rendido, 2) }}</strong></span>
                @if ($balance > 0)
                    <span>Pendiente por devolver: <strong style="color:var(--red)">{{ $curr . number_format($balance, 2) }}</strong></span>
                @elseif ($balance < 0)
                    <span>Pendiente por reembolsar: <strong style="color:var(--red)">{{ $curr . number_format(abs($balance), 2) }}</strong></span>
                @else
                    <span><strong style="color:var(--green)">Cuadra exacto</strong></span>
                @endif
            </div>
        </div>
    </div>
    @endif
</form>

{{-- ── MODAL: Registrar beneficiario ── --}}
<div class="modal-backdrop" id="modal-beneficiary" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff">Registrar nuevo beneficiario</h3>
            <button type="button" class="modal-close" data-close="modal-beneficiary" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <fieldset class="fs">
                <legend>Datos generales</legend>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Tipo doc.</label><select id="nb_doc_type" class="form-control"><option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CE">CE</option></select></div>
                    <div class="form-group"><label class="form-label">N° documento <span class="required">*</span></label><input id="nb_doc_number" class="form-control" maxlength="20"></div>
                    <div class="form-group"><label class="form-label">Nombre / Razón social <span class="required">*</span></label><input id="nb_name" class="form-control" maxlength="250"></div>
                </div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Correo</label><input id="nb_email" type="email" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Teléfono</label><input id="nb_phone" class="form-control"></div>
                </div>
            </fieldset>
            <fieldset class="fs">
                <legend>Cuentas bancarias</legend>
                <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
                    <button type="button" id="nb-add-account" class="btn btn-outline btn-sm">+ Agregar cuenta</button>
                </div>
                <div id="nb-accounts"></div>
            </fieldset>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-beneficiary">Cancelar</button>
            <button type="button" id="nb-save" class="btn btn-primary">Guardar beneficiario</button>
        </div>
    </div>
</div>

@if ($gaReview)
{{-- Modal motivo (observar / rechazar) para el GA en edición --}}
<div class="modal-backdrop" id="modal-reason" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header" style="background:var(--text);border-bottom:none">
            <h3 class="modal-title" style="color:#fff"><span id="reason-title">Observar orden</span> <span style="opacity:.7;font-weight:400">{{ $f->code }}</span></h3>
            <button type="button" class="modal-close" data-close="modal-reason" style="color:rgba(255,255,255,.7)">×</button>
        </div>
        <div class="modal-body">
            <div class="banner banner-danger" id="reason-banner" style="margin-bottom:14px;display:none">
                <svg class="banner-icon" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5 5l6 6M11 5l-6 6"/></svg>
                <div class="banner-body">El rechazo es <strong>definitivo</strong>. La orden quedará como RECHAZADA.</div>
            </div>
            <div class="form-group" id="reason-type-wrap">
                <label class="form-label">Tipo de observación <span class="required">*</span></label>
                <select id="reason-type" class="form-control">
                    <option value="">Seleccione...</option>
                    @foreach ($obsTypes as $val => $desc)<option value="{{ $val }}">{{ $desc }}</option>@endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" id="reason-label">Motivo <span class="required">*</span></label>
                <textarea id="reason-text" class="form-control" rows="3" maxlength="1000" placeholder="Escribe el motivo..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-reason">Cancelar</button>
            <button type="button" class="btn btn-primary" id="reason-confirm">Confirmar</button>
        </div>
    </div>
</div>
@endif

@if ($isRendicion)
{{-- Modal: agregar comprobante --}}
<div class="modal-backdrop" id="modal-comp" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff">Agregar comprobante</h3><button type="button" class="modal-close" data-close="modal-comp" style="color:rgba(255,255,255,.7)">×</button></div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group"><label class="form-label">Tipo de comprobante <span class="required">*</span></label>
                    <select id="comp-type" class="form-control"><option value="">Seleccione...</option>@foreach ($voucherTypes as $val => $desc)<option value="{{ $val }}">{{ $desc }}</option>@endforeach</select></div>
                <div class="form-group"><label class="form-label">Proveedor</label><input type="text" id="comp-supplier" class="form-control" maxlength="150" placeholder="Razón social del gasto"></div>
                <div class="form-group"><label class="form-label">N° documento <span class="required">*</span></label><input type="text" id="comp-doc" class="form-control" maxlength="80" placeholder="Ej. F001-123"></div>
                <div class="form-group"><label class="form-label">Fecha de emisión <span class="required">*</span></label><input type="date" id="comp-date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div class="form-group"><label class="form-label">Monto <span class="required">*</span></label><input type="number" id="comp-amount" class="form-control" min="0.01" step="0.01" placeholder="0.00"></div>
                <div class="form-group"><label class="form-label">Archivo (PDF/imagen) <span class="required">*</span></label>
                    <label class="dropzone dz-compact" id="comp-dz" for="comp-file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                        <div class="hint">Suelta el archivo o haz clic para buscar</div>
                        <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                        <input type="file" id="comp-file" accept="application/pdf,image/*" style="display:none">
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-comp">Cancelar</button><button type="button" class="btn btn-primary" id="comp-confirm">Cargar comprobante</button></div>
    </div>
</div>

{{-- Modal de confirmación --}}
<div class="modal-backdrop" id="modal-confirm" style="display:none">
    <div class="modal-dialog modal-sm">
        <div class="modal-header" style="background:var(--text);border-bottom:none"><h3 class="modal-title" style="color:#fff" id="confirm-title">Confirmar</h3><button type="button" class="modal-close" data-close="modal-confirm" style="color:rgba(255,255,255,.7)">×</button></div>
        <div class="modal-body"><p id="confirm-msg" style="margin:0;font-size:14px;line-height:1.5"></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" data-close="modal-confirm">Cancelar</button><button type="button" class="btn btn-primary" id="confirm-ok">Confirmar</button></div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="/src/searchable-select.js?v={{ @filemtime(public_path('src/searchable-select.js')) ?: time() }}"></script>
<script>
function showToast(message, variant = 'success', timeout = 3200) {
    let host = document.querySelector('.toast-host');
    if (!host) { host = document.createElement('div'); host.className = 'toast-host'; host.style.zIndex = 2000; document.body.appendChild(host); }
    const t = document.createElement('div'); t.className = 'toast toast-' + variant; t.textContent = message;
    host.appendChild(t); requestAnimationFrame(() => t.classList.add('show'));
    const close = () => { t.classList.remove('show'); setTimeout(() => t.remove(), 200); };
    const timer = setTimeout(close, timeout); t.addEventListener('click', () => { clearTimeout(timer); close(); });
}
window.showToast = showToast;

document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const BANK_OPTIONS = `@foreach($banks as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach`;

    // ── Modal helpers ──
    const openM  = (id) => { const el = $(id); el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('show')); document.body.classList.add('modal-open'); };
    const closeM = (id) => { const el = $(id); el.classList.remove('show'); setTimeout(() => el.style.display = 'none', 180); document.body.classList.remove('modal-open'); };
    document.addEventListener('click', (e) => {
        const c = e.target.closest('[data-close]'); if (c) closeM(c.dataset.close);
        if (e.target.classList.contains('modal-backdrop') && e.target.id === 'modal-beneficiary') closeM('modal-beneficiary');
    });
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

@unless ($isRendicion)
    // ── Centro de costo dependiente del Área ──
    const areaSel = $('area_id'), ccSel = $('cost_center_id');
    const CC_BASE = "{{ url('requirements/cost-centers') }}";
    areaSel.addEventListener('change', async () => {
        ccSel.disabled = true; ccSel.innerHTML = '<option value="">Cargando...</option>';
        if (ccSel.__ss) ccSel.__ss.setDisabled(true);
        if (!areaSel.value) { ccSel.innerHTML = '<option value="">Seleccione área primero</option>'; return; }
        try {
            const res = await fetch(`${CC_BASE}/${areaSel.value}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            ccSel.innerHTML = '<option value="">Seleccione...</option>' + (json.data || []).map(c => `<option value="${c.id}">${esc(c.description)}</option>`).join('');
            ccSel.disabled = false; if (ccSel.__ss) ccSel.__ss.setDisabled(false);
        } catch (e) { ccSel.innerHTML = '<option value="">Error al cargar</option>'; }
    });

    // ── Contador justificación ──
    const ta = $('purpose'), jc = $('just-count');
    const upd = () => { jc.textContent = ta.value.length; }; ta.addEventListener('input', upd); upd();

    // ── Ítems del fondo (descripción + monto neto) ──
    const itemsBody = $('items-body');
    let itemIdx = 0;
    const fmt = (n) => (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function recalcItems() {
        let total = 0;
        itemsBody.querySelectorAll('.it-amount').forEach(i => { total += parseFloat(i.value) || 0; });
        $('items-total').textContent = fmt(total);
    }
    function addItem(desc = '', amount = '') {
        const i = itemIdx++;
        const tr = document.createElement('tr');
        tr.className = 'it-row';
        tr.innerHTML = `
            <td><input type="text" name="items[${i}][description]" class="form-control it-desc" maxlength="255" placeholder="Ej. Pasajes del mes" value="${String(desc).replace(/"/g,'&quot;')}"></td>
            <td><input type="number" name="items[${i}][amount]" class="form-control it-amount" min="0.01" step="0.01" placeholder="0.00" style="text-align:right" value="${amount}"></td>
            <td style="text-align:center"><button type="button" class="btn-remove it-rm" title="Quitar">×</button></td>`;
        itemsBody.appendChild(tr);
        recalcItems();
    }
    $('add-item').addEventListener('click', () => addItem());
    itemsBody.addEventListener('input', (e) => { if (e.target.classList.contains('it-amount')) recalcItems(); });
    itemsBody.addEventListener('click', (e) => {
        if (e.target.classList.contains('it-rm')) {
            if (itemsBody.querySelectorAll('.it-row').length > 1) { e.target.closest('.it-row').remove(); recalcItems(); }
            else showToast('Debe quedar al menos un ítem.', 'warning');
        }
    });

    // Precarga: ítems existentes (edición) o una fila vacía (creación)
    @if ($isEdit && !empty($items))
        @foreach ($items as $it)
            addItem(@json($it['description']), {{ (float) $it['amount'] }});
        @endforeach
    @else
        addItem();
    @endif

    // ── Beneficiario: buscar / pintar cuentas ──
    const benefId = $('beneficiary_id'), benefAcc = $('beneficiary_account_id');
    const accBox = $('benef-accounts'), docMsg = $('doc-msg'), btnBenef = $('btn-open-benef');

    function clearBenef() {
        benefId.value = ''; benefAcc.value = ''; $('benef_name').value = '';
        accBox.innerHTML = `<div style="padding:14px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Busca un beneficiario para ver sus cuentas.</div>`;
    }
    function renderAccounts(accounts) {
        if (!accounts.length) {
            accBox.innerHTML = `<div style="padding:14px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Este beneficiario no tiene cuentas registradas.</div>`;
            benefAcc.value = ''; return;
        }
        const primary = accounts.find(a => a.is_primary) || accounts[0];
        accBox.innerHTML = `<div class="table-responsive"><table class="table"><thead><tr><th></th><th>Banco</th><th>Moneda</th><th>N° cuenta</th><th>CCI</th></tr></thead><tbody>`
            + accounts.map(a => `<tr>
                <td style="text-align:center"><input type="radio" name="acc_pick" value="${a.id}" ${a.id === primary.id ? 'checked' : ''}></td>
                <td class="cell-strong">${esc(a.bank)}</td><td><span class="status status-blue">${esc(a.currency)}</span></td>
                <td class="cell-mono">${esc(a.account_number)}</td><td class="cell-mono">${a.cci ? esc(a.cci) : '—'}</td></tr>`).join('')
            + `</tbody></table></div>`;
        benefAcc.value = primary.id;
        accBox.querySelectorAll('input[name="acc_pick"]').forEach(r => r.addEventListener('change', () => { benefAcc.value = r.value; }));
    }
    function fillBenef(b, accounts) {
        benefId.value = b.id; $('benef_name').value = `${b.name} (${b.doc_number || '—'})`;
        renderAccounts(accounts || []);
    }

    async function buscarDoc() {
        const doc = $('doc_search').value.trim();
        if (!doc) { docMsg.textContent = 'Ingresa un documento.'; docMsg.style.color = 'var(--red)'; return; }
        docMsg.textContent = 'Buscando...'; docMsg.style.color = 'var(--text-muted)';
        try {
            const res = await fetch(`{{ route('requirements.beneficiary.search') }}?doc=${encodeURIComponent(doc)}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (json.status === 1) {
                fillBenef(json.data.beneficiary, json.data.accounts);
                docMsg.textContent = '✓ Beneficiario encontrado'; docMsg.style.color = 'var(--green)';
                btnBenef.style.display = 'none';
            } else if (json.data && json.data.exists) {
                clearBenef(); docMsg.textContent = json.description; docMsg.style.color = 'var(--red)'; btnBenef.style.display = 'none';
            } else {
                clearBenef(); docMsg.textContent = 'No encontrado. Puedes registrarlo →'; docMsg.style.color = 'var(--red)';
                btnBenef.style.display = ''; $('nb_doc_number').value = doc;
            }
        } catch (e) { docMsg.textContent = 'Error al buscar.'; docMsg.style.color = 'var(--red)'; }
    }
    $('btn-buscar-doc').addEventListener('click', buscarDoc);
    $('doc_search').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); buscarDoc(); } });
    $('doc_search').addEventListener('input', () => { if (benefId.value) { clearBenef(); btnBenef.style.display = 'none'; docMsg.textContent = ''; } });

@if ($isEdit && !empty($benef))
    // ── Precarga del beneficiario actual (modo edición) ──
    fillBenef(@json($benef), @json($benefAccounts));
    (function () {
        const saved = "{{ $f->beneficiary_account_id }}";
        const rb = accBox.querySelector(`input[name="acc_pick"][value="${saved}"]`);
        if (rb) { rb.checked = true; benefAcc.value = saved; }
        $('doc_search').value = @json($benef['doc_number']);
        docMsg.textContent = 'Beneficiario actual'; docMsg.style.color = 'var(--text-muted)';
    })();
@endif

    // ── Modal registrar beneficiario ──
    function nbAccountRow() {
        const div = document.createElement('div');
        div.className = 'nb-acc-row';
        div.style = 'display:grid;grid-template-columns:1fr .8fr 1.7fr 1.7fr auto;gap:8px;align-items:end;margin-bottom:8px';
        div.innerHTML = `
            <div><label class="form-label">Banco</label><select class="nb-bank form-control">${BANK_OPTIONS}</select></div>
            <div><label class="form-label">Moneda</label><select class="nb-cur form-control"><option value="PEN">Soles (PEN)</option><option value="USD">Dólares (USD)</option></select></div>
            <div><label class="form-label">N° cuenta</label><input class="nb-num form-control"></div>
            <div><label class="form-label">CCI</label><input class="nb-cci form-control"></div>
            <button type="button" class="btn-remove nb-rm" title="Quitar" style="height:34px">×</button>`;
        return div;
    }
    $('btn-open-benef').addEventListener('click', () => {
        ['nb_name','nb_doc_number','nb_email','nb_phone'].forEach(id => { if (id !== 'nb_doc_number' || !$('nb_doc_number').value) $(id).value = $(id).value; });
        $('nb_name').value = ''; $('nb_email').value = ''; $('nb_phone').value = '';
        $('nb-accounts').innerHTML = ''; $('nb-accounts').appendChild(nbAccountRow());
        openM('modal-beneficiary'); $('nb_name').focus();
    });
    $('nb-add-account').addEventListener('click', () => $('nb-accounts').appendChild(nbAccountRow()));
    $('nb-accounts').addEventListener('click', (e) => {
        if (e.target.classList.contains('nb-rm')) {
            const rows = $('nb-accounts').querySelectorAll('.nb-acc-row');
            if (rows.length > 1) e.target.closest('.nb-acc-row').remove();
        }
    });

    $('nb-save').addEventListener('click', async function () {
        const payload = {
            name: $('nb_name').value.trim(), doc_type: $('nb_doc_type').value, doc_number: $('nb_doc_number').value.trim(),
            email: $('nb_email').value.trim(), phone: $('nb_phone').value.trim(),
            accounts: [...$('nb-accounts').querySelectorAll('.nb-acc-row')].map(r => ({
                bank: r.querySelector('.nb-bank').value, currency: r.querySelector('.nb-cur').value,
                account_number: r.querySelector('.nb-num').value.trim(), cci: r.querySelector('.nb-cci').value.trim(),
            })).filter(a => a.account_number),
        };
        if (!payload.name || !payload.doc_number) { showToast('Nombre y documento son obligatorios.', 'warning'); return; }
        if (!payload.accounts.length) { showToast('Agrega al menos una cuenta bancaria (con N° de cuenta).', 'warning'); return; }
        this.disabled = true; this.textContent = 'Guardando...';
        try {
            const res = await fetch("{{ route('requirements.beneficiary.store') }}", {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.status === 1) {
                fillBenef(json.data.beneficiary, json.data.accounts);
                $('doc_search').value = json.data.beneficiary.doc_number;
                docMsg.textContent = '✓ Beneficiario registrado'; docMsg.style.color = 'var(--green)';
                closeM('modal-beneficiary'); showToast('Beneficiario registrado', 'success');
            } else {
                showToast(json.description || (json.errors && Object.values(json.errors)[0]?.[0]) || json.message || 'No se pudo registrar.', 'error');
            }
        } catch (e) { showToast('Error al registrar el beneficiario.', 'error'); }
        this.disabled = false; this.textContent = 'Guardar beneficiario';
    });

    // ── Envío del formulario ──
    const form = $('req-form');
    const btn  = document.querySelector('button[form="req-form"]');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        // Validar ítems del fondo
        const rows = [...itemsBody.querySelectorAll('.it-row')];
        const validItems = rows.filter(r => r.querySelector('.it-desc').value.trim() && parseFloat(r.querySelector('.it-amount').value) > 0);
        if (!validItems.length) { showToast('Agrega al menos un ítem con descripción y monto.', 'warning'); return; }
        if (validItems.length !== rows.length) { showToast('Completa descripción y monto en todos los ítems (o quita los vacíos).', 'warning'); return; }
        if (!benefId.value) { showToast('Busca o registra un beneficiario.', 'warning'); return; }
        if (!benefAcc.value) { showToast('Selecciona la cuenta de destino del abono.', 'warning'); return; }
        const original = btn.innerHTML; btn.disabled = true; btn.textContent = 'Guardando...';
        window.blockUI && window.blockUI('Procesando…');
        try {
            const res = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: new FormData(form) });
            if (res.status === 422) {
                const j = await res.json(); showToast(Object.values(j.errors || {})[0]?.[0] || j.message || 'Revisa los campos.', 'warning');
            } else if (res.ok) {
                const j = await res.json();
                if (j.status === 1) { showToast(`${j.description} (${j.data.code})`, 'success'); setTimeout(() => { window.location = j.data.redirect; }, 900); return; }
                showToast(j.description || 'No se pudo crear.', 'error');
            } else { showToast('Error al guardar.', 'error'); }
        } catch (err) { showToast('Error de red al guardar.', 'error'); }
        window.unblockUI && window.unblockUI(); btn.disabled = false; btn.innerHTML = original;
    });

@if ($gaReview)
    // ── GA en edición: observar / rechazar (además de guardar=aprobar) ──
    let reasonMode = null;
    function openReason(m) {
        reasonMode = m;
        const isReject = m === 'reject';
        $('reason-title').textContent = isReject ? 'Rechazar orden' : 'Observar orden';
        $('reason-label').innerHTML = (isReject ? 'Motivo del rechazo' : 'Motivo de la observación') + ' <span class="required">*</span>';
        $('reason-banner').style.display = isReject ? 'flex' : 'none';
        $('reason-type-wrap').style.display = isReject ? 'none' : '';
        $('reason-type').value = '';
        const cf = $('reason-confirm'); cf.className = 'btn ' + (isReject ? 'btn-danger' : 'btn-primary'); cf.textContent = isReject ? 'Confirmar rechazo' : 'Enviar observación';
        $('reason-text').value = ''; openM('modal-reason'); setTimeout(() => $('reason-text').focus(), 100);
    }
    $('btn-observe').addEventListener('click', () => openReason('observe'));
    $('btn-reject').addEventListener('click', () => openReason('reject'));
    $('reason-confirm').addEventListener('click', async () => {
        const txt = $('reason-text').value.trim();
        if (reasonMode === 'observe' && !$('reason-type').value) { showToast('Selecciona el tipo de observación.', 'warning'); return; }
        if (!txt) { showToast('Escribe el motivo.', 'warning'); return; }
        const url = reasonMode === 'observe' ? "{{ route('requirements.observe', $f) }}" : "{{ route('requirements.reject', $f) }}";
        const body = reasonMode === 'observe' ? { obs_type: $('reason-type').value, comment: txt } : { reason: txt };
        window.blockUI && window.blockUI('Procesando…');
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(body) });
            const j = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) { showToast(j.description, 'success'); setTimeout(() => location = j.data.redirect, 800); return; }
            showToast(j.description || (j.errors && Object.values(j.errors)[0]?.[0]) || 'No se pudo completar.', res.status === 409 ? 'warning' : 'error');
        } catch (e) { showToast('Error de red.', 'error'); }
        window.unblockUI && window.unblockUI();
    });
@endif
@endunless

@if ($isRendicion)
    // ── Rendición: documentos de pago (subir/eliminar comprobantes + finalizar) ──
    let __res = null;
    function askConfirm(message, opts = {}) {
        $('confirm-title').textContent = opts.title || 'Confirmar';
        $('confirm-msg').textContent = message;
        const ok = $('confirm-ok'); ok.className = 'btn btn-' + (opts.variant || 'primary'); ok.textContent = opts.okLabel || 'Confirmar';
        openM('modal-confirm');
        return new Promise(r => { __res = r; });
    }
    $('confirm-ok').addEventListener('click', () => { closeM('modal-confirm'); if (__res) { __res(true); __res = null; } });
    document.querySelectorAll('[data-close="modal-confirm"]').forEach(b => b.addEventListener('click', () => { if (__res) { __res(false); __res = null; } }));

    async function sendRend(url, body, isForm, btn) {
        const orig = btn.textContent; btn.disabled = true; btn.textContent = 'Procesando…';
        window.blockUI && window.blockUI('Procesando…');
        try {
            const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };
            if (!isForm) headers['Content-Type'] = 'application/json';
            const res = await fetch(url, { method: 'POST', headers, body: isForm ? body : JSON.stringify(body) });
            const j = await res.json().catch(() => ({}));
            if (res.ok && j.status === 1) { showToast(j.description, 'success'); setTimeout(() => location = j.data.redirect, 700); return; }
            showToast(j.description || (j.errors && Object.values(j.errors)[0]?.[0]) || 'No se pudo completar.', res.status === 409 ? 'warning' : 'error');
        } catch (e) { showToast('Error de red.', 'error'); }
        window.unblockUI && window.unblockUI(); btn.disabled = false; btn.textContent = orig;
    }

    // Dropzone del comprobante
    (function () {
        const dz = $('comp-dz'); if (!dz) return;
        const hint = dz.querySelector('.hint'); const HINT = 'Suelta el archivo o haz clic para buscar';
        const input = () => dz.querySelector('input[type=file]');
        const show = () => { const i = input(); hint.textContent = (i && i.files.length) ? i.files[0].name : HINT; };
        dz.addEventListener('change', show);
        dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', (e) => { e.preventDefault(); dz.classList.remove('over'); const i = input(); if (i && e.dataTransfer.files.length) { i.files = e.dataTransfer.files; show(); } });
        dz.__reset = () => { hint.textContent = HINT; };
    })();
    $('btn-add-comp').addEventListener('click', () => { $('comp-dz') && $('comp-dz').__reset && $('comp-dz').__reset(); openM('modal-comp'); });
    $('comp-confirm').addEventListener('click', function () {
        const type = $('comp-type').value, doc = $('comp-doc').value.trim(), date = $('comp-date').value, amount = $('comp-amount').value, file = $('comp-file').files[0];
        if (!type)   { showToast('Selecciona el tipo de comprobante.', 'warning'); return; }
        if (!doc)    { showToast('Indica el N° de documento.', 'warning'); return; }
        if (!date)   { showToast('Indica la fecha de emisión.', 'warning'); return; }
        if (!amount || parseFloat(amount) <= 0) { showToast('Indica un monto válido.', 'warning'); return; }
        if (!file)   { showToast('Adjunta el archivo del comprobante.', 'warning'); return; }
        const fd = new FormData();
        fd.append('type_file', type); fd.append('supplier', $('comp-supplier').value.trim());
        fd.append('document_number', doc); fd.append('issue_date', date); fd.append('amount', amount); fd.append('file', file);
        sendRend("{{ route('requirements.comprobante.upload', $f) }}", fd, true, this);
    });
    document.querySelectorAll('.btn-del-comp').forEach(b => b.addEventListener('click', function () {
        const self = this;
        askConfirm('¿Eliminar este comprobante?', { title: 'Eliminar comprobante', okLabel: 'Eliminar', variant: 'danger' })
            .then(ok => { if (ok) sendRend("{{ url('requirements/' . $f->id . '/comprobante') }}/" + self.dataset.id + "/delete", {}, false, self); });
    }));
    $('btn-rendir').addEventListener('click', function () {
        if (!document.querySelectorAll('#comp-body .btn-del-comp').length) {
            showToast('Agrega al menos un comprobante de pago antes de finalizar.', 'warning');
            return;
        }
        const self = this;
        askConfirm('¿Finalizar la rendición? Se calculará la diferencia y pasará a validación.', { title: 'Finalizar rendición', okLabel: 'Finalizar' })
            .then(ok => { if (ok) sendRend("{{ route('requirements.rendir', $f) }}", {}, false, self); });
    });
@endif
});
</script>
@endpush