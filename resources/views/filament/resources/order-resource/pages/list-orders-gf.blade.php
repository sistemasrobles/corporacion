@php
    $quotas = $this->getQuotas();
@endphp

<div class="space-y-6 fi-section">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Mis Órdenes</h1>
    </div>

    <div class="fi-section-header">
        <h2 class="text-xl font-bold">CUENTAS POR PAGAR</h2>
        <p class="text-sm text-gray-600 mt-1">Programación: GENERAL</p>
    </div>

    <div class="fi-section-content">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Código Orden</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Empresa</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Cuota #</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Monto</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Fecha Vencimiento</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-900">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($quotas as $quota)
                    @php
                        $order = $quota->order;
                        $statusBg = match($quota->status) {
                            200 => 'bg-yellow-100 text-yellow-800',
                            201 => 'bg-blue-100 text-blue-800',
                            202 => 'bg-green-100 text-green-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                        $statusLabel = match($quota->status) {
                            200 => 'PENDIENTE',
                            201 => 'DEPOSITADO',
                            202 => 'CONFIRMADO',
                            default => 'OTRO'
                        };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-bold text-gray-900">
                            <a href="{{ route('filament.admin.resources.orders.view', $order->id) }}" class="text-blue-600 hover:underline">
                                {{ $order->code }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $order->company->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $quota->quota_number }}</td>
                        <td class="px-6 py-4 text-gray-600">
                            @php
                                $cur = ($order->detail?->currency === 'USD') ? '$ ' : 'S/ ';
                            @endphp
                            {{ $cur }}{{ number_format($quota->amount, 2) }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $quota->due_date->format('d/m/Y') }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium {{ $statusBg }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            No hay cuotas para pagar en este momento
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>