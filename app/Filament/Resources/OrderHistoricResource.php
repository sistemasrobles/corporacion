<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderHistoricResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderHistoricResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Órdenes';

    protected static ?string $modelLabel = 'Orden Histórica';

    protected static ?string $pluralModelLabel = 'Órdenes Históricas';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // Sin filtro de políticas - muestra TODAS las órdenes a todos los usuarios
        return parent::getEloquentQuery()
            ->with(['company', 'detail', 'paymentSchedule'])
            ->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(40)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->limit(25)
                    ->sortable(),

                Tables\Columns\TextColumn::make('detail.area.name')
                    ->label('Área')
                    ->limit(20),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado Actual')
                    ->getStateUsing(function ($record) {
                        $statuses = [
                            0 => 'BORRADOR',
                            1 => 'CREADO',
                            2 => 'POR_REVISAR',
                            3 => 'APROBADO',
                            4 => 'RECHAZADO',
                            5 => 'OBSERVADO',
                            8 => 'SUSTENTADO',
                            9 => 'CONFORME',
                            10 => 'CERRADO',
                            55 => 'ABONOS_COMPLETADOS',
                            91 => 'CODIGO_DE_REGISTRO',
                            92 => 'CODIGO_DE_BANCO',
                            100 => 'ACEPTADO',
                            101 => 'CONFIRMADO',
                            102 => 'CRONOGRAMA_DE_PAGO',
                        ];
                        return $statuses[$record->status] ?? "ESTADO_{$record->status}";
                    })
                    ->color(fn ($state) => match($state) {
                        'BORRADOR' => 'gray',
                        'CREADO', 'POR_REVISAR' => 'warning',
                        'APROBADO', 'ACEPTADO', 'CONFORME', 'CONFIRMADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'OBSERVADO' => 'info',
                        'CERRADO' => 'gray',
                        'CRONOGRAMA_DE_PAGO', 'ABONOS_COMPLETADOS' => 'success',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('paymentSchedule.name')
                    ->label('Programación')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'GENERAL' => 'info',
                        'URGENTE' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('detail.amount_neto')
                    ->label('Monto Total')
                    ->getStateUsing(function ($record) {
                        $currency = $record->detail?->currency === 'USD' ? '$ ' : 'S/ ';
                        return $currency . number_format($record->detail?->amount_neto ?? 0, 2);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        0 => 'BORRADOR',
                        1 => 'CREADO',
                        2 => 'POR_REVISAR',
                        3 => 'APROBADO',
                        4 => 'RECHAZADO',
                        5 => 'OBSERVADO',
                        8 => 'SUSTENTADO',
                        9 => 'CONFORME',
                        10 => 'CERRADO',
                        55 => 'ABONOS_COMPLETADOS',
                        91 => 'CODIGO_DE_REGISTRO',
                        92 => 'CODIGO_DE_BANCO',
                        100 => 'ACEPTADO',
                        101 => 'CONFIRMADO',
                        102 => 'CRONOGRAMA_DE_PAGO',
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $data['value'] ? $query->where('status', $data['value']) : $query
                    ),

                Tables\Filters\SelectFilter::make('programacion')
                    ->label('Programación')
                    ->relationship('paymentSchedule', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->multiple(),

                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha de creación')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([50, 100, 200]);
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
            'index' => Pages\ListOrderHistoric::route('/'),
            'view' => Pages\ViewOrderHistoric::route('/{record}'),
        ];
    }
}