@php
    $quotas = $this->getQuotas();
    $orders = $this->getOrders();
@endphp

<div class="space-y-6 fi-section">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Mis Órdenes</h1>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex -mb-px" aria-label="Tabs">
            <button
                onclick="toggleTab('cuentas')"
                id="tab-cuentas-btn"
                class="tab-button active py-4 px-6 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                CUENTAS POR PAGAR
            </button>

            <button
                onclick="toggleTab('ordenes')"
                id="tab-ordenes-btn"
                class="tab-button py-4 px-6 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                ÓRDENES DE COMPRA Y SERVICIO
            </button>
        </nav>
    </div>

    <!-- Tab Content: Cuentas por Pagar -->
    <div id="cuentas-tab" class="tab-content">
        <div class="overflow-x-auto">
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

    <!-- Tab Content: Órdenes de Compra y Servicio -->
    <div id="ordenes-tab" class="tab-content hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Código</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Empresa</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Responsable</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Título</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Monto Total</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-900">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($orders as $order)
                        @php
                            $detail = $order->detail;
                            $amount = floatval($detail?->amount_neto ?? 0) > 0
                                ? floatval($detail->amount_neto)
                                : floatval($detail?->amount_ref ?? 0);
                            $cur = ($detail?->currency === 'USD') ? '$ ' : 'S/ ';
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold text-gray-900">
                                <a href="{{ route('filament.admin.resources.orders.view', $order->id) }}" class="text-blue-600 hover:underline">
                                    {{ $order->code }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $order->company->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $order->responsible?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-600 max-w-sm truncate">{{ $order->title }}</td>
                            <td class="px-6 py-4 text-gray-600 font-medium">
                                {{ $cur }}{{ number_format($amount, 2) }}
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('filament.admin.resources.orders.view', $order->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                No hay órdenes pendientes de conforme en este momento
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleTab(tabName) {
    document.getElementById('cuentas-tab').classList.add('hidden');
    document.getElementById('ordenes-tab').classList.add('hidden');

    document.getElementById('tab-cuentas-btn').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('tab-cuentas-btn').classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

    document.getElementById('tab-ordenes-btn').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('tab-ordenes-btn').classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');

    document.getElementById(tabName + '-tab').classList.remove('hidden');

    document.getElementById('tab-' + tabName + '-btn').classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    document.getElementById('tab-' + tabName + '-btn').classList.add('border-blue-500', 'text-blue-600');
}
</script>