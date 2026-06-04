<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderQuota;
use Filament\Resources\Pages\Page;

class ListOrdersGF extends Page
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.order-resource.pages.list-orders-gf';

    protected static ?string $title = 'Mis Órdenes';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public function mount(): void
    {
        abort_unless(auth()->user()->user_type === 'GF', 403);
    }

    public function getQuotas()
    {
        return OrderQuota::whereHas('order', function ($q) {
            $q->whereHas('paymentSchedule', function ($ps) {
                $ps->where('name', 'GENERAL');
            });
        })->get();
    }
}