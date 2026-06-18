@foreach ($suppliers as $s)
    <tr data-active="{{ $s->active ? 1 : 0 }}">
        <td class="cell-mono">{{ $s->ruc }}</td>
        <td class="cell-strong">{{ $s->name }}</td>
        @php
            // Número para WhatsApp: solo dígitos. Móvil Perú (9 díg.) → anteponer 51; si ya trae código país (10+), tal cual.
            $waDigits = preg_replace('/\D+/', '', (string) $s->phone);
            $wa = strlen($waDigits) === 9 ? '51' . $waDigits : (strlen($waDigits) >= 10 ? $waDigits : null);
        @endphp
        <td class="cell-mono">
            @if ($wa)
                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600" title="Escribir por WhatsApp">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="vertical-align:-2px;margin-right:3px"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.255zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>{{ $s->phone }}
                </a>
            @else
                {{ $s->phone ?: '—' }}
            @endif
        </td>
        <td>{{ $s->email ?: '—' }}</td>
        <td>{{ $s->address ?: '—' }}</td>
        <td>
            @if ($s->active)
                <span class="chip chip-green">Activo</span>
            @else
                <span class="chip chip-red">Inactivo</span>
            @endif
        </td>
        <td style="text-align:right;white-space:nowrap">
            <button type="button" class="btn btn-outline btn-sm sup-accounts" data-id="{{ $s->id }}" data-name="{{ $s->name }}" title="Ver cuentas" style="color:var(--green)">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 7H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2v-1"/><path d="M21 7V5a2 2 0 00-2-2H6a3 3 0 00-3 3"/><circle cx="17" cy="13" r="1.2"/></svg>
            </button>
            <button type="button" class="btn btn-outline btn-sm sup-edit" data-id="{{ $s->id }}" title="Editar proveedor" style="color:var(--primary)">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button type="button" class="btn btn-outline btn-sm sup-toggle" data-id="{{ $s->id }}" data-active="{{ $s->active ? 1 : 0 }}" data-name="{{ $s->name }}" title="{{ $s->active ? 'Inactivar proveedor' : 'Activar proveedor' }}" style="color:{{ $s->active ? 'var(--red)' : 'var(--green)' }}">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18.36 6.64a9 9 0 11-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            </button>
        </td>
    </tr>
@endforeach