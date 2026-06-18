@php
    // Self-contained: el partial se reusa en la carga inicial y en el endpoint "Recargar".
    $abStatus = [200 => 'Pendiente por depósito', 201 => 'Depositado', 202 => 'Constancia adjuntada'];
    $abClass  = fn (int $s) => match ($s) {
        200 => 'status-yellow',
        201 => 'status-blue',
        202 => 'status-green',
        default => 'status-blue',
    };
@endphp
@foreach ($rows as $row)
    @php
        $o = $row['order']; $ab = $row['abono']; $acts = $row['acts'];
        $cur = ($o->detail?->currency === 'USD') ? '$ ' : 'S/ ';
    @endphp
    <tr
        data-company="{{ $o->company_id }}"
        data-schedule="{{ $o->payment_schedule_id }}"
        data-format="{{ $o->format_id }}"
        data-status="{{ $ab->status }}"
        data-currency="{{ $o->detail?->currency }}"
        data-due="{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('Y-m-d') : '' }}"
    >
        <td class="cell-mono"><a href="#" class="order-link" data-id="{{ $o->id }}" data-code="{{ $o->code }}" style="color:var(--primary);font-weight:600" title="Ver detalle de la orden">{{ $o->code }}</a></td>
        <td><span class="status status-blue">{{ \Illuminate\Support\Str::limit($o->paymentSchedule?->name, 14) }}</span></td>
        <td class="cell-strong">Cuota {{ $ab->quota_number }}</td>
        <td class="cell-strong">{{ $cur . number_format($ab->amount, 2) }}</td>
        <td>{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}</td>
        <td>
            <span class="status {{ $abClass((int) $ab->status) }}">
                {{ $abStatus[$ab->status] ?? $ab->status }}
            </span>
        </td>
        <td class="obs-cell">
            @if ($ab->rebote && $ab->observacion)
                <div style="display:flex;align-items:flex-start;gap:5px;color:#b45309;font-size:12px;line-height:1.4;max-width:240px">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>{{ $ab->observacion }}</span>
                </div>
            @else
                <span style="color:var(--text-muted)">—</span>
            @endif
        </td>
        <td class="cell-strong">{{ $ab->source_bank ?: '—' }}</td>
        <td class="cell-mono">{{ $ab->source_account_number ?: '—' }}</td>
        <td class="cell-mono">{{ $ab->operation_number ?: '—' }}</td>
        <td>
            @if ($ab->constancia)
                <a href="/storage/{{ $ab->constancia }}" class="constancia-link" data-url="/storage/{{ $ab->constancia }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" style="color:var(--primary)">Ver</a>
            @else
                <span style="color:var(--text-muted)">—</span>
            @endif
        </td>
        <td style="text-align:right;white-space:nowrap">
            @if ($acts['deposit'])
                <button type="button" class="btn btn-outline btn-sm ab-deposit" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" data-amount="{{ $cur . number_format($ab->amount, 2) }}" data-due="{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}" data-sched="{{ $o->paymentSchedule?->name }}" title="Registrar depósito" style="color:var(--green)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </button>
            @endif
            @if ($acts['constancia'])
                <button type="button" class="btn btn-outline btn-sm ab-constancia" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" data-amount="{{ $cur . number_format($ab->amount, 2) }}" data-due="{{ $ab->due_date ? \Carbon\Carbon::parse($ab->due_date)->format('d/m/Y') : '—' }}" data-sched="{{ $o->paymentSchedule?->name }}" title="Subir constancia" style="color:var(--primary)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                </button>
            @endif
            @if ($acts['verify'])
                <button type="button" class="btn btn-outline btn-sm ab-verify" data-id="{{ $ab->id }}" title="Marcar conforme" style="color:var(--green)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
                </button>
            @endif
            @if ($acts['observe'])
                <button type="button" class="btn btn-outline btn-sm ab-observe" data-id="{{ $ab->id }}" data-code="{{ $o->code }}" data-num="{{ $ab->quota_number }}" title="Observar abono" style="color:#b45309">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </button>
            @endif
        </td>
    </tr>
@endforeach