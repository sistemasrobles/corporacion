@php
    // Color de badge por estado (self-contained: el partial se reusa en la carga
    // inicial y en el endpoint de "Recargar").
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
        $cur = ($detail?->currency === 'USD') ? '$ ' : 'S/ ';
    @endphp
    <tr
        data-status="{{ $order->status }}"
        data-schedule="{{ $order->payment_schedule_id }}"
        data-format="{{ $order->format_id }}"
        data-responsible="{{ $order->user_responsible }}"
        data-company="{{ $order->company_id }}"
        data-currency="{{ $detail?->currency }}"
        data-date="{{ $order->created_at?->format('Y-m-d') }}"
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
        <td data-order="{{ $order->created_at?->timestamp }}">{{ $order->created_at?->format('d/m/Y') }}</td>
        <td class="cell-strong" data-order="{{ $amount }}">{{ $amount > 0 ? $cur . number_format($amount, 2) : '—' }}</td>
        <td style="text-align:right;white-space:nowrap">
            <button type="button" class="btn btn-outline btn-sm btn-timeline"
                    data-id="{{ $order->id }}" data-code="{{ $order->code }}" title="Línea de tiempo">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </button>
            <a href="{{ route('orders.show', ['order' => $order->id, 'from' => 'history']) }}" class="btn btn-outline btn-sm">Ver</a>
        </td>
    </tr>
@endforeach