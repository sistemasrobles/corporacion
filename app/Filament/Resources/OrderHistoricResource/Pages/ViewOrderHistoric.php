<?php

namespace App\Filament\Resources\OrderHistoricResource\Pages;

use App\Filament\Resources\OrderHistoricResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrderHistoric extends ViewRecord
{
    protected static string $resource = OrderHistoricResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Información General')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('code')
                        ->label('Código'),

                    Infolists\Components\TextEntry::make('title')
                        ->label('Título')
                        ->columnSpan(2),

                    Infolists\Components\TextEntry::make('company.name')
                        ->label('Empresa'),

                    Infolists\Components\TextEntry::make('detail.area.name')
                        ->label('Área'),

                    Infolists\Components\TextEntry::make('paymentSchedule.name')
                        ->label('Programación')
                        ->badge()
                        ->color(fn ($state) => match($state) {
                            'GENERAL' => 'info',
                            'URGENTE' => 'danger',
                            default => 'gray',
                        }),
                ]),

            Infolists\Components\Section::make('Estado Actual')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado')
                        ->formatStateUsing(function ($state) {
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
                            return $statuses[$state] ?? "ESTADO_{$state}";
                        })
                        ->badge()
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
                ]),

            Infolists\Components\Section::make('Detalles de Orden')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('detail.supplier.name')
                        ->label('Proveedor'),

                    Infolists\Components\TextEntry::make('detail.category.name')
                        ->label('Categoría'),

                    Infolists\Components\TextEntry::make('detail.amount_neto')
                        ->label('Monto Total')
                        ->formatStateUsing(function ($state, $record) {
                            $currency = $record->detail?->currency === 'USD' ? '$ ' : 'S/ ';
                            return $currency . number_format($state, 2);
                        }),

                    Infolists\Components\TextEntry::make('detail.required_date')
                        ->label('Fecha Requerida')
                        ->dateTime('d/m/Y'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Creado')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('updated_at')
                        ->label('Actualizado')
                        ->dateTime('d/m/Y H:i'),
                ]),

            Infolists\Components\Section::make('Ciclo de Abonos')
                ->columns(4)
                ->collapsed(true)
                ->collapsible(true)
                ->schema([
                    Infolists\Components\RepeatableEntry::make('quotas')
                        ->columns(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('quota_number')
                                ->label('Cuota #'),

                            Infolists\Components\TextEntry::make('amount')
                                ->label('Monto')
                                ->formatStateUsing(function ($state, $record) {
                                    $currency = $record->order?->detail?->currency === 'USD' ? '$ ' : 'S/ ';
                                    return $currency . number_format($state, 2);
                                }),

                            Infolists\Components\TextEntry::make('due_date')
                                ->label('Vencimiento')
                                ->dateTime('d/m/Y'),

                            Infolists\Components\BadgeEntry::make('status')
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
                        ]),
                ]),
        ]);
    }
}