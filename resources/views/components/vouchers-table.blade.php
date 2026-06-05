@props(['vouchers' => []])

<div class="space-y-4">
    {{-- Tabla de Comprobantes --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Documento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Monto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Archivo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($vouchers as $index => $voucher)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $voucher['type_file'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $voucher['document_number'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $voucher['amount'] ? number_format($voucher['amount'], 2) : '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $voucher['emission_date'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-blue-600">
                            @if($voucher['path'] ?? false)
                                <a href="#" class="underline">Ver archivo</a>
                            @else
                                <span class="text-gray-400">Sin archivo</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center text-sm">
                            <button class="text-red-600 hover:text-red-900" title="Eliminar">
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
        <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
            + Agregar comprobante
        </button>
    </div>
</div>
