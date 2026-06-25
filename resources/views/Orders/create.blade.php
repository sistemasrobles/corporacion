@extends('layouts.sistema')

@php
    $isEdit = isset($order) && $order;
    $pf = fn ($k, $d = null) => data_get($prefill ?? null, $k, $d);
@endphp

@section('title', $isEdit ? 'Editar Orden' : 'Crear Orden')
@section('page', 'orders')

@section('page-pretitle', 'Gestión')
@section('page-title', $isEdit ? 'Editar Orden · ' . $order->code : 'Crear Orden')
@section('breadcrumb', $isEdit ? 'Editar Orden' : 'Crear Orden')

@if ($isEdit)
    @section('page-subtitle')
        Solicitada el {{ $orderMeta['fecha'] }} · Solicitante: {{ $orderMeta['creador'] }}
        <span class="status {{ $orderMeta['statusClass'] }}" style="margin-left:8px">{{ $orderMeta['statusLabel'] }}</span>
    @endsection
@endif

@section('page-actions')
    <a href="{{ route('orders.view') }}" class="btn btn-outline">Cancelar</a>

    @if ($isEdit && ($acts['observe'] ?? false))
        <button type="button" id="btn-observe" class="btn btn-outline" style="color:#b45309">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Observar
        </button>
    @endif
    @if ($isEdit && ($acts['reject'] ?? false))
        <button type="button" id="btn-reject" class="btn btn-danger">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            Rechazar
        </button>
    @endif

    <button type="submit" form="order-form" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
        {{ $isEdit ? 'Guardar y reenviar' : 'Guardar orden' }}
    </button>
@endsection

@push('styles')
<style>
    .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .grid-4 { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
    .form-group { margin-bottom: 14px; }
    .sec-card   { margin-bottom: 16px; }
    /* Edición restringida: secciones bloqueadas */
    .is-locked .card-body { opacity:.6; }
    .is-locked .card-header { background:#fafafa; }
    .lock-badge {
        display:inline-flex;align-items:center;gap:5px;
        background:#fef3c7;color:#92400e;border:1px solid #fde68a;
        border-radius:999px;padding:3px 10px;font-size:12px;font-weight:600;margin-left:8px;
    }
    .lock-note {
        display:flex;align-items:flex-start;gap:10px;
        background:#fffbeb;border:1px solid #fde68a;color:#92400e;
        border-radius:var(--radius);padding:12px 14px;margin-bottom:16px;font-size:13px;line-height:1.5;
    }
    /* Banner de observación (motivo + quién/cuándo) */
    .obs-note {
        background:#fff7ed;border:1px solid #fed7aa;border-left:4px solid #f97316;
        border-radius:var(--radius);padding:12px 14px;margin-bottom:16px;
    }
    .obs-note-head { display:flex;align-items:center;gap:8px;color:#9a3412;font-weight:700;font-size:13px; }
    .obs-note-meta { margin-left:auto;font-weight:500;font-size:12px;color:#b45309; }
    .obs-note-body { margin-top:8px;color:#7c2d12;font-size:13.5px;line-height:1.6;display:flex;flex-direction:column;gap:2px; }
    .obs-note-l { font-weight:700;color:#9a3412; }
    .sec-num {
        display:inline-flex;align-items:center;justify-content:center;
        width:22px;height:22px;border-radius:6px;
        
        background:var(--text);
        
        color:#fff;
        font-size:12px;font-weight:700;margin-right:8px;
    }
    .items-table input { border:1px solid var(--border-color);border-radius:var(--radius-sm);height:34px;padding:0 8px;width:100%;font:inherit;font-size:13px; }
    .items-table td { vertical-align:middle; }
    .btn-remove { border:none;background:transparent;color:var(--red);cursor:pointer;font-size:18px;line-height:1; }
    .totals-box {
        display:flex;align-items:center;justify-content:flex-end;gap:24px;flex-wrap:wrap;
        padding:12px 16px;background:var(--bg-surface-secondary);
        border:1px solid var(--border-color);border-radius:var(--radius-lg);margin-top:8px;
    }
    .tot-item { display:flex;flex-direction:column;align-items:flex-end;gap:1px; }
    .tot-label { font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);font-weight:600; }
    .tot-val { font-size:13.5px;font-weight:600;color:var(--text); }
    .tot-main { padding-left:20px;border-left:1px solid var(--border-color); }
    .tot-main .tot-val { font-size:20px;font-weight:700; }
    /* Campos autocompletados (solo lectura) */
    .ro-field { background: var(--bg-surface-secondary) !important; color: var(--text-muted); cursor: not-allowed; }
    .ro-field:focus { outline: none; box-shadow: none; border-color: var(--border-color); }
    /* Dropzone compacto para modales */
    .dz-compact { min-height: 120px; padding: 20px; }
    .dz-compact svg { width: 28px; height: 28px; margin-bottom: 6px; }
    /* Fieldset para agrupar en modales */
    .fs { border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 18px 4px; margin-bottom: 16px; }
    .fs > legend { font-size: 11px; font-weight: 700; color: var(--text-secondary); padding: 0 8px; text-transform: uppercase; letter-spacing: .4px; }
    /* Modal de registrar proveedor: más ancho y con más aire entre campos */
    #modal-supplier .modal-dialog { max-width: 1000px; }
    #modal-supplier .grid-3 { gap: 16px 22px; }
    #modal-supplier .form-group { margin-bottom: 16px; }
    /* Modal plan de cuotas */
    #modal-cuotas .modal-dialog { max-width: 840px; }
    #modal-cuotas .cuota-fecha, #modal-cuotas .cuota-monto, #modal-cuotas .cuota-pct { width: 100%; }
    #modal-cuotas .table td, #modal-cuotas .table th { padding-left: 8px; padding-right: 8px; }
    /* Cabeceras de modales estilizadas */
    .modal-dialog { overflow: hidden; }
    .modal-header { background: var(--text); border-bottom: none; padding: 14px 18px; }
    .modal-header .modal-title { color: #fff; }
    .modal-header .modal-close { color: rgba(255,255,255,.7); }
    .modal-header .modal-close:hover { background: rgba(255,255,255,.15); color: #fff; }
    /* Select buscable deshabilitado */
    .ss-disabled .ms-input { background: var(--bg-surface-secondary); cursor: not-allowed; opacity: .7; }
    .ss-disabled .ms-search { cursor: not-allowed; }
    /* Toasts por encima de los modales (modal z-index 1200) */
    .toast-host { z-index: 2000; }
    @media (max-width: 900px) { .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

<form id="order-form" method="POST" enctype="multipart/form-data"
      action="{{ $isEdit ? route('orders.update', $order->id) : route('orders.store') }}">
    @csrf
    <div id="keep-files"></div>{{-- archivos existentes a conservar (modo edición) --}}

    @if (!empty($obsInfo))
        <div class="obs-note">
            <div class="obs-note-head">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Orden observada</span>
                <span class="obs-note-meta">{{ $obsInfo['usuario'] }} ({{ $obsInfo['rol'] }}) · {{ $obsInfo['fecha'] }}</span>
            </div>
            <div class="obs-note-body">
                @if ($obsInfo['tipo'])
                    <div><span class="obs-note-l">Tipo de observación:</span> {{ $obsInfo['tipo'] }}</div>
                @endif
                <div><span class="obs-note-l">Detalle de observación:</span> {{ $obsInfo['detalle'] }}</div>
            </div>
        </div>
    @endif

    @if ($restricted ?? false)
        <div class="lock-note">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" style="flex-shrink:0;margin-top:1px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <div>Esta orden ya pasó la etapa de aprobación financiera. Puedes corregir los datos generales y los documentos, pero el <strong>Proveedor</strong>, el <strong>Detalle de la orden</strong> y la <strong>Condición de pago</strong> quedan bloqueados (sus montos y cuotas no pueden modificarse).</div>
        </div>
    @endif

    {{-- ── CARD 1: Datos generales ── --}}
    <div class="card sec-card">
        <div class="card-header">
            <div>
                <div class="card-title"><span class="sec-num">1</span>Datos generales
                <small class="card-subtitle">(Empresa, tipo, moneda y clasificación)</small>
                </div>
                
            </div>
        </div>
        <div class="card-body">
            <div class="grid-4">
                <div class="form-group">
                    <label class="form-label">Empresa <span class="required">*</span></label>
                    <select name="company_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($companies as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
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
                    <select name="category_id" id="category_id" class="form-control searchable" required @disabled(!$isEdit)>
                        <option value="">{{ $isEdit ? 'Seleccione...' : 'Seleccione tipo primero' }}</option>
                        @if ($isEdit)
                            @foreach ($pf('categoryOptions', collect()) as $c)
                                <option value="{{ $c->id }}" @selected($pf('category_id') == $c->id)>{{ $c->description }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Moneda <span class="required">*</span></label>
                    <select name="currency" id="currency" class="form-control searchable" required>
                        @foreach ($monedas as $m)
                            <option value="{{ $m->value }}">{{ $m->description }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if (!$isEdit && auth()->user()->user_type === 'GA')
                {{-- El GA elige la Gestión y con ella el AA responsable de la orden. --}}
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tipo de Gestión <span class="required">*</span></label>
                        <select name="type_id" id="type_id" class="form-control searchable" required>
                            <option value="">Seleccione una opción</option>
                            @foreach (($gestiones ?? collect()) as $g)
                                <option value="{{ $g['id'] }}" data-responsible="{{ $g['responsible'] }}">{{ $g['descripcion'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Responsable asignado</label>
                        <input type="text" id="resp-name" class="form-control ro-field" readonly placeholder="Según la gestión elegida">
                    </div>
                </div>
            @endif

            <div class="grid-4">
                <div class="form-group" style="grid-column:span 1">
                    <label class="form-label">Título / Asunto <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="250" required placeholder="Ej. Adquisición de servidores">
                </div>
                <div class="form-group">
                    <label class="form-label">Sede <span class="required">*</span></label>
                    <select name="sede_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($sedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Área <span class="required">*</span></label>
                    <select name="area_id" id="area_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($areas as $id => $desc)
                            <option value="{{ $id }}">{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Centros de Costo <span class="required">*</span></label>
                    <div class="multi-select" id="cc-ms">
                        <div class="ms-input" id="cc-input">
                            <div class="ms-chips" id="cc-chips"></div>
                            <input type="text" class="ms-search" id="cc-search" placeholder="Seleccione área primero" disabled autocomplete="off">
                            <svg class="ms-chev" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6l4 4 4-4"/></svg>
                        </div>
                        <div class="ms-menu" id="cc-menu" hidden></div>
                        <div id="cc-hidden"></div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Justificación <span class="required">*</span></label>
                <textarea name="justification" id="justification" class="form-control" rows="2" maxlength="500" required
                          placeholder="Describa el motivo y sustento del requerimiento..."></textarea>
                <div style="text-align:right;font-size:12px;color:var(--text-muted);margin-top:2px"><span id="just-count">0</span>/500</div>
            </div>
        </div>
    </div>

    {{-- ── CARD 2: Proveedor ── --}}
    <div class="card sec-card @if ($restricted ?? false) is-locked @endif">
        <div class="card-header">
            <div>
                <div class="card-title"><span class="sec-num">2</span>Proveedor
                <small class="card-subtitle">(RUC, cuenta destino y términos comerciales)</small>
                @if ($restricted ?? false)<span class="lock-badge"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Bloqueado</span>@endif
            </div>

            </div>
            <button type="button" id="btn-open-register" class="btn btn-outline btn-sm" style="display:none">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
                Registrar nuevo proveedor
            </button>
        </div>
        <div class="card-body">
            <input type="hidden" name="supplier_id" id="supplier_id">
            <input type="hidden" name="supplier_account_id" id="supplier_account_id">

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">RUC</label>
                    <div style="display:flex;gap:8px">
                        <input type="text" id="ruc_search" class="form-control" placeholder="11 dígitos" inputmode="numeric" maxlength="11" autocomplete="off">
                        <button type="button" id="btn-buscar-ruc" class="btn btn-primary" title="Buscar">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg>
                        </button>
                    </div>
                    <div id="ruc-msg" style="font-size:12px;margin-top:4px"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Razón social</label>
                    <input type="text" name="supplier_name" id="supplier_name" class="form-control ro-field" readonly placeholder="Se autocompleta al buscar">
                </div>
                <div class="form-group">
                    <label class="form-label">Domicilio fiscal</label>
                    <input type="text" id="supplier_address" class="form-control ro-field" readonly placeholder="Se autocompleta al buscar">
                </div>
            </div>

            <div class="grid-4" id="supplier-fields" style="display:none">
                <div class="form-group"><label class="form-label">Distrito</label><input type="text" id="supplier_district" class="form-control ro-field" readonly></div>
                <div class="form-group"><label class="form-label">Contacto</label><input type="text" id="supplier_contact" class="form-control ro-field" readonly></div>
                <div class="form-group"><label class="form-label">Celular</label><input type="text" id="supplier_phone" class="form-control ro-field" readonly></div>
                <div class="form-group"><label class="form-label">Correo</label><input type="text" id="supplier_email" class="form-control ro-field" readonly></div>
            </div>

            <label class="form-label">Cuentas bancarias</label>
            <div id="supplier-accounts">
                <div style="padding:16px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">
                    Busca un proveedor para ver sus cuentas bancarias.
                </div>
            </div>
        </div>
    </div>

    {{-- ── CARD 3: Detalle de la orden ── --}}
    <div class="card sec-card @if ($restricted ?? false) is-locked @endif">
        <div class="card-header">
            <div>
                <div class="card-title"><span class="sec-num">3</span>Detalle de la orden
                <small class="card-subtitle">(Productos / servicios y configuración tributaria)</small>
                @if ($restricted ?? false)<span class="lock-badge"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Bloqueado</span>@endif
            </div>

            </div>
        </div>
        <div class="card-body">
            <div class="grid-3" style="margin-bottom:8px">
                <div class="form-group">
                    <label class="switch">
                        <input type="checkbox" name="grabable" id="grabable" value="1" checked>
                        <span class="track"></span>
                        <span class="switch-label">Calcular IGV (18%)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="switch">
                        <input type="checkbox" name="apply_discount" id="apply_discount" value="1">
                        <span class="track"></span>
                        <span class="switch-label">Aplicar descuento</span>
                    </label>
                </div>
                <div class="form-group">
                    <select name="discount_type_id" id="discount_type_id" class="form-control searchable" disabled>
                        <option value="">Selecciona % de descuento</option>
                        @forelse ($discountOpts as $id => $desc)
                            <option value="{{ $id }}">{{ $desc }}</option>
                        @empty
                            <option value="5">5% — Pronto pago</option>
                            <option value="10">10% — Convenio</option>
                            <option value="15">15% — Liquidación</option>
                        @endforelse
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table items-table">
                    <thead>
                        <tr>
                            <th style="width:45%">Descripción del producto / servicio</th>
                            <th style="width:12%">Cant.</th>
                            <th style="width:18%">P. Unitario</th>
                            <th style="width:18%">Subtotal</th>
                            <th style="width:7%"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
            </div>
            <button type="button" id="add-item" class="btn btn-outline btn-sm" style="margin-top:8px">+ Agregar ítem</button>

            <div class="form-group" style="margin-top:16px">
                <label class="form-label">Observaciones</label>
                <textarea name="observation" class="form-control" rows="2"></textarea>
            </div>

            {{-- Totales (en vivo) --}}
            <div class="totals-box">
                <div class="tot-item"><span class="tot-label">Subtotal</span><span class="tot-val" id="t-subtotal">S/ 0.00</span></div>
                <div class="tot-item" id="t-igv-box"><span class="tot-label">IGV (18%)</span><span class="tot-val" id="t-igv">S/ 0.00</span></div>
                <div class="tot-item" id="t-disc-box" style="display:none"><span class="tot-label">Descuento</span><span class="tot-val" id="t-disc" style="color:var(--red)">S/ 0.00</span></div>
                <div class="tot-item tot-main"><span class="tot-label">Total</span><span class="tot-val" id="t-total">S/ 0.00</span></div>
            </div>
        </div>
    </div>

    {{-- ── CARD 4: Condición de pago ── --}}
    <div class="card sec-card @if ($restricted ?? false) is-locked @endif">
        <div class="card-header">
            <div>
                <div class="card-title"><span class="sec-num">4</span>Condición de pago
                <small class="card-subtitle">(Forma de pago, condición y programación)</small>
                @if ($restricted ?? false)<span class="lock-badge"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Bloqueado</span>@endif
            </div>

            </div>
        </div>
        <div class="card-body">
            <div class="grid-4">
                <div class="form-group">
                    <label class="form-label">Programación <span class="required">*</span>
                        @if ($scheduleLocked ?? false)<small style="color:var(--text-muted);font-weight:500">· no editable (orden ya aprobada)</small>@endif
                    </label>
                    <select name="payment_schedule_id" id="payment_schedule_id" class="form-control searchable" required @disabled($scheduleLocked ?? false)>
                        <option value="">Seleccione...</option>
                        @foreach ($schedules as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de pago <span class="required">*</span></label>
                    <select name="payment_id" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($paymentOpts as $id => $desc)
                            <option value="{{ $id }}">{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Condición <span class="required">*</span></label>
                    <select name="condition_payment" id="condition_payment" class="form-control searchable" required>
                        <option value="">Seleccione...</option>
                        @foreach ($conditionOpts as $id => $desc)
                            <option value="{{ $id }}">{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de vencimiento <span class="required" id="venc-req" style="display:none">*</span></label>
                    <input type="date" name="expiration_date" class="form-control" @unless ($isEdit) min="{{ now()->toDateString() }}" @endunless>
                </div>
            </div>

            {{-- Cronograma de cuotas (solo si condición = fraccionado) --}}
            <div id="cuotas-section" style="display:none;margin-top:6px">
                <input type="hidden" name="plan_cuotas" id="plan_cuotas">
                <input type="hidden" name="quotas" id="quotas" value="1">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                    <strong style="font-size:13px">Plan de cuotas <span style="font-weight:400;color:var(--text-muted)">· debe sumar el total de la orden</span></strong>
                    <button type="button" id="btn-config-cuotas" class="btn btn-outline btn-sm">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6h12M5 1v3M11 1v3"/></svg>
                        Configurar cuotas
                    </button>
                </div>
                <div id="cuotas-display">
                    <div style="padding:16px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">
                        Sin configurar aún. Haz clic en "Configurar cuotas".
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CARD 5: Documentos ── --}}
    <div class="card sec-card">
        <div class="card-header">
            <div>
                <div class="card-title"><span class="sec-num">5</span>Documentos

                <small class="card-subtitle">(Comprobante de pago y documentos anexos)</small>
            </div>
                
            </div>
        </div>
        <div class="card-body">
            {{-- Comprobantes --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <strong style="font-size:13px">Comprobante de pago</strong>
                <button type="button" id="btn-add-comprobante" class="btn btn-outline btn-sm">+ Agregar comprobante</button>
            </div>
            <div class="table-responsive" style="margin-bottom:20px">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>Serie</th><th>N° Documento</th><th>Monto</th><th>Emisión</th><th>Comentario</th><th>Archivo</th><th></th></tr></thead>
                    <tbody id="comprobantes-body">
                        <tr id="comprobantes-empty"><td colspan="7" style="padding:14px;text-align:center;color:var(--text-muted);font-style:italic">Sin comprobantes.</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="comprobantes-store"></div>

            {{-- Documentos anexos --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <strong style="font-size:13px">Documentos anexos <span style="font-weight:400;color:var(--text-muted)">· PDF, JPG, PNG · máx. 10 MB c/u</span></strong>
                <button type="button" id="btn-add-documento" class="btn btn-outline btn-sm">+ Agregar D.Anexos</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>Comentario</th><th>Archivo</th><th></th></tr></thead>
                    <tbody id="documentos-body">
                        <tr id="documentos-empty"><td colspan="4" style="padding:14px;text-align:center;color:var(--text-muted);font-style:italic">Sin documentos.</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="documentos-store"></div>
        </div>
    </div>

    <div style="height:8px"></div>
</form>

{{-- ── MODAL: Registrar proveedor ── --}}
<div class="modal-backdrop" id="modal-supplier" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Registrar nuevo proveedor</h3>
            <button type="button" class="modal-close" data-close="modal-supplier">×</button>
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

{{-- ── MODAL: Agregar comprobante ── --}}
<div class="modal-backdrop" id="modal-comprobante" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Agregar comprobante de pago</h3>
            <button type="button" class="modal-close" data-close="modal-comprobante">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Tipo de comprobante <span class="required">*</span></label>
                <select id="cb_type" class="form-control">
                    <option value="">Seleccione...</option>
                    @foreach ($voucherTypes as $val => $desc)
                        <option value="{{ $val }}">{{ $desc }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Solo para Recibo x Honorario: define qué anexos son obligatorios --}}
            <div class="form-group" id="cb_retencion_wrap" style="display:none">
                <label class="switch">
                    <input type="checkbox" id="cb_retencion" value="1">
                    <span class="track"></span>
                    <span class="switch-label">Tiene retención</span>
                </label>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                    Sin retención exige además el anexo <strong>Suspensión 4ta/5ta</strong>. El <strong>Informe Laboral</strong> es obligatorio en ambos casos.
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Serie <span class="required">*</span></label><input id="cb_serie" class="form-control" maxlength="50" placeholder="F001"></div>
                <div class="form-group"><label class="form-label">N.° de documento <span class="required">*</span></label><input id="cb_docnum" class="form-control" placeholder="00012345"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Monto <span class="required">*</span></label><input id="cb_amount" type="number" step="0.01" min="0" class="form-control"></div>
                <div class="form-group"><label class="form-label">Fecha de emisión <span class="required">*</span></label><input id="cb_date" type="date" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Comentario</label><input id="cb_coment" class="form-control" maxlength="500" placeholder="Opcional"></div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Archivo (solo PDF) <span class="required">*</span></label>
                <label class="dropzone dz-compact" id="cb_dz" for="cb_file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                    <div class="hint">Suelta el archivo o haz clic para buscar</div>
                    <div class="sub">Solo PDF · máx. 3 MB</div>
                    <input type="file" id="cb_file" accept="application/pdf" style="display:none">
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-comprobante">Cancelar</button>
            <button type="button" id="cb-save" class="btn btn-primary">Guardar comprobante</button>
        </div>
    </div>
</div>

{{-- ── MODAL: Agregar documento anexo ── --}}
<div class="modal-backdrop" id="modal-documento" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Agregar documento anexo</h3>
            <button type="button" class="modal-close" data-close="modal-documento">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Tipo de documento <span class="required">*</span></label>
                <select id="dn_type" class="form-control">
                    <option value="">Seleccione...</option>
                    @foreach ($attachTypes as $val => $desc)
                        <option value="{{ $val }}">{{ $desc }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label class="form-label">Comentario</label><input id="dn_coment" class="form-control" maxlength="500" placeholder="Opcional"></div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Archivo <span class="required">*</span></label>
                <label class="dropzone dz-compact" id="dn_dz" for="dn_file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 21V9M6 13l6-6 6 6"/><path d="M3 3h18"/></svg>
                    <div class="hint">Suelta el archivo o haz clic para buscar</div>
                    <div class="sub">PDF, JPG, PNG · máx. 10 MB</div>
                    <input type="file" id="dn_file" accept="application/pdf,image/*" style="display:none">
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-documento">Cancelar</button>
            <button type="button" id="dn-save" class="btn btn-primary">Guardar documento</button>
        </div>
    </div>
</div>

{{-- ── MODAL: Configurar cuotas ── --}}
<div class="modal-backdrop" id="modal-cuotas" style="display:none">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Plan de cuotas</h3>
            <button type="button" class="modal-close" data-close="modal-cuotas">×</button>
        </div>
        <div class="modal-body">
            <div class="banner banner-info" style="margin-bottom:14px">
                <svg class="banner-icon" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M8 5v.01M8 7v4"/></svg>
                <div class="banner-body">El total de las cuotas debe coincidir con el <strong>monto total de la orden</strong>: <strong id="cuotas-order-total">S/ 0.00</strong></div>
            </div>

            {{-- Configuración rápida: genera el plan automáticamente --}}
            <div style="display:flex;flex-wrap:wrap;align-items:end;gap:12px 16px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-color)">
                <div class="form-group" style="margin-bottom:0;width:100px">
                    <label class="form-label">N° de cuotas</label>
                    <input type="number" id="cfg-num" class="form-control" min="1" max="60" value="3">
                </div>
                <div class="form-group" style="margin-bottom:0;width:170px">
                    <label class="form-label">Frecuencia</label>
                    <select id="cfg-freq" class="form-control">
                        <option value="semanal">Semanal</option>
                        <option value="quincenal" selected>Quincenal</option>
                        <option value="mensual">Mensual</option>
                        <option value="trimestral">Trimestral</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;margin-left:auto">
                    <label class="form-label">Distribución</label>
                    <div style="display:flex;align-items:center;gap:8px;height:38px">
                        <span style="font-size:13px;color:var(--text-secondary)">Montos</span>
                        <label class="switch" style="margin:0"><input type="checkbox" id="cfg-dist"><span class="track"></span></label>
                        <span style="font-size:13px;color:var(--text-secondary)">Porcentajes</span>
                    </div>
                </div>
                <button type="button" id="cfg-apply" class="btn btn-primary" style="height:38px">Aplicar</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th style="width:14%">Cuota</th><th style="width:30%">Fecha de vencimiento</th><th class="pct-col" style="display:none;width:16%">%</th><th style="width:24%">Monto</th><th style="width:8%"></th></tr></thead>
                    <tbody id="cuotas-rows"></tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:24px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border-color)">
                <div class="tot-item"><span class="tot-label">Suma de cuotas</span><span class="tot-val" id="cuotas-sum">S/ 0.00</span></div>
                <div class="tot-item"><span class="tot-label">Diferencia</span><span class="tot-val" id="cuotas-diff">S/ 0.00</span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close="modal-cuotas">Cancelar</button>
            <button type="button" id="cuotas-save" class="btn btn-primary">Guardar plan</button>
        </div>
    </div>
</div>

@if ($isEdit && ($acts['observe'] ?? false))
{{-- ── MODAL: Observar (edición) ── --}}
<div class="modal-backdrop" id="modal-observe" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header"><h3 class="modal-title">Registrar observación</h3><button type="button" class="modal-close" data-close="modal-observe">×</button></div>
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
@endif

@if ($isEdit && ($acts['reject'] ?? false))
{{-- ── MODAL: Rechazar (edición) ── --}}
<div class="modal-backdrop" id="modal-reject" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header"><h3 class="modal-title">Rechazar orden</h3><button type="button" class="modal-close" data-close="modal-reject">×</button></div>
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
@endif

@endsection

@push('scripts')
<script>
window.PREFILL = @json($prefill ?? null);
window.RESTRICTED = @json($restricted ?? false);
window.DOC_RULES = @json($docRules ?? null);   // {recibo, informe, suspension, factura, boleta, cotizacion, guia, proyectosArea, ...}
window.FRACCIONADO_IDS = @json($fraccionadoIds).map(String);
</script>
<script>
// ── Toast del tema Gentelella (reemplaza alert) ──
window.showToast = function (message, variant = 'success', timeout = 3200) {
    let host = document.querySelector('.toast-host');
    if (!host) { host = document.createElement('div'); host.className = 'toast-host'; document.body.appendChild(host); }
    const t = document.createElement('div');
    t.className = 'toast toast-' + variant;
    t.textContent = message;
    host.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    const close = () => { t.classList.remove('show'); setTimeout(() => t.remove(), 200); };
    const timer = setTimeout(close, timeout);
    t.addEventListener('click', () => { clearTimeout(timer); close(); });
};
</script>

<script>
(function () {
    const body = document.getElementById('items-body');
    const addBtn = document.getElementById('add-item');
    const igvChk = document.getElementById('grabable');
    const discChk = document.getElementById('apply_discount');
    const discSel = document.getElementById('discount_type_id');
    let idx = 0;

    function curSymbol() {
        return document.getElementById('currency').value === 'USD' ? '$ ' : 'S/ ';
    }
    function fmt(v) {
        return curSymbol() + (parseFloat(v) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function addItem(data) {
        const i = idx++;
        const esc = (s) => String(s ?? '').replace(/"/g, '&quot;');
        const desc  = data ? esc(data.description) : '';
        const qty   = data ? (data.quantity ?? 1) : 1;
        const price = data ? (data.unit_price ?? 0) : 0;
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td><input name="items[${i}][description]" placeholder="Descripción..." required value="${desc}"></td>
            <td><input name="items[${i}][quantity]" class="item-qty" type="number" min="1" value="${qty}"></td>
            <td><input name="items[${i}][unit_price]" class="item-price" type="number" min="0" step="0.01" value="${price}"></td>
            <td class="item-subtotal cell-strong">0.00</td>
            <td style="text-align:center"><button type="button" class="btn-remove" title="Quitar">×</button></td>`;
        body.appendChild(tr);
        recalc();
    }

    // Precarga (edición)
    window.__setItems = (arr) => { body.innerHTML = ''; idx = 0; (arr && arr.length ? arr : [null]).forEach(addItem); };

    function recalc() {
        let subtotal = 0;
        body.querySelectorAll('.item-row').forEach(row => {
            const q = parseFloat(row.querySelector('.item-qty').value) || 0;
            const p = parseFloat(row.querySelector('.item-price').value) || 0;
            const sub = q * p;
            row.querySelector('.item-subtotal').textContent = sub.toFixed(2);
            subtotal += sub;
        });

        const igv = igvChk.checked ? Math.round(subtotal * 18) / 100 : 0;
        let total = Math.round((subtotal + igv) * 100) / 100;

        let disc = 0;
        if (discChk.checked && discSel.value) {
            const pct = parseFloat((discSel.options[discSel.selectedIndex].text.match(/(\d+(\.\d+)?)\s*%/) || [])[1] || discSel.value) || 0;
            disc = Math.round(total * pct) / 100;
            total = Math.round((total - disc) * 100) / 100;
        }

        document.getElementById('t-subtotal').textContent = fmt(subtotal);
        document.getElementById('t-igv').textContent = fmt(igv);
        document.getElementById('t-igv-box').style.display = igvChk.checked ? '' : 'none';
        document.getElementById('t-disc').textContent = '− ' + fmt(disc);
        document.getElementById('t-disc-box').style.display = (discChk.checked && disc > 0) ? '' : 'none';
        document.getElementById('t-total').textContent = fmt(total);
        window.__orderTotal = total;   // expuesto para el cronograma de cuotas
    }

    // Eventos
    addBtn.addEventListener('click', addItem);
    body.addEventListener('input', e => {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) recalc();
    });
    body.addEventListener('click', e => {
        if (e.target.classList.contains('btn-remove')) {
            const row  = e.target.closest('.item-row');
            const rows = body.querySelectorAll('.item-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                // Última fila: limpiarla en vez de bloquear
                row.querySelector('input[name*="[description]"]').value = '';
                row.querySelector('.item-qty').value = '1';
                row.querySelector('.item-price').value = '0';
            }
            recalc();
        }
    });
    igvChk.addEventListener('change', recalc);
    discChk.addEventListener('change', () => {
        const off = !discChk.checked;
        discSel.disabled = off;
        if (off) discSel.value = '';
        if (discSel.__ss) discSel.__ss.setDisabled(off);
        recalc();
    });
    discSel.addEventListener('change', recalc);
    document.getElementById('currency').addEventListener('change', recalc);

    // Ítem inicial
    addItem();
})();
</script>

<script>
// ── Multi-select con chips para Centros de Costo ──
(function () {
    const wrap   = document.getElementById('cc-ms');
    const input  = document.getElementById('cc-input');
    const chips  = document.getElementById('cc-chips');
    const search = document.getElementById('cc-search');
    const menu   = document.getElementById('cc-menu');
    const hidden = document.getElementById('cc-hidden');
    const areaSel = document.getElementById('area_id');
    const BASE = "{{ url('orders/cost-centers') }}";

    let options = [];                 // [{id, label}]
    const selected = new Map();       // id(string) -> label

    function renderChips() {
        chips.innerHTML = '';
        hidden.innerHTML = '';
        selected.forEach((label, id) => {
            const chip = document.createElement('span');
            chip.className = 'ms-chip';
            chip.innerHTML = `${label}<button type="button" data-id="${id}" aria-label="Quitar">×</button>`;
            chips.appendChild(chip);

            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'cc_ids[]'; h.value = id;
            hidden.appendChild(h);
        });
    }

    function renderMenu() {
        const q = search.value.toLowerCase();
        const avail = options.filter(o => !selected.has(String(o.id)) && o.label.toLowerCase().includes(q));
        menu.innerHTML = avail.length
            ? avail.map(o => `<button type="button" class="ms-option" data-id="${o.id}">${o.label}</button>`).join('')
            : `<div class="ms-empty">Sin opciones</div>`;
    }

    const open  = () => { if (search.disabled) return; wrap.classList.add('open'); menu.hidden = false; renderMenu(); };
    const close = () => { wrap.classList.remove('open'); menu.hidden = true; };

    input.addEventListener('click', () => { if (!search.disabled) { search.focus(); open(); } });
    search.addEventListener('focus', open);
    search.addEventListener('input', () => { open(); renderMenu(); });

    // mousedown para seleccionar antes del blur
    menu.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('.ms-option');
        if (!opt) return;
        e.preventDefault();
        const o = options.find(x => String(x.id) === opt.dataset.id);
        if (o) { selected.set(String(o.id), o.label); renderChips(); search.value = ''; renderMenu(); search.focus(); }
    });

    chips.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-id]');
        if (!btn) return;
        selected.delete(btn.dataset.id);
        renderChips(); renderMenu();
    });

    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) close(); });

    // ── Categoría dependiente de Tipo de Orden ──
    const formatSel = document.getElementById('format_id');
    const catSel    = document.getElementById('category_id');
    const CAT_BASE  = "{{ url('orders/categories') }}";
    formatSel.addEventListener('change', async () => {
        catSel.disabled = true;
        catSel.innerHTML = '<option value="">Cargando...</option>';
        const f = formatSel.value;
        if (!f) { catSel.innerHTML = '<option value="">Seleccione tipo primero</option>'; return; }
        try {
            const res  = await fetch(`${CAT_BASE}/${f}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const opts = json.data || [];
            catSel.innerHTML = '<option value="">Seleccione...</option>'
                + opts.map(c => `<option value="${c.id}">${c.description}</option>`).join('');
            catSel.disabled = false;
            if (catSel.__ss) catSel.__ss.setDisabled(false);   // refresca el widget buscable
        } catch (e) {
            catSel.innerHTML = '<option value="">Error al cargar</option>';
        }
    });

    // (cost centers abajo)
    areaSel.addEventListener('change', async () => {
        selected.clear(); renderChips();
        options = []; menu.innerHTML = '';
        const areaId = areaSel.value;
        if (!areaId) { search.disabled = true; search.placeholder = 'Seleccione área primero'; return; }

        search.disabled = true; search.placeholder = 'Cargando...';
        try {
            const res  = await fetch(`${BASE}/${areaId}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            options = (json.data || []).map(c => ({ id: c.id, label: c.description }));
            search.disabled = false;
            search.placeholder = options.length ? 'Buscar centro de costo...' : 'Sin centros de costo';
        } catch (e) {
            search.placeholder = 'Error al cargar';
        }
        renderMenu();
    });

    // Precarga (edición): opciones + seleccionados, sin ir al servidor
    window.__setCostCenters = (opts, selectedIds) => {
        options = (opts || []).map(c => ({ id: c.id, label: c.description ?? c.label }));
        selected.clear();
        (selectedIds || []).forEach(id => {
            const o = options.find(x => String(x.id) === String(id));
            if (o) selected.set(String(o.id), o.label);
        });
        search.disabled = false;
        search.placeholder = options.length ? 'Buscar centro de costo...' : 'Sin centros de costo';
        renderChips();
    };
})();
</script>

<script>
// ── Helpers de modales (Gentelella) ──
function openModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
    requestAnimationFrame(() => el.classList.add('show'));
    document.body.classList.add('modal-open');
}
function closeModal(id) {
    const el = document.getElementById(id);
    el.classList.remove('show');
    setTimeout(() => { el.style.display = 'none'; }, 180);
    document.body.classList.remove('modal-open');
}
document.addEventListener('click', (e) => {
    const c = e.target.closest('[data-close]');
    if (c) closeModal(c.dataset.close);
    // clic en el backdrop (no en el diálogo)
    if (e.target.classList.contains('modal-backdrop')) closeModal(e.target.id);
});
</script>

<script>
// ── Proveedor: búsqueda RUC, cuentas y registro ──
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const URL_SEARCH = "{{ route('orders.supplier.search') }}";
    const URL_STORE  = "{{ route('orders.supplier.store') }}";
    const BANK_OPTIONS = `@foreach($banks as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach`;

    const $ = (id) => document.getElementById(id);
    const supId = $('supplier_id'), supAcc = $('supplier_account_id');
    const fields = $('supplier-fields'), accBox = $('supplier-accounts');
    const rucInput = $('ruc_search'), rucMsg = $('ruc-msg'), btnRegister = $('btn-open-register');

    function clearSupplier() {
        supId.value = ''; supAcc.value = '';
        $('supplier_name').value = '';
        ['supplier_address','supplier_district','supplier_contact','supplier_phone','supplier_email'].forEach(k => $(k).value = '');
        fields.style.display = 'none';
        accBox.innerHTML = `<div style="padding:16px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Busca un proveedor para ver sus cuentas bancarias.</div>`;
    }

    function fillSupplier(s) {
        supId.value = s.id;
        $('supplier_name').value = s.name || '';
        $('supplier_address').value = s.address || '';
        $('supplier_district').value = s.district || '';
        $('supplier_contact').value = s.contact || '';
        $('supplier_phone').value = s.phone || '';
        $('supplier_email').value = s.email || '';
        fields.style.display = '';
        btnRegister.style.display = 'none';
    }

    function renderAccounts(accounts) {
        if (!accounts || !accounts.length) {
            accBox.innerHTML = `<div style="padding:16px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Este proveedor no tiene cuentas registradas.</div>`;
            return;
        }
        let rows = accounts.map(a => `
            <tr>
                <td style="text-align:center"><input type="radio" name="acc_pick" value="${a.id}" ${a.is_primary ? 'checked' : ''}></td>
                <td class="cell-strong">${a.bank}</td>
                <td><span class="status status-blue">${a.currency}</span></td>
                <td>${a.account_number}</td>
                <td>${a.cci || '—'}</td>
                <td style="text-align:center">${a.is_primary ? '<span class="status status-green">Sí</span>' : '—'}</td>
            </tr>`).join('');
        accBox.innerHTML = `
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th></th><th>Banco</th><th>Moneda</th><th>N° cuenta</th><th>CCI</th><th>Principal</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        // Seleccionar la principal (o la primera) por defecto
        const primary = accounts.find(a => a.is_primary) || accounts[0];
        supAcc.value = primary.id;
        accBox.querySelectorAll('input[name="acc_pick"]').forEach(r =>
            r.addEventListener('change', () => { supAcc.value = r.value; }));
    }

    async function buscarRuc() {
        const ruc = rucInput.value.trim();
        if (!/^\d{11}$/.test(ruc)) { rucMsg.textContent = 'El RUC debe tener exactamente 11 dígitos.'; rucMsg.style.color = 'var(--red)'; return; }
        rucMsg.textContent = 'Buscando...'; rucMsg.style.color = 'var(--text-muted)';
        try {
            const res = await fetch(`${URL_SEARCH}?ruc=${encodeURIComponent(ruc)}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (json.status === 1) {
                fillSupplier(json.data.supplier);
                renderAccounts(json.data.accounts);
                rucMsg.textContent = '✓ Proveedor encontrado'; rucMsg.style.color = 'var(--green)';
            } else if (json.data && json.data.exists) {
                // Existe pero está INACTIVO → no se puede usar ni registrar; hay que activarlo en Proveedores
                clearSupplier();
                rucMsg.textContent = json.description; rucMsg.style.color = 'var(--red)';
                btnRegister.style.display = 'none';
            } else {
                clearSupplier();
                rucMsg.textContent = 'No encontrado. Puedes registrarlo →'; rucMsg.style.color = 'var(--red)';
                btnRegister.style.display = '';
                $('ns_ruc').value = ruc;
            }
        } catch (e) {
            rucMsg.textContent = 'Error al buscar.'; rucMsg.style.color = 'var(--red)';
        }
    }

    $('btn-buscar-ruc').addEventListener('click', buscarRuc);
    rucInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); buscarRuc(); } });
    // RUC: solo dígitos (máx 11) tanto en búsqueda como en registro
    ['ruc_search', 'ns_ruc'].forEach((id) => {
        const el = $(id);
        if (el) el.addEventListener('input', () => { el.value = el.value.replace(/\D/g, '').slice(0, 11); });
    });
    // Si editan el RUC con un proveedor ya cargado, se descarta (evita guardar uno que no corresponde)
    rucInput.addEventListener('input', () => {
        if (supId.value) {
            clearSupplier();
            btnRegister.style.display = 'none';
            rucMsg.textContent = 'Busca el RUC para cargar el proveedor.';
            rucMsg.style.color = 'var(--text-muted)';
        }
    });

    // ── Modal registrar proveedor ──
    function nsAccountRow() {
        const div = document.createElement('div');
        div.className = 'ns-acc-row';
        div.style = 'display:grid;grid-template-columns:1fr .8fr 1.7fr 1.7fr auto;gap:8px;align-items:end;margin-bottom:8px';
        div.innerHTML = `
            <div><label class="form-label">Banco</label><select class="ns-bank form-control">${BANK_OPTIONS}</select></div>
            <div><label class="form-label">Moneda</label><select class="ns-cur form-control"><option value="PEN">Soles (PEN)</option><option value="USD">Dólares (USD)</option></select></div>
            <div><label class="form-label">N° cuenta</label><input class="ns-num form-control"></div>
            <div><label class="form-label">CCI</label><input class="ns-cci form-control"></div>
            <button type="button" class="btn-remove ns-rm" title="Quitar" style="height:34px">×</button>`;
        return div;
    }

    btnRegister.addEventListener('click', () => {
        const acc = $('ns-accounts');
        if (!acc.children.length) acc.appendChild(nsAccountRow());
        openModal('modal-supplier');
    });
    $('ns-add-account').addEventListener('click', () => $('ns-accounts').appendChild(nsAccountRow()));
    $('ns-accounts').addEventListener('click', (e) => {
        if (e.target.classList.contains('ns-rm')) {
            const rows = $('ns-accounts').querySelectorAll('.ns-acc-row');
            if (rows.length > 1) e.target.closest('.ns-acc-row').remove();
        }
    });

    $('ns-save').addEventListener('click', async () => {
        const payload = {
            ruc:       $('ns_ruc').value.trim(),
            name:      $('ns_name').value.trim(),
            address:   $('ns_address').value.trim(),
            provincia: $('ns_provincia').value.trim(),
            district:  $('ns_district').value.trim(),
            contact:   $('ns_contact').value.trim(),
            phone:     $('ns_phone').value.trim(),
            email:     $('ns_email').value.trim(),
            accounts:  [...$('ns-accounts').querySelectorAll('.ns-acc-row')].map(r => ({
                bank:           r.querySelector('.ns-bank').value,
                currency:       r.querySelector('.ns-cur').value,
                account_number: r.querySelector('.ns-num').value.trim(),
                cci:            r.querySelector('.ns-cci').value.trim(),
            })).filter(a => a.account_number),
        };
        if (!payload.ruc || !payload.name) { showToast('RUC y Razón social son obligatorios.', 'warning'); return; }
        if (!/^\d{11}$/.test(payload.ruc)) { showToast('El RUC debe tener exactamente 11 dígitos.', 'warning'); return; }

        const btn = $('ns-save'); btn.disabled = true; btn.textContent = 'Guardando...';
        try {
            const res = await fetch(URL_STORE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            });
            const json = await res.json();
            if (json.status === 1) {
                fillSupplier(json.data.supplier);
                renderAccounts(json.data.accounts);
                rucInput.value = json.data.supplier.ruc;
                rucMsg.textContent = '✓ Proveedor registrado'; rucMsg.style.color = 'var(--green)';
                closeModal('modal-supplier');
                showToast('Proveedor registrado', 'success');
            } else {
                showToast(json.description || 'No se pudo registrar.', 'error');
            }
        } catch (e) {
            showToast('Error al registrar el proveedor.', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar proveedor';
        }
    });

    // Precarga (edición): rellena el proveedor seleccionado y sus cuentas
    window.__setSupplier = (sup, accounts, accountId) => {
        if (!sup) return;
        fillSupplier(sup);
        renderAccounts(accounts || []);
        rucInput.value = sup.ruc || '';
        if (accountId) {
            supAcc.value = accountId;
            const radio = accBox.querySelector(`input[name="acc_pick"][value="${accountId}"]`);
            if (radio) radio.checked = true;
        }
        rucMsg.textContent = '✓ Proveedor cargado'; rucMsg.style.color = 'var(--green)';
    };
})();
</script>

<script>
// ── Comprobantes y Documentos anexos (con subida de archivos) ──
(function () {
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    // Dropzone del tema: arrastrar/soltar + mostrar nombre del archivo
    function initDropzone(dzId, defaultHint) {
        const dz = document.getElementById(dzId);
        if (!dz) return;
        const hint = dz.querySelector('.hint');
        const getInput = () => dz.querySelector('input[type=file]');
        const showName = () => { const i = getInput(); hint.textContent = (i && i.files.length) ? i.files[0].name : defaultHint; };
        dz.addEventListener('change', showName);
        dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('over'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', (e) => {
            e.preventDefault(); dz.classList.remove('over');
            const i = getInput();
            if (i && e.dataTransfer.files.length) { i.files = e.dataTransfer.files; showName(); }
        });
        dz.__resetHint = () => { hint.textContent = defaultHint; };
    }
    initDropzone('cb_dz', 'Suelta el archivo o haz clic para buscar');
    initDropzone('dn_dz', 'Suelta el archivo o haz clic para buscar');

    // Fábrica genérica para una tabla de adjuntos
    function makeManager(cfg) {
        let idx = 0;
        const body  = document.getElementById(cfg.bodyId);
        const empty = document.getElementById(cfg.emptyId);
        const store = document.getElementById(cfg.storeId);

        document.getElementById(cfg.addBtnId).addEventListener('click', () => {
            cfg.resetFields();
            const fi = document.getElementById(cfg.fileId);
            if (fi) fi.value = '';
            if (cfg.dzId) { const dz = document.getElementById(cfg.dzId); dz.__resetHint && dz.__resetHint(); }
            openModal(cfg.modalId);
        });

        document.getElementById(cfg.saveBtnId).addEventListener('click', () => {
            const data = cfg.collect();
            if (!data) return;                 // validación interna
            const i = idx++;

            // Grupo de inputs ocultos (se envían con el form)
            const g = document.createElement('div');
            g.dataset.idx = i;
            g.innerHTML = cfg.hiddenInputs(i, data);

            // Relocar el input file al form (se envía como multipart)
            const fileInput = document.getElementById(cfg.fileId);
            const fileName  = fileInput.files[0] ? fileInput.files[0].name : '';
            const parent    = fileInput.parentNode;
            fileInput.name = `${cfg.name}[${i}][path]`;
            fileInput.style.display = 'none';
            fileInput.removeAttribute('id');
            g.appendChild(fileInput);
            store.appendChild(g);

            // Recrear un file input fresco dentro del dropzone
            const fresh = document.createElement('input');
            fresh.type = 'file'; fresh.id = cfg.fileId; fresh.accept = 'application/pdf,image/*';
            fresh.style.display = 'none';
            parent.appendChild(fresh);
            if (cfg.dzId) { const dz = document.getElementById(cfg.dzId); dz.__resetHint && dz.__resetHint(); }

            // Fila en la tabla
            empty.style.display = 'none';
            const tr = document.createElement('tr');
            tr.dataset.idx = i;
            tr.dataset.doctype = String(data.type ?? '');           // tipo (comprobante o anexo)
            if (data.has_retention) tr.dataset.retention = '1';      // solo recibo x honorario
            tr.innerHTML = cfg.rowHtml(i, data, fileName);
            body.appendChild(tr);

            closeModal(cfg.modalId);
            showToast(cfg.successMsg || 'Agregado', 'success');
        });

        const refreshEmpty = () => {
            if (!body.querySelector('tr[data-idx], tr[data-fileid]')) empty.style.display = '';
        };

        body.addEventListener('click', (e) => {
            // Quitar fila nueva
            const b = e.target.closest('.rm-attach');
            if (b) {
                const i = b.dataset.idx;
                body.querySelector(`tr[data-idx="${i}"]`)?.remove();
                store.querySelector(`div[data-idx="${i}"]`)?.remove();
                refreshEmpty();
                return;
            }
            // Quitar fila existente (marca el archivo para borrado)
            const ex = e.target.closest('.rm-existing');
            if (ex) {
                const tr = ex.closest('tr');
                const id = tr.dataset.fileid;
                tr.remove();
                document.getElementById('keep-' + id)?.remove();
                refreshEmpty();
            }
        });

        // Precarga (edición): agregar una fila de archivo YA existente
        if (cfg.existingRowHtml) {
            window['__existing_' + cfg.name] = (data) => {
                empty.style.display = 'none';
                const tr = document.createElement('tr');
                tr.dataset.fileid = data.id;
                tr.dataset.doctype = String(data.type_file ?? data.type ?? '');
                if (data.has_retention) tr.dataset.retention = '1';
                tr.innerHTML = cfg.existingRowHtml(data);
                body.appendChild(tr);
                // input keep_files[] para conservarlo al guardar
                const keepHost = document.getElementById('keep-files');
                if (keepHost && !document.getElementById('keep-' + data.id)) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'keep_files[]'; inp.value = data.id;
                    inp.id = 'keep-' + data.id;
                    keepHost.appendChild(inp);
                }
            };
        }
    }

    // ── Comprobantes ──
    makeManager({
        bodyId: 'comprobantes-body', emptyId: 'comprobantes-empty', storeId: 'comprobantes-store',
        addBtnId: 'btn-add-comprobante', saveBtnId: 'cb-save', modalId: 'modal-comprobante',
        fileId: 'cb_file', dzId: 'cb_dz', name: 'comprobantes', successMsg: 'Comprobante agregado',
        resetFields() {
            ['cb_type','cb_serie','cb_docnum','cb_amount','cb_date','cb_coment'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('cb_retencion').checked = false;
            document.getElementById('cb_retencion_wrap').style.display = 'none';
        },
        collect() {
            const t = document.getElementById('cb_type');
            const type = t.value, label = type ? t.options[t.selectedIndex].text : '';
            const serie  = document.getElementById('cb_serie').value.trim();
            const docnum = document.getElementById('cb_docnum').value.trim();
            const amount = document.getElementById('cb_amount').value;
            const date   = document.getElementById('cb_date').value;
            const coment = document.getElementById('cb_coment').value.trim();
            const file   = document.getElementById('cb_file').files[0];
            if (!type || !serie || !docnum || !amount || !date) { showToast('Completa tipo, serie, N° documento, monto y fecha.', 'warning'); return null; }
            if (!file) { showToast('Adjunta el archivo del comprobante.', 'warning'); return null; }
            if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) { showToast('El comprobante de pago debe ser un archivo PDF.', 'warning'); return null; }
            if (file.size > 3 * 1024 * 1024) { showToast('El comprobante no puede superar los 3 MB.', 'warning'); return null; }
            const esRecibo = window.DOC_RULES && String(type) === String(window.DOC_RULES.recibo);
            const has_retention = esRecibo && document.getElementById('cb_retencion').checked;
            return { type, label, serie, docnum, amount, date, coment, has_retention };
        },
        hiddenInputs(i, d) {
            return `
                <input type="hidden" name="comprobantes[${i}][type_file]" value="${esc(d.type)}">
                <input type="hidden" name="comprobantes[${i}][type_file_label]" value="${esc(d.label)}">
                <input type="hidden" name="comprobantes[${i}][serie]" value="${esc(d.serie)}">
                <input type="hidden" name="comprobantes[${i}][document_number]" value="${esc(d.docnum)}">
                <input type="hidden" name="comprobantes[${i}][amount]" value="${esc(d.amount)}">
                <input type="hidden" name="comprobantes[${i}][emission_date]" value="${esc(d.date)}">
                <input type="hidden" name="comprobantes[${i}][comentario]" value="${esc(d.coment)}">
                ${d.has_retention ? `<input type="hidden" name="comprobantes[${i}][has_retention]" value="1">` : ''}`;
        },
        rowHtml(i, d, fileName) {
            return `
                <td>${esc(d.label)}</td>
                <td class="cell-mono">${d.serie ? esc(d.serie) : '—'}</td>
                <td class="cell-mono">${esc(d.docnum)}</td>
                <td class="cell-strong">${parseFloat(d.amount).toFixed(2)}</td>
                <td>${esc(d.date)}</td>
                <td>${d.coment ? esc(d.coment) : '—'}</td>
                <td>${fileName ? esc(fileName) : '—'}</td>
                <td style="text-align:center"><button type="button" class="btn-remove rm-attach" data-idx="${i}">×</button></td>`;
        },
        existingRowHtml(d) {
            return `
                <td>${esc(d.type_file_label)}</td>
                <td class="cell-mono">${d.serie ? esc(d.serie) : '—'}</td>
                <td class="cell-mono">${esc(d.document_number)}</td>
                <td class="cell-strong">${d.amount != null ? parseFloat(d.amount).toFixed(2) : '—'}</td>
                <td>${esc(d.emission_date ?? '')}</td>
                <td>${d.comentario ? esc(d.comentario) : '—'}</td>
                <td>${d.path ? `<a href="${esc(d.path)}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
                <td style="text-align:center"><button type="button" class="btn-remove rm-existing" title="Quitar">×</button></td>`;
        },
    });

    // ── Documentos anexos ──
    makeManager({
        bodyId: 'documentos-body', emptyId: 'documentos-empty', storeId: 'documentos-store',
        addBtnId: 'btn-add-documento', saveBtnId: 'dn-save', modalId: 'modal-documento',
        fileId: 'dn_file', dzId: 'dn_dz', name: 'documentos', successMsg: 'Documento agregado',
        resetFields() { ['dn_type','dn_coment'].forEach(id => document.getElementById(id).value = ''); },
        collect() {
            const t = document.getElementById('dn_type');
            const type = t.value, label = type ? t.options[t.selectedIndex].text : '';
            const coment = document.getElementById('dn_coment').value.trim();
            const file = document.getElementById('dn_file').files[0];
            if (!type) { showToast('Selecciona el tipo de documento.', 'warning'); return null; }
            if (!file) { showToast('Adjunta un archivo.', 'warning'); return null; }
            return { type, label, coment };
        },
        hiddenInputs(i, d) {
            return `
                <input type="hidden" name="documentos[${i}][type]" value="${esc(d.type)}">
                <input type="hidden" name="documentos[${i}][type_label]" value="${esc(d.label)}">
                <input type="hidden" name="documentos[${i}][comentario]" value="${esc(d.coment)}">`;
        },
        rowHtml(i, d, fileName) {
            return `
                <td>${esc(d.label)}</td>
                <td>${d.coment ? esc(d.coment) : '—'}</td>
                <td>${fileName ? esc(fileName) : '—'}</td>
                <td style="text-align:center"><button type="button" class="btn-remove rm-attach" data-idx="${i}">×</button></td>`;
        },
        existingRowHtml(d) {
            return `
                <td>${esc(d.type_label)}</td>
                <td>${d.comentario ? esc(d.comentario) : '—'}</td>
                <td>${d.path ? `<a href="${esc(d.path)}" target="_blank" style="color:var(--primary)">Ver</a>` : '—'}</td>
                <td style="text-align:center"><button type="button" class="btn-remove rm-existing" title="Quitar">×</button></td>`;
        },
    });

    // Switch "Tiene retención": visible solo para Recibo x Honorario
    document.getElementById('cb_type').addEventListener('change', function () {
        const esRecibo = window.DOC_RULES && String(this.value) === String(window.DOC_RULES.recibo);
        const wrap = document.getElementById('cb_retencion_wrap');
        wrap.style.display = esRecibo ? '' : 'none';
        if (!esRecibo) document.getElementById('cb_retencion').checked = false;
    });
})();
</script>

<script>
// ── Cronograma de cuotas (condición fraccionado) ──
(function () {
    const FRACCIONADO = @json($fraccionadoIds).map(String);
    const condSel  = document.getElementById('condition_payment');
    const section  = document.getElementById('cuotas-section');
    const display  = document.getElementById('cuotas-display');
    const planHidden = document.getElementById('plan_cuotas');
    const quotasHidden = document.getElementById('quotas');
    const rowsBody = document.getElementById('cuotas-rows');

    const curSymbol = () => document.getElementById('currency').value === 'USD' ? '$ ' : 'S/ ';
    const fmt = (v) => curSymbol() + (parseFloat(v) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const orderTotal = () => parseFloat(window.__orderTotal || 0);
    const TODAY = new Date().toLocaleDateString('en-CA');   // YYYY-MM-DD (zona local)

    // Mostrar/ocultar sección según condición
    condSel.addEventListener('change', () => {
        const isFrac = FRACCIONADO.includes(condSel.value);
        section.style.display = isFrac ? '' : 'none';
        if (!isFrac) { planHidden.value = ''; quotasHidden.value = 1; renderDisplay([]); }
        // Vencimiento obligatorio solo si NO es fraccionado
        const vr = document.getElementById('venc-req');
        if (vr) vr.style.display = (condSel.value && !isFrac) ? 'inline' : 'none';
    });

    // Si cambia la moneda de la cabecera, re-pinta el plan con el símbolo correcto
    document.getElementById('currency').addEventListener('change', () => {
        let plan = [];
        try { plan = JSON.parse(planHidden.value || '[]'); } catch (e) { plan = []; }
        if (plan.length) renderDisplay(plan);
    });

    // Config rápida
    const cfgNum  = document.getElementById('cfg-num');
    const cfgFreq = document.getElementById('cfg-freq');
    const cfgDist = document.getElementById('cfg-dist');   // checked = Porcentajes

    // ── Modal ──
    function cuotaRow(num, fecha = '', monto = '', pct = '') {
        const tr = document.createElement('tr');
        tr.className = 'cuota-row';
        tr.innerHTML = `
            <td class="cell-strong cuota-num">${num}</td>
            <td><input type="date" class="cuota-fecha" min="${TODAY}" value="${fecha}"></td>
            <td class="cuota-pct-cell" style="display:none"><input type="number" step="0.01" min="0" max="100" class="cuota-pct" value="${pct}"></td>
            <td><input type="number" step="0.01" min="0" class="cuota-monto" value="${monto}"></td>
            <td style="text-align:center"><button type="button" class="btn-remove cuota-rm">×</button></td>`;
        return tr;
    }

    // Muestra/oculta la columna % y deja el monto derivado (solo lectura) en modo Porcentajes
    function setMode(esPct) {
        document.querySelectorAll('#modal-cuotas .pct-col, #cuotas-rows .cuota-pct-cell').forEach(el => el.style.display = esPct ? '' : 'none');
        rowsBody.querySelectorAll('.cuota-row').forEach(row => {
            const montoEl = row.querySelector('.cuota-monto');
            const pctEl   = row.querySelector('.cuota-pct');
            montoEl.readOnly = esPct;
            montoEl.style.background = esPct ? 'var(--bg-surface-secondary)' : '';
            if (esPct && !pctEl.value) {   // al activar %, deriva el % desde el monto actual
                const m = parseFloat(montoEl.value) || 0, t = orderTotal();
                pctEl.value = t > 0 ? (Math.round(m / t * 10000) / 100).toFixed(2) : '';
            }
        });
    }

    function renumber() {
        rowsBody.querySelectorAll('.cuota-row').forEach((r, i) => r.querySelector('.cuota-num').textContent = 'Cuota ' + (i + 1));
    }

    function recalcModal() {
        let sum = 0;
        rowsBody.querySelectorAll('.cuota-monto').forEach(m => sum += parseFloat(m.value) || 0);
        const diff = Math.round((orderTotal() - sum) * 100) / 100;
        document.getElementById('cuotas-sum').textContent = fmt(sum);
        const diffEl = document.getElementById('cuotas-diff');
        diffEl.textContent = fmt(diff);
        diffEl.style.color = Math.abs(diff) < 0.01 ? 'var(--green)' : 'var(--red)';
    }

    // Fecha de la cuota i (1..N): hoy + i × frecuencia
    function fechaCuota(i, freq) {
        const d = new Date(); d.setHours(0, 0, 0, 0);
        if (freq === 'semanal')          d.setDate(d.getDate() + 7 * i);
        else if (freq === 'quincenal')   d.setDate(d.getDate() + 15 * i);
        else if (freq === 'mensual')     d.setMonth(d.getMonth() + i);
        else if (freq === 'trimestral')  d.setMonth(d.getMonth() + 3 * i);
        return d.toLocaleDateString('en-CA');   // YYYY-MM-DD
    }

    // Genera el plan automáticamente según N° de cuotas, frecuencia y distribución
    function aplicarPlan() {
        const N = parseInt(cfgNum.value, 10) || 0;
        if (N < 1) { showToast('Indica un número de cuotas válido.', 'warning'); return; }
        const total = orderTotal();
        if (total <= 0) { showToast('La orden no tiene un total válido. Completa los ítems primero.', 'warning'); return; }
        const freq  = cfgFreq.value;
        const esPct = cfgDist.checked;

        const filas = [];
        let acumMonto = 0, acumPct = 0;
        for (let i = 1; i <= N; i++) {
            const ultima = i === N;
            let pct, monto;
            if (esPct) {
                pct   = ultima ? Math.round((100 - acumPct) * 100) / 100 : Math.round(100 / N * 100) / 100;
                monto = ultima ? Math.round((total - acumMonto) * 100) / 100 : Math.round(pct / 100 * total * 100) / 100;
            } else {
                monto = ultima ? Math.round((total - acumMonto) * 100) / 100 : Math.round(total / N * 100) / 100;
                pct   = Math.round(monto / total * 10000) / 100;
            }
            acumPct += pct; acumMonto += monto;
            filas.push({ fecha: fechaCuota(i, freq), monto: monto.toFixed(2), pct: pct.toFixed(2) });
        }

        rowsBody.innerHTML = '';
        filas.forEach((f, i) => rowsBody.appendChild(cuotaRow('Cuota ' + (i + 1), f.fecha, f.monto, f.pct)));
        setMode(esPct);
        recalcModal();
    }

    document.getElementById('cfg-apply').addEventListener('click', aplicarPlan);
    cfgDist.addEventListener('change', () => setMode(cfgDist.checked));

    document.getElementById('btn-config-cuotas').addEventListener('click', () => {
        document.getElementById('cuotas-order-total').textContent = fmt(orderTotal());
        // Cargar plan existente o una fila vacía
        let plan = [];
        try { plan = JSON.parse(planHidden.value || '[]'); } catch (e) { plan = []; }
        rowsBody.innerHTML = '';
        if (plan.length) {
            plan.forEach((c, i) => rowsBody.appendChild(cuotaRow('Cuota ' + (i + 1), c.fecha_vencimiento || '', c.monto || '')));
        } else {
            rowsBody.appendChild(cuotaRow('Cuota 1'));
        }
        // Reset de la config rápida (por defecto Montos)
        cfgNum.value = plan.length || 3;
        cfgDist.checked = false;
        setMode(false);
        recalcModal();
        openModal('modal-cuotas');
    });

    rowsBody.addEventListener('click', (e) => {
        if (e.target.classList.contains('cuota-rm')) {
            const row  = e.target.closest('.cuota-row');
            const rows = rowsBody.querySelectorAll('.cuota-row');
            if (rows.length > 1) {
                row.remove(); renumber();
            } else {
                // Última cuota: limpiarla en vez de bloquear
                row.querySelector('.cuota-fecha').value = '';
                row.querySelector('.cuota-monto').value = '';
            }
            recalcModal();
        }
    });
    rowsBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('cuota-monto')) recalcModal();
        if (e.target.classList.contains('cuota-pct')) {
            // Modo Porcentajes: el monto se deriva del % sobre el total
            const row   = e.target.closest('.cuota-row');
            const pct   = parseFloat(e.target.value) || 0;
            const monto = Math.round(pct / 100 * orderTotal() * 100) / 100;
            row.querySelector('.cuota-monto').value = monto.toFixed(2);
            recalcModal();
        }
    });

    document.getElementById('cuotas-save').addEventListener('click', () => {
        const plan = [];
        let valid = true, sum = 0, fechaPasada = false;
        rowsBody.querySelectorAll('.cuota-row').forEach((r, i) => {
            const fecha = r.querySelector('.cuota-fecha').value;
            const monto = parseFloat(r.querySelector('.cuota-monto').value) || 0;
            if (!fecha || monto <= 0) valid = false;
            if (fecha && fecha < TODAY) fechaPasada = true;
            sum += monto;
            plan.push({ numero: i + 1, fecha_vencimiento: fecha, monto: monto });
        });
        if (!valid) { showToast('Cada cuota debe tener fecha y monto válido.', 'warning'); return; }
        if (fechaPasada) { showToast('La fecha de vencimiento de las cuotas no puede ser anterior a hoy.', 'warning'); return; }
        if (Math.abs(orderTotal() - sum) > 0.01) {
            showToast(`La suma de cuotas (${fmt(sum)}) debe ser igual al total de la orden (${fmt(orderTotal())}).`, 'error');
            return;
        }
        planHidden.value = JSON.stringify(plan);
        quotasHidden.value = plan.length;
        renderDisplay(plan);
        closeModal('modal-cuotas');
        showToast('Plan de cuotas guardado', 'success');
    });

    function renderDisplay(plan) {
        if (!plan.length) {
            display.innerHTML = `<div style="padding:16px;border:1px dashed var(--border-color);border-radius:var(--radius);color:var(--text-muted);font-size:13px;font-style:italic">Sin configurar aún. Haz clic en "Configurar cuotas".</div>`;
            return;
        }
        const rows = plan.map(c => `<tr><td class="cell-strong">Cuota ${c.numero}</td><td>${c.fecha_vencimiento}</td><td class="cell-strong" style="text-align:right">${fmt(c.monto)}</td></tr>`).join('');
        const total = plan.reduce((s, c) => s + parseFloat(c.monto || 0), 0);
        display.innerHTML = `
            <div class="table-responsive" style="max-width:560px">
                <table class="table" style="width:auto;min-width:100%">
                    <thead><tr><th>Cuota</th><th>Fecha de vencimiento</th><th style="text-align:right">Monto</th></tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr><td colspan="2" class="cell-strong">Total</td><td class="cell-strong" style="text-align:right;color:var(--green)">${fmt(total)}</td></tr></tfoot>
                </table>
            </div>`;
    }

    // Precarga (edición): muestra sección + carga el plan existente
    window.__setCuotas = (plan) => {
        if (!plan || !plan.length) return;
        const norm = plan.map((c, i) => ({ numero: i + 1, fecha_vencimiento: c.fecha_vencimiento || '', monto: c.monto || 0 }));
        planHidden.value = JSON.stringify(norm);
        quotasHidden.value = norm.length;
        section.style.display = '';
        renderDisplay(norm);
    };
})();
</script>

<script>
// ── Select con búsqueda (filtra la lista ya renderizada, sin servidor) ──
(function () {
    function enhance(select) {
        const placeholder = (select.options[0] && select.options[0].value === '') ? select.options[0].text : 'Buscar...';

        const wrap = document.createElement('div');
        wrap.className = 'multi-select ss';
        wrap.innerHTML = `
            <div class="ms-input">
                <input type="text" class="ms-search" autocomplete="off" placeholder="${placeholder}">
                <svg class="ms-chev" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6l4 4 4-4"/></svg>
            </div>
            <div class="ms-menu" hidden></div>`;
        select.style.display = 'none';
        select.removeAttribute('required');                 // se valida en el servidor
        select.parentNode.insertBefore(wrap, select.nextSibling);

        const input = wrap.querySelector('.ms-search');
        const menu  = wrap.querySelector('.ms-menu');

        let options  = [];
        let curValue = '';
        let curLabel = '';

        function readOptions() {
            options  = [...select.options].map(o => ({ value: o.value, label: o.text }));
            curValue = select.value;
            curLabel = (options.find(o => o.value === curValue) || {}).label || '';
            input.value = curValue ? curLabel : '';
        }

        function renderMenu(q = '') {
            const ql = q.toLowerCase();
            const list = options.filter(o => o.value !== '' && o.label.toLowerCase().includes(ql));
            menu.innerHTML = list.length
                ? list.map(o => `<button type="button" class="ms-option${o.value === curValue ? ' active' : ''}" data-value="${o.value}">${o.label}</button>`).join('')
                : `<div class="ms-empty">Sin coincidencias</div>`;
        }
        const open  = () => { if (input.disabled) return; wrap.classList.add('open'); menu.hidden = false; renderMenu(input.value === curLabel ? '' : input.value); };
        const close = () => { wrap.classList.remove('open'); menu.hidden = true; };

        function choose(value, label) {
            curValue = value; curLabel = label;
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = label;
            close();
        }

        wrap.querySelector('.ms-input').addEventListener('click', () => { if (!input.disabled) input.focus(); });
        input.addEventListener('focus', () => { open(); input.select(); });
        input.addEventListener('input', () => { open(); renderMenu(input.value); });
        menu.addEventListener('mousedown', (e) => {
            const opt = e.target.closest('.ms-option');
            if (!opt) return;
            e.preventDefault();
            const o = options.find(x => x.value === opt.dataset.value);
            if (o) choose(o.value, o.label);
        });
        input.addEventListener('blur', () => setTimeout(() => { close(); input.value = curValue ? curLabel : ''; }, 120));
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) { close(); input.value = curValue ? curLabel : ''; } });

        // Estado deshabilitado inicial
        function setDisabled(off) {
            input.disabled = off;
            wrap.classList.toggle('ss-disabled', off);
            readOptions();
        }
        setDisabled(select.disabled);
        readOptions();

        // API para sincronizar cuando el <select> cambia por JS
        select.__ss = {
            refresh: readOptions,
            setDisabled,
        };
    }

    document.querySelectorAll('select.searchable').forEach(enhance);
})();
</script>

<script>
// ── Envío del formulario (AJAX multipart) ──
(function () {
    const form = document.getElementById('order-form');
    const btn  = document.querySelector('button[form="order-form"]');
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    // Regla Recibo x Honorario → anexos obligatorios (Informe Laboral / Suspensión)
    function validarReciboHonorario() {
        const R = window.DOC_RULES;
        if (!R || !R.recibo) return true;
        const comps  = [...document.querySelectorAll('#comprobantes-body tr[data-doctype]')];
        const recibos = comps.filter(tr => String(tr.dataset.doctype) === String(R.recibo));
        if (!recibos.length) return true;
        const anexos = [...document.querySelectorAll('#documentos-body tr[data-doctype]')].map(tr => String(tr.dataset.doctype));
        if (R.informe && !anexos.includes(String(R.informe))) {
            showToast(`Para un Recibo x Honorario debes adjuntar el anexo "${R.informeLabel}".`, 'warning'); return false;
        }
        const haySinRetencion = recibos.some(tr => tr.dataset.retention !== '1');
        if (haySinRetencion && R.suspension && !anexos.includes(String(R.suspension))) {
            showToast(`El Recibo x Honorario sin retención exige el anexo "${R.suspensionLabel}".`, 'warning'); return false;
        }
        return true;
    }

    // La suma del plan de cuotas debe coincidir con el total de la orden
    function validarSumaCuotas() {
        let plan = [];
        try { plan = JSON.parse(document.getElementById('plan_cuotas').value || '[]'); } catch (e) { plan = []; }
        if (!plan.length) return true;   // al contado / sin cronograma
        const sum   = plan.reduce((s, c) => s + (parseFloat(c.monto) || 0), 0);
        const total = parseFloat(window.__orderTotal || 0);
        if (Math.abs(sum - total) > 0.01) {
            showToast(`La suma de cuotas (${sum.toFixed(2)}) no coincide con el total de la orden (${total.toFixed(2)}). Reconfigura el plan de cuotas.`, 'error');
            return false;
        }
        return true;
    }

    // Reglas: vencimiento (no fraccionado), comprobante (General), anexos (Proyectos en General)
    function validarReglasOrden() {
        const R = window.DOC_RULES || {};
        const cond = document.getElementById('condition_payment').value;
        const venc = document.querySelector('[name="expiration_date"]').value;
        const esFrac = (window.FRACCIONADO_IDS || []).includes(String(cond));
        if (cond && esFrac) {
            let plan = [];
            try { plan = JSON.parse(document.getElementById('plan_cuotas').value || '[]'); } catch (e) { plan = []; }
            if (!plan.length) { showToast('Configura el plan de cuotas para el pago fraccionado.', 'warning'); return false; }
        } else if (cond && !esFrac && !venc) {
            showToast('Selecciona la fecha de vencimiento (el pago no es fraccionado).', 'warning'); return false;
        }
        // ¿Programación General? (el nombre no contiene "urgente")
        const sched = document.querySelector('[name="payment_schedule_id"]');
        const schedTxt = sched.options[sched.selectedIndex] ? sched.options[sched.selectedIndex].text : '';
        if (/urgente/i.test(schedTxt)) return true;   // urgente: reglas 2 y 3 no aplican
        // 2) Comprobante Factura o Boleta obligatorio
        const comps = [...document.querySelectorAll('#comprobantes-body tr[data-doctype]')].map(tr => String(tr.dataset.doctype));
        const tieneFB = (R.factura && comps.includes(String(R.factura))) || (R.boleta && comps.includes(String(R.boleta)));
        if (!tieneFB) { showToast('En programación General debes adjuntar un comprobante de pago (Factura o Boleta).', 'warning'); return false; }
        // 3) Área Proyectos → anexos Cotización + Guía de Remisión
        const area = document.querySelector('[name="area_id"]').value;
        if (R.proyectosArea && String(area) === String(R.proyectosArea)) {
            const anx = [...document.querySelectorAll('#documentos-body tr[data-doctype]')].map(tr => String(tr.dataset.doctype));
            const faltan = [];
            if (R.cotizacion && !anx.includes(String(R.cotizacion))) faltan.push(R.cotizacionLabel);
            if (R.guia && !anx.includes(String(R.guia))) faltan.push(R.guiaLabel);
            // Si además es Orden de Servicio → exige Evidencias
            const format = document.getElementById('format_id').value;
            const esServicio = R.ordenServicio && String(format) === String(R.ordenServicio);
            if (esServicio && R.evidencia && !anx.includes(String(R.evidencia))) faltan.push(R.evidenciaLabel);
            if (faltan.length) { showToast('Para el área Proyectos (General) son obligatorios los anexos: ' + faltan.join(', ') + '.', 'warning'); return false; }
        }
        return true;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!document.getElementById('supplier_id').value) {
            showToast('Busca y selecciona un proveedor válido por RUC antes de guardar.', 'warning');
            return;
        }
        // La cuenta bancaria del proveedor (destino del pago) es obligatoria
        if (!document.getElementById('supplier_account_id').value) {
            const tieneCuentas = document.querySelectorAll('#supplier-accounts input[name="acc_pick"]').length > 0;
            showToast(tieneCuentas
                ? 'Selecciona la cuenta bancaria del proveedor.'
                : 'Este proveedor no tiene cuentas bancarias. Regístrale una en Proveedores.', 'warning');
            return;
        }
        if (!validarReciboHonorario()) return;
        if (!validarReglasOrden()) return;
        if (!validarSumaCuotas()) return;
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
                    return;   // no rehabilitar el botón; vamos a redirigir
                } else {
                    showToast(j.description || 'No se pudo crear la orden.', 'error');
                }
            } else {
                showToast('Error al guardar la orden.', 'error');
            }
        } catch (err) {
            showToast('Error de red al guardar.', 'error');
        }
        window.unblockUI && window.unblockUI();
        btn.disabled = false; btn.innerHTML = original;
    });
})();
</script>

<script>
// ── Precarga de datos en modo edición ──
(function () {
    const P = window.PREFILL;
    if (!P) return;

    const form = document.getElementById('order-form');
    const setField = (name, val) => {
        const el = form.elements[name];
        if (!el) return;
        el.value = (val ?? '');
        if (el.__ss) el.__ss.refresh();
    };

    // Campos escalares (sin disparar change para no limpiar dependientes)
    [
        'company_id', 'format_id', 'currency', 'title', 'sede_id', 'area_id',
        'justification', 'observation', 'expiration_date', 'payment_id',
        'payment_schedule_id', 'category_id', 'condition_payment',
    ].forEach(k => setField(k, P[k]));

    // IGV / descuento
    const grab = document.getElementById('grabable');
    grab.checked = !!P.grabable;
    const disc = document.getElementById('apply_discount');
    disc.checked = !!P.apply_discount;
    disc.dispatchEvent(new Event('change'));          // habilita/deshabilita el select de descuento
    if (P.apply_discount) setField('discount_type_id', P.discount_type_id);

    // Ítems
    if (window.__setItems) window.__setItems(P.items || []);

    // Centros de costo (opciones + seleccionados)
    if (window.__setCostCenters) window.__setCostCenters(P.ccOptions || [], P.ccSelected || []);

    // Proveedor
    if (P.supplier && window.__setSupplier) window.__setSupplier(P.supplier, P.supplier.accounts || [], P.supplierAccountId);

    // Archivos existentes (comprobantes + documentos)
    (P.comprobantes || []).forEach(c => window.__existing_comprobantes && window.__existing_comprobantes(c));
    (P.documentos || []).forEach(d => window.__existing_documentos && window.__existing_documentos(d));

    // Cuotas: dispara el handler de condición; si quedó visible la sección, carga el plan
    const cond = document.getElementById('condition_payment');
    cond.dispatchEvent(new Event('change'));
    if (document.getElementById('cuotas-section').style.display !== 'none' && window.__setCuotas) {
        window.__setCuotas(P.planCuotas || []);
    }

    // Recalcular totales
    grab.dispatchEvent(new Event('change'));

    // Edición restringida: bloquear Proveedor, Detalle y Condición de pago.
    // Se deshabilitan todos los controles dentro de las cards .is-locked DESPUÉS
    // de la precarga; al estar disabled no se envían en el FormData y el servidor
    // los repone desde la BD (montos y cuotas intactos).
    if (window.RESTRICTED) {
        document.querySelectorAll('.is-locked').forEach(card => {
            card.querySelectorAll('input, select, textarea, button').forEach(el => {
                el.disabled = true;
                if (el.__ss) el.__ss.setDisabled(true);   // widget buscable
            });
        });
    }
})();
</script>

<script>
// ── Programación Urgente → la condición solo puede ser "Al Contado" ──
document.addEventListener('DOMContentLoaded', function () {
    const schedSel = document.getElementById('payment_schedule_id');
    const condSel  = document.getElementById('condition_payment');
    if (!schedSel || !condSel) return;

    // Id de "Al Contado" = la condición que NO está en FRACCIONADO_IDS.
    const fracc = (window.FRACCIONADO_IDS || []).map(String);
    const CONTADO_ID = [...condSel.options].map(o => o.value).find(v => v && !fracc.includes(String(v))) || '';

    const isUrgente = () => {
        const opt = schedSel.options[schedSel.selectedIndex];
        return opt ? /urgente/i.test(opt.text) : false;
    };

    function applyRestriction() {
        if (window.RESTRICTED) return;   // en edición restringida la condición ya está bloqueada
        if (isUrgente()) {
            // Forzar "Al Contado" y bloquear el combo (el <select> nativo sigue enviando su valor).
            if (condSel.value !== CONTADO_ID) {
                condSel.value = CONTADO_ID;
                condSel.dispatchEvent(new Event('change'));   // re-aplica reglas de cuotas/vencimiento
            }
            condSel.__ss && condSel.__ss.refresh();
            condSel.__ss && condSel.__ss.setDisabled(true);
        } else {
            condSel.__ss && condSel.__ss.setDisabled(false);
        }
    }

    schedSel.addEventListener('change', applyRestriction);
    applyRestriction();   // estado inicial (creación / edición con programación preseleccionada)
});
</script>

@if ($isEdit && (($acts['observe'] ?? false) || ($acts['reject'] ?? false)))
<script>
// ── Acciones de flujo desde el form de edición (Observar / Rechazar) ──
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        });
        return { ok: res.ok, json: await res.json().catch(() => ({})) };
    }

    @if ($acts['observe'] ?? false)
    document.getElementById('btn-observe').addEventListener('click', () => {
        document.getElementById('obs_type').value = '';
        document.getElementById('obs_comment').value = '';
        openModal('modal-observe');
    });
    document.getElementById('observe-save').addEventListener('click', async function () {
        const obs_type = document.getElementById('obs_type').value;
        const obs_comment = document.getElementById('obs_comment').value.trim();
        if (!obs_type || !obs_comment) { showToast('Completa el tipo y el comentario.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Enviando...';
        try {
            const r = await post("{{ route('orders.observe', $order->id) }}", { obs_type, obs_comment });
            if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = r.json.data.redirect, 900); return; }
            showToast(r.json.description || 'No se pudo registrar.', 'error');
        } catch (e) { showToast('Error al observar.', 'error'); }
        this.disabled = false; this.textContent = 'Enviar observación';
    });
    @endif

    @if ($acts['reject'] ?? false)
    document.getElementById('btn-reject').addEventListener('click', () => {
        document.getElementById('reject_reason').value = '';
        openModal('modal-reject');
    });
    document.getElementById('reject-confirm').addEventListener('click', async function () {
        const reason = document.getElementById('reject_reason').value.trim();
        if (!reason) { showToast('Indica el motivo del rechazo.', 'warning'); return; }
        this.disabled = true; this.textContent = 'Rechazando...';
        try {
            const r = await post("{{ route('orders.reject', $order->id) }}", { reject_reason: reason });
            if (r.ok && r.json.status === 1) { showToast(r.json.description, 'success'); setTimeout(() => location = r.json.data.redirect, 900); return; }
            showToast(r.json.description || 'No se pudo rechazar.', 'error');
        } catch (e) { showToast('Error al rechazar.', 'error'); }
        this.disabled = false; this.textContent = 'Confirmar rechazo';
    });
    @endif
})();
</script>
@endif

<script>
// ── Contador de caracteres de Justificación (máx 500) ──
(function () {
    const ta = document.getElementById('justification');
    const out = document.getElementById('just-count');
    if (!ta || !out) return;
    const update = () => { out.textContent = ta.value.length; out.parentElement.style.color = ta.value.length >= 500 ? 'var(--red)' : 'var(--text-muted)'; };
    ta.addEventListener('input', update);
    update();   // estado inicial (incluye precarga en edición)
})();

// ── Responsable según la Gestión elegida (solo GA) ──
(function () {
    const sel = document.getElementById('type_id');
    const out = document.getElementById('resp-name');
    if (!sel || !out) return;
    sel.addEventListener('change', () => {
        const opt = sel.options[sel.selectedIndex];
        out.value = (opt && opt.dataset.responsible) ? opt.dataset.responsible : '';
    });
})();
</script>
@endpush