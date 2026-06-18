@foreach ($abonos as $ab)
    @php
        $o   = $ab->order;
        $cur = ($o->detail?->currency === 'USD') ? '$ ' : 'S/ ';
    @endphp
    <tr
        data-company="{{ $ab->source_company_id }}"
        data-schedule="{{ $o->payment_schedule_id }}"
        data-format="{{ $o->format_id }}"
        data-currency="{{ $o->detail?->currency }}"
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
        <td class="cell-strong">{{ $ab->source_bank ?: '—' }}</td>
        <td class="cell-mono">{{ $ab->source_account_number ?: '—' }}</td>
        <td class="cell-mono">{{ $ab->operation_number ?: '—' }}</td>
        <td>
            @if ($ab->constancia)
                <a href="/storage/{{ $ab->constancia }}" class="btn btn-outline btn-sm constancia-link" data-url="/storage/{{ $ab->constancia }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" style="color:var(--primary)">Ver</a>
            @else
                <span style="color:var(--text-muted)">—</span>
            @endif
        </td>
    </tr>
@endforeach