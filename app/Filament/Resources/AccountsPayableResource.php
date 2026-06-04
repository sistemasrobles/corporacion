<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountsPayableResource\Pages;
use App\Models\OrderQuota;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsPayableResource extends Resource
{
    protected static ?string $model = OrderQuota::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Cuentas';

    protected static ?string $modelLabel = 'Cuota';

    protected static ?string $pluralModelLabel = 'Cuentas por Pagar';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with(['order.company', 'order.detail', 'order.paymentSchedule']);

        if ($user->user_type === 'GF') {
            // GF ve AMBAS programaciones
            $query->whereHas('order', function ($q) {
                $q->whereHas('paymentSchedule', function ($ps) {
                    $ps->whereIn('name', ['GENERAL', 'URGENTE']);
                });
            });
        } elseif ($user->user_type === 'AF') {
            // AF solo ve URGENTE
            $query->whereHas('order', function ($q) {
                $q->whereHas('paymentSchedule', function ($ps) {
                    $ps->where('name', 'URGENTE');
                });
            });
        } else {
            $query->where('id', 0);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.code')
                    ->label('Código Orden')->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('order.company.name')
                    ->label('Empresa')->sortable()->limit(25),

                Tables\Columns\TextColumn::make('quota_number')
                    ->label('Cuota #')->sortable()->alignment('center'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->getStateUsing(fn ($record) =>
                        (($record->order->detail?->currency === 'USD') ? '$ ' : 'S/ ') .
                        number_format($record->amount, 2)
                    ),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Fecha Vencimiento')->date('d/m/Y')->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => match($state) {
                        200 => 'PENDIENTE',
                        201 => 'DEPOSITADO',
                        202 => 'CONFIRMADO',
                        default => 'OTRO'
                    })
                    ->color(fn ($state) => match($state) {
                        200 => 'warning',
                        201 => 'info',
                        202 => 'success',
                        default => 'gray'
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return in_array($user->user_type, ['GF', 'AF']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountsPayable::route('/'),
        ];
    }
}