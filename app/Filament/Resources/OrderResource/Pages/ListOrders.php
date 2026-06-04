<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Redirect;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function mount(): void
    {
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if (!in_array($user->user_type, ['JA', 'AA', 'GA'])) {
            return [];
        }

        return [
            Actions\CreateAction::make()->label('Nueva Orden'),
        ];
    }
}
