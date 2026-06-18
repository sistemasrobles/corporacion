@php
    // Color de badge por estado
    $statusClass = fn (int $s) => match ($s) {
        3, 7, 9 => 'status-green',
        2, 5    => 'status-yellow',
        4       => 'status-red',
        default => 'status-blue',
    };
@endphp
@foreach ($orders as $order)
    @php
        $detail = $order->detail;
        $amount = floatval($detail?->amount_neto ?? 0) > 0
            ? floatval($detail->amount_neto)
            : floatval($detail?->amount_ref ?? 0);
        $cur  = ($detail?->currency === 'USD') ? '$ ' : 'S/ ';
        $acts = $actions($order);
    @endphp
    <tr
        data-status="{{ $order->status }}"
        data-format="{{ $order->format_id }}"
        data-area="{{ $detail?->area_id }}"
        data-schedule="{{ $order->payment_schedule_id }}"
    >
        @if ($isGA)<td style="text-align:center"><input type="checkbox" class="chk-row" data-id="{{ $order->id }}" data-status="{{ $order->status }}"></td>@endif
        <td class="cell-mono"><a href="{{ route('orders.show', $order->id) }}" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600" title="Ver detalle de la orden">{{ $order->code }}</a></td>
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
            @if ($canEdit($order))
                <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-outline btn-sm" title="Editar" style="color:var(--primary)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            @endif
            {{-- El "Reenviar" de una orden OBSERVADA (5) se hace desde el lápiz (Guardar y reenviar) --}}
            @if ($acts['approve'] && !$acts['close'] && (int) $order->status !== 5)
                <button type="button" class="btn btn-outline btn-sm btn-approve" data-id="{{ $order->id }}" data-code="{{ $order->code }}" data-label="{{ $acts['approveLabel'] }}" title="{{ $acts['approveLabel'] }}" style="color:var(--green)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
                </button>
            @endif
            @if ($acts['code'] || $acts['close'])
                {{-- Código (UC1/UC2) o Cierre (UC3/UC4): abre la Vista Contable --}}
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
            <button type="button" class="btn btn-outline btn-sm btn-timeline" data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Línea de tiempo">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </button>
        </td>
    </tr>
@endforeach