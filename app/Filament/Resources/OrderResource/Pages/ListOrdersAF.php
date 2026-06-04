<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderQuota;
use Filament\Resources\Pages\Page;

class ListOrdersAF extends Page
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.order-resource.pages.list-orders-af';

    protected static ?string $title = 'Mis Órdenes';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public function mount(): void
    {
        abort_unless(auth()->user()->user_type === 'AF', 403);
    }

    public function getQuotas()
    {
        return OrderQuota::whereHas('order', function ($q) {
            $q->whereHas('paymentSchedule', function ($ps) {
                $ps->where('name', 'URGENTE');
            });
        })->get();
    }

    public function getOrders()
    {
        return Order::where('status', 55)
            ->whereHas('paymentSchedule', function ($q) {
                $q->where('name', 'URGENTE');
            })
            ->get();
    }
}