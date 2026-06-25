@php
    $files = $getState() ?? [];
@endphp

<div class="space-y-4" x-data="vouchersTable()">
    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Documento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Monto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Archivo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 uppercase">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($files as $index => $file)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $file['type_file'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $file['document_number'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $file['amount'] ? number_format($file['amount'], 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $file['emission_date'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-blue-600">
                            @if($file['path'] ?? false)
                                <a href="{{ \App\Support\FileStorage::url($file['path']) }}" target="_blank" class="underline">
                                    Ver
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center text-sm">
                            <button
                                type="button"
                                @click="deleteRow({{ $index }})"
                                class="text-red-600 hover:text-red-900 text-lg"
                                title="Eliminar"
                            >
                                🗑️
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                            No hay comprobantes agregados
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Botón Agregar --}}
    <div class="flex justify-end">
        <button
            type="button"
            @click="openModal()"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
        >
            + Agregar comprobante
        </button>
    </div>
</div>

<script>
function vouchersTable() {
    return {
        openModal() {
            console.log('Abrir modal');
        },
        deleteRow(index) {
            if (confirm('¿Eliminar este comprobante?')) {
                console.log('Eliminar:', index);
            }
        }
    }
}
</script>