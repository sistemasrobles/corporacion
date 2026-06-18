@php
    // Color del badge por estado del requerimiento
    $statusClass = fn (int $s) => match ($s) {
        2, 7, 9, 10 => 'status-green',
        1, 4, 8     => 'status-yellow',
        3           => 'status-red',
        default     => 'status-blue',   // 0, 5, 6
    };
    $u = auth()->user();
@endphp
@foreach ($refunds as $r)
    @php
        $cur = ($r->currency === 'USD') ? '$ ' : 'S/ ';
        $s = (int) $r->status; $ut = $u->user_type; $isResp = $r->responsible_id === $u->id;
        $diff          = (float) $r->difference_amount;
        $hasDevolucion = $r->payments->contains('payment_type', 'DEVOLUCION_EMPRESA');
        $abonoPay      = $r->payments->firstWhere('payment_type', 'ABONO_INICIAL');   // origen para devolver
        $saldado       = $diff <= 0 || $hasDevolucion;   // liquidación lista para el conforme del AF
        // Acciones disponibles en la lista según rol + estado.
        $acts = [
            'edit'       => ($ut === 'AA' && $isResp && in_array($s, [0, 4], true)) || ($ut === 'GA' && $s === 1),
            'review'     => $ut === 'GA' && $s === 1,                       // aprobar / observar / rechazar
            'abono'      => $ut === 'GF' && $s === 2,
            'constancia' => in_array($ut, ['GF', 'AF'], true) && $s === 5,
            'rendir'     => $ut === 'AA' && $isResp && $s === 6,
            // AA registra la devolución del sobrante solo si aún no la registró.
            'devolucion' => $ut === 'AA' && $isResp && $s === 7 && $diff > 0 && !$hasDevolucion,
            'rendObs'    => $ut === 'AF' && $s === 7,
            // AF da conforme solo cuando la liquidación está saldada (cuadra o ya hay devolución).
            'conforme'   => $ut === 'AF' && $s === 7 && $saldado,
            'reembolso'  => $ut === 'GF' && $s === 8,
            'uc1Observe' => $ut === 'UC1' && $s === 9,
            'cerrar'     => $ut === 'UC1' && $s === 9,
        ];
        $hasActions = in_array(true, $acts, true);
        $benName = $r->beneficiary_name ?? ($r->beneficiary->name ?? '—');
        $benData = 'data-benef="' . e($benName) . '" data-bank="' . e($r->beneficiary_bank) . '" data-account="' . e($r->beneficiary_account) . '"';
    @endphp
    <tr data-status="{{ $r->status }}">
        <td class="cell-mono"><a href="{{ route('requirements.show', $r) }}" class="link">{{ $r->code }}</a></td>
        <td class="cell-strong">{{ \Illuminate\Support\Str::limit($r->title, 35) }}</td>
        <td>{{ $r->company->name ?? '—' }}</td>
        <td>{{ $benName }}</td>
        <td class="cell-strong" data-order="{{ $r->requested_amount }}">{{ $cur . number_format($r->requested_amount, 2) }}</td>
        <td data-order="{{ $r->status }}">
            <span class="status {{ $statusClass($s) }}">{{ $r->statusInfo->name ?? $r->status }}</span>
        </td>
        <td data-order="{{ $r->created_at?->timestamp }}">{{ $r->created_at?->format('d/m/Y') }}</td>
        <td style="text-align:center;white-space:nowrap">
            @if ($acts['edit'])
                <a href="{{ route('requirements.edit', $r) }}" class="btn btn-outline btn-sm" title="Editar" style="color:var(--primary)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            @endif
            @if ($acts['review'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="approve" data-id="{{ $r->id }}" data-code="{{ $r->code }}" title="Aprobar" style="color:var(--green)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6L9 17l-5-5"/></svg>
                </button>
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="observe" data-id="{{ $r->id }}" data-code="{{ $r->code }}" title="Observar" style="color:#b45309">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </button>
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="reject" data-id="{{ $r->id }}" data-code="{{ $r->code }}" title="Rechazar" style="color:var(--red)">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </button>
            @endif
            @if ($acts['abono'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="abono" data-id="{{ $r->id }}" data-code="{{ $r->code }}" data-cur="{{ $r->currency }}" data-amount="{{ $r->approved_amount ?? $r->requested_amount }}" {!! $benData !!} style="color:var(--primary)">Registrar abono</button>
            @endif
            @if ($acts['constancia'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="constancia" data-id="{{ $r->id }}" data-code="{{ $r->code }}" data-txn="{{ optional($r->payments->firstWhere('payment_type', 'ABONO_INICIAL'))->transaction_code }}" style="color:var(--primary)">Adjuntar constancia</button>
            @endif
            @if ($acts['rendir'])
                <a href="{{ route('requirements.rendicion', $r) }}" class="btn btn-outline btn-sm" style="color:var(--primary)">Rendir</a>
            @endif
            @if ($acts['reembolso'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="reembolso" data-id="{{ $r->id }}" data-code="{{ $r->code }}" data-cur="{{ $r->currency }}" data-amount="{{ abs((float) $r->difference_amount) }}" {!! $benData !!} style="color:var(--primary)">Registrar reembolso</button>
            @endif
            @if ($acts['devolucion'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="devolucion" data-id="{{ $r->id }}" data-code="{{ $r->code }}" data-cur="{{ $r->currency }}" data-amount="{{ (float) $r->difference_amount }}" data-obank="{{ optional($abonoPay)->bank_origin }}" data-oaccount="{{ optional($abonoPay)->account_origin }}" style="color:var(--green)">Registrar devolución</button>
            @endif
            @if ($acts['rendObs'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="observe-rend" data-id="{{ $r->id }}" data-code="{{ $r->code }}" title="Observar" style="color:#b45309">Observar</button>
            @endif
            @if ($acts['conforme'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="conforme" data-id="{{ $r->id }}" data-code="{{ $r->code }}" style="color:var(--green)">Dar conforme</button>
            @endif
            @if ($acts['uc1Observe'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="observe-uc1" data-id="{{ $r->id }}" data-code="{{ $r->code }}" title="Observar" style="color:#b45309">Observar</button>
            @endif
            @if ($acts['cerrar'])
                <button type="button" class="btn btn-outline btn-sm act-btn" data-act="cerrar" data-id="{{ $r->id }}" data-code="{{ $r->code }}" style="color:var(--green)">Cerrar</button>
            @endif
            @unless ($hasActions)
                <span style="color:var(--text-muted)">—</span>
            @endunless
        </td>
    </tr>
@endforeach