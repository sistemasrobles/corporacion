<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\HasOrderForm;
use App\Models\CostCenter;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderQuota;
use App\Models\PaymentSchedule;
use App\Models\Status;
use App\Models\SupplierAccount;
use App\Models\UserType;
use Filament\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    use HasOrderForm;

    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Orden ' . $this->record->code;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $order  = $this->record;
        $date   = $order->created_at->format('d/m/Y');
        $creator = \App\Models\User::find($order->created_by)?->name ?? '—';

        [$statusLabel, $statusColor] = $this->getStatusMeta((int) $order->status);

        $colorMap = [
            'warning' => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d'],
            'success' => ['bg' => '#dcfce7', 'text' => '#166534', 'border' => '#86efac'],
            'danger'  => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5'],
            'info'    => ['bg' => '#dbeafe', 'text' => '#1e40af', 'border' => '#93c5fd'],
            'gray'    => ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#d1d5db'],
            'primary' => ['bg' => '#ede9fe', 'text' => '#4c1d95', 'border' => '#c4b5fd'],
        ];
        $c = $colorMap[$statusColor] ?? $colorMap['gray'];

        $pill = '<span style="display:inline-flex;align-items:center;padding:2px 10px;border-radius:9999px;'
            . 'font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;'
            . 'background:' . $c['bg'] . ';color:' . $c['text'] . ';border:1px solid ' . $c['border'] . ';'
            . 'margin-left:0.6rem;vertical-align:middle;">'
            . e($statusLabel) . '</span>';

        return new HtmlString(
            '<span style="color:#6b7280;font-size:0.875rem">'
            . 'Solicitada el ' . $date . ' &nbsp;·&nbsp; Solicitante: ' . e($creator)
            . '</span>'
            . $pill
        );
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $order       = $this->record->load(['company', 'detail.supplier', 'detail.area', 'detail.category', 'files', 'products', 'quotas']);
        $attachTypes = Master::where('main', 18)->orderBy('description')->get();
        $status      = (int) $order->status;

        return $infolist->record($order)->schema([

            // ── 1. Datos generales ────────────────────────────────────────
            Section::make('1. Datos generales')
                ->compact()
                ->description('Empresa, tipo, moneda y clasificación')
                ->schema([
                    Grid::make(9)->schema([
                        TextEntry::make('company.name')
                            ->label('Empresa')->columnSpan(2),

                        TextEntry::make('format_id')
                            ->label('Tipo de Orden')
                            ->badge()->color('gray')->columnSpan(2),

                        TextEntry::make('detail.category.description')
                            ->label('Categoría')->default('—')->columnSpan(3),

                        TextEntry::make('detail.currency')
                            ->label('Moneda')
                            ->badge()->color('gray')->columnSpan(2),
                    ]),

                    Grid::make(5)->schema([
                        TextEntry::make('title')
                            ->label('Título / Asunto')->columnSpan(2),

                        TextEntry::make('_monto_ref')
                            ->label('Monto Referencial')
                            ->getStateUsing(function ($record) {
                                $amount = floatval($record->detail?->amount_ref ?? 0);
                                if ($amount == 0) return '—';
                                $cur = ($record->detail?->currency === 'USD') ? '$ ' : 'S/ ';
                                return $cur . number_format($amount, 2);
                            }),

                        TextEntry::make('detail.area.description')
                            ->label('Área')->default('—'),

                        TextEntry::make('_cc_ids')
                            ->label('Centros de Costo')
                            ->getStateUsing(fn ($record) =>
                                collect($record->detail?->cc_id ?? [])
                                    ->map(fn ($id) => CostCenter::find($id)?->description ?? $id)
                                    ->join(' · ') ?: '—'),
                    ]),
                ]),

            // ── 2. Proveedor y condición de pago ─────────────────────────
            Section::make('2. Proveedor y condición de pago')
                ->compact()
                ->description('RUC, cuenta destino y términos comerciales')
                ->schema([
                    Section::make('Proveedor')->compact()->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('detail.supplier.ruc')
                                ->label('RUC')->default('—'),

                            TextEntry::make('detail.supplier.name')
                                ->label('Razón social')->default('—')->columnSpan(2),
                        ]),

                        Grid::make(4)->schema([
                            TextEntry::make('detail.supplier.address')
                                ->label('Domicilio fiscal')->default('—'),
                            TextEntry::make('detail.supplier.district')
                                ->label('Distrito')->default('—'),
                            TextEntry::make('detail.supplier.contact')
                                ->label('Contacto')->default('—'),
                            TextEntry::make('detail.supplier.email')
                                ->label('Correo')->default('—'),
                        ]),

                        TextEntry::make('_account')
                            ->label('Cuenta bancaria destino')
                            ->html()
                            ->getStateUsing(function ($record) {
                                $acc = SupplierAccount::find($record->detail?->supplier_account_id);
                                if (!$acc) return '<span style="color:#94a3b8">—</span>';
                                return '<span style="font-weight:600;color:#1e293b">' . e($acc->bank) . '</span>'
                                    . '&nbsp;<span style="background:#dbeafe;color:#1d4ed8;font-size:11px;padding:1px 7px;border-radius:4px;font-weight:700">' . e($acc->currency) . '</span>'
                                    . '&nbsp;&nbsp;<span style="color:#64748b;font-size:0.85rem">N° ' . e($acc->account_number)
                                    . ($acc->cci ? ' · CCI ' . e($acc->cci) : '') . '</span>';
                            }),
                    ]),

                    Section::make('Condición de pago')->compact()->schema([
                        Grid::make(9)->schema([
                            TextEntry::make('_payment')
                                ->label('Forma de pago')
                                ->getStateUsing(fn ($record) => Master::find($record->detail?->payment_id)?->description ?? '—')
                                ->columnSpan(2),

                            TextEntry::make('_condition')
                                ->label('Condición')
                                ->getStateUsing(fn ($record) => Master::find($record->detail?->condition_payment)?->description ?? '—')
                                ->columnSpan(2),

                            TextEntry::make('detail.quotas')
                                ->label('N° de cuotas')->default('—')->columnSpan(1),

                            TextEntry::make('detail.expiration_date')
                                ->label('Fecha de vencimiento')
                                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : '—')
                                ->columnSpan(2),

                            TextEntry::make('_schedule')
                                ->label('Programación')
                                ->getStateUsing(fn ($record) => PaymentSchedule::find($record->payment_schedule_id)?->name ?? '—')
                                ->columnSpan(2),
                        ]),
                    ]),
                ]),

            // ── 3. Detalle de la orden ────────────────────────────────────
            Section::make('3. Detalle de la orden')
                ->compact()
                ->description('Productos / servicios y configuración tributaria')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('_grabable')
                            ->label('IGV')
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->detail?->grabable ? 'Aplica IGV (18%)' : 'Sin IGV')
                            ->color(fn ($record) => $record->detail?->grabable ? 'warning' : 'gray'),

                        TextEntry::make('_discount')
                            ->label('Descuento')
                            ->getStateUsing(fn ($record) =>
                                floatval($record->detail?->discount ?? 0) > 0
                                    ? ($record->detail->currency === 'USD' ? '$ ' : 'S/ ') . number_format(floatval($record->detail->discount), 2)
                                    : '—'),

                        TextEntry::make('_amount_neto')
                            ->label('Monto neto')
                            ->getStateUsing(fn ($record) =>
                                $record->detail?->amount_neto
                                    ? ($record->detail->currency === 'USD' ? '$ ' : 'S/ ') . number_format(floatval($record->detail->amount_neto), 2)
                                    : '—'),
                    ]),

                    RepeatableEntry::make('products')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('description')->label('Descripción / Servicio')->columnSpan(2),
                            TextEntry::make('quantity')->label('Cant.'),
                            TextEntry::make('unit_price')->label('P. Unitario')
                                ->formatStateUsing(fn ($state) => number_format(floatval($state), 2)),
                            TextEntry::make('sub_total')->label('Subtotal')
                                ->formatStateUsing(fn ($state) => number_format(floatval($state), 2)),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),

                    TextEntry::make('detail.observation')
                        ->label('Observaciones')->default('—')->columnSpanFull(),

                    TextEntry::make('_totals')
                        ->hiddenLabel()->columnSpanFull()->html()
                        ->getStateUsing(fn ($record) => $this->buildTotalsFromRecord($record)),
                ]),

            // ── 4. Documentos (collapsible) ───────────────────────────────
            Section::make('4. Documentos')
                ->compact()
                ->collapsible()->collapsed()
                ->description('Comprobante de pago y documentos anexos')
                ->schema([
                    Section::make('Comprobante de pago')->compact()->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('_voucher_type')
                                ->label('Tipo de comprobante')
                                ->getStateUsing(fn ($record) =>
                                    Master::where('main', 15)
                                        ->where('value', $record->files->firstWhere('principal', 1)?->type_file)
                                        ->value('description') ?? '—'),

                            TextEntry::make('_voucher_file')
                                ->label('Archivo del comprobante')
                                ->html()
                                ->getStateUsing(function ($record) {
                                    $file = $record->files->firstWhere('principal', 1);
                                    if (!$file?->path) return '<span style="color:#94a3b8">Sin archivo adjunto</span>';
                                    $url = Storage::url($file->path);
                                    return '<a href="' . e($url) . '" target="_blank" style="color:#4f46e5;text-decoration:underline">'
                                        . e(basename($file->path)) . ' ↗</a>';
                                }),
                        ]),
                    ]),

                    Section::make('Documentos anexos')->compact()->schema([
                        Grid::make(2)->schema(
                            $attachTypes->map(fn ($m) =>
                                TextEntry::make('_doc_' . $m->value)
                                    ->label($m->description)
                                    ->html()
                                    ->getStateUsing(function ($record) use ($m) {
                                        $file = $record->files->where('principal', 0)->where('type_file', $m->value)->first();
                                        if (!$file?->path) return '<span style="color:#94a3b8">Sin archivo adjunto</span>';
                                        $url = Storage::url($file->path);
                                        return '<a href="' . e($url) . '" target="_blank" style="color:#4f46e5;text-decoration:underline">'
                                            . e(basename($file->path)) . ' ↗</a>';
                                    })
                            )->all()
                        ),
                    ]),
                ]),

            // ── 5. Constancia de Abono (collapsible) ─────────────────────
            Section::make('5. Constancia de Abono')
                ->compact()
                ->collapsible()->collapsed()
                ->description('Comprobante de transferencia o depósito bancario')
                ->schema([
                    TextEntry::make('_constancia')
                        ->label('Constancia de abono')
                        ->html()
                        ->getStateUsing(function ($record) {
                            $file = $record->files->where('type_file', 8)->first();
                            if (!$file?->path) return '<span style="color:#94a3b8;font-style:italic">Pendiente de adjuntar</span>';
                            $url = Storage::url($file->path);
                            return '<a href="' . e($url) . '" target="_blank" style="color:#4f46e5;text-decoration:underline">'
                                . e(basename($file->path)) . ' ↗</a>';
                        }),
                ]),

            // ── 6. Registro Contable (solo visible desde UC / cierre) ─────
            // Sección de Abonos (solo si hay cuotas)
            Section::make('Ciclo de Abonos')
                ->compact()
                ->collapsible()->collapsed()
                ->description('Cuotas y estado de depósitos')
                ->hidden($record->quotas()->count() === 0)
                ->schema([
                    \Filament\Infolists\Components\RepeatableEntry::make('quotas')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('quota_number')->label('Cuota'),
                            TextEntry::make('amount')->label('Monto')->formatStateUsing(fn ($state) => 'S/ ' . number_format($state, 2)),
                            TextEntry::make('due_date')->label('Vencimiento')->date('d/m/Y'),
                            TextEntry::make('status')->label('Estado')->badge()
                                ->formatStateUsing(fn ($state) => Status::label($state) ?? 'Desconocido')
                                ->color(fn ($state) => match($state) {
                                    200 => 'warning',
                                    201 => 'info',
                                    202 => 'success',
                                    default => 'gray',
                                }),
                        ])
                        ->columns(4),
                ]),

            Section::make('6. Registro Contable')
                ->compact()
                ->collapsible()->collapsed()
                ->description('Códigos ingresados por el área de Contabilidad')
                ->hidden(!in_array($status, [9, 91, 92, 10]))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('detail.codigo_registro')
                            ->label('Código de Registro')->default('—'),
                        TextEntry::make('detail.codigo_banco')
                            ->label('Código de Banco')->default('—'),
                    ]),
                ]),
        ]);
    }

    private function buildTotalsFromRecord(Order $record): string
    {
        $detail   = $record->detail;
        $subtotal = $record->products->sum('sub_total');
        $igv      = ($detail?->grabable) ? round($subtotal * 0.18, 2) : 0;
        $total    = round($subtotal + $igv, 2);
        $discount = floatval($detail?->discount ?? 0);
        $amtNeto  = round($total - $discount, 2);
        $cur      = ($detail?->currency === 'USD') ? '$' : 'S/';

        $html  = '<div style="display:flex;align-items:center;justify-content:flex-end;gap:24px;padding:12px 16px;';
        $html .= 'background:#F8FAFC;border:1px solid #E2E8EF;border-radius:8px;margin-top:4px;flex-wrap:wrap;">';
        $html .= $this->totItem('Subtotal', "{$cur} " . number_format($subtotal, 2));
        if ($detail?->grabable) {
            $html .= $this->totItem('IGV (18%)', "{$cur} " . number_format($igv, 2));
        }
        if ($discount > 0) {
            $html .= $this->totItem('Descuento', "− {$cur} " . number_format($discount, 2), '#C0392B');
            $html .= $this->totItem('Neto c/ desc.', "{$cur} " . number_format($amtNeto, 2), '#1a6b3c');
        }
        $mainVal = $discount > 0 ? $amtNeto : $total;
        $html .= '<div style="padding-left:20px;border-left:1px solid #E2E8EF;">';
        $html .= '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#94A3B8;font-weight:600;">Total</div>';
        $html .= '<div style="font-size:20px;font-weight:700;color:#0F172A;">' . "{$cur} " . number_format($mainVal, 2) . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    protected function getHeaderActions(): array
    {
        $order = $this->record;
        $user = auth()->user();
        $actions = [];

        // Acción: Generar Cronograma de Pago (estado 102)
        $canGenerateCronograma = in_array($user->user_type, ['GF', 'UC5'])
            && in_array($order->status, [3, 101]); // Status 3 (URGENTE) o 101 (GENERAL)

        if ($canGenerateCronograma) {
            $actions[] = Action::make('generar_cronograma')
                ->label('Generar Cronograma de Pago')
                ->icon('heroicon-o-calendar')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Grid::make(2)->schema([
                        \Filament\Forms\Components\TextInput::make('num_cuotas')
                            ->label('Número de cuotas')
                            ->type('number')
                            ->min(1)
                            ->max(24)
                            ->default(1)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                    ]),
                    \Filament\Forms\Components\Repeater::make('cuotas')
                        ->label('Cuotas')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('quota_number')
                                ->label('Cuota #')
                                ->disabled()
                                ->columnSpan(1),
                            \Filament\Forms\Components\DatePicker::make('due_date')
                                ->label('Fecha de vencimiento')
                                ->required()
                                ->columnSpan(2),
                            \Filament\Forms\Components\TextInput::make('amount')
                                ->label('Monto')
                                ->numeric()
                                ->prefix('S/ ')
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->live(),
                ])
                ->modalHeading('Configurar Cronograma de Pago')
                ->modalWidth('3xl')
                ->action(fn (array $data) => $this->guardarCronograma($data, $order));
        }

        // Acción: Ejecutar Abonos (GF)
        if ($user->user_type === 'GF' && $order->status === 102) {
            $quotasPendientes = OrderQuota::where('order_id', $order->id)
                ->where('status', 200)
                ->count();

            if ($quotasPendientes > 0) {
                $actions[] = Action::make('ejecutar_abonos')
                    ->label('Ejecutar Abonos')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar ejecución de abonos')
                    ->modalDescription("Se marcarán {$quotasPendientes} cuota(s) como depositadas")
                    ->action(function () use ($order, $user) {
                        OrderQuota::where('order_id', $order->id)
                            ->where('status', 200)
                            ->update([
                                'status' => 201, // DEPOSITADO
                                'updated_by' => $user->id,
                            ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Abonos ejecutados')
                            ->success()
                            ->send();

                        $this->dispatch('refresh');
                    });
            }
        }

        // Acción: Subir Voucher/Verificar (AF en URGENTE, UC2 en GENERAL)
        $quotasParaVoucher = OrderQuota::where('order_id', $order->id)
            ->where('status', 201)
            ->count();

        if (($user->user_type === 'AF' && $order->status === 102) && $quotasParaVoucher > 0) {
            $actions[] = Action::make('subir_voucher')
                ->label('Subir Comprobante de Abono')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('voucher')
                        ->label('Comprobante bancario')
                        ->required()
                        ->disk('public')
                        ->directory('orders/vouchers'),
                    \Filament\Forms\Components\Select::make('quota_id')
                        ->label('Cuota')
                        ->options(fn () => OrderQuota::where('order_id', $order->id)
                            ->where('status', 201)
                            ->get()
                            ->mapWithKeys(fn ($q) => [$q->id => "Cuota {$q->quota_number}"])
                        )
                        ->required(),
                ])
                ->modalHeading('Subir comprobante de abono')
                ->action(fn (array $data) => $this->subirVoucher($data, $order, $user));
        }

        // Acción: Observar Abono (AA en URGENTE)
        $quotasParaObservar = OrderQuota::where('order_id', $order->id)
            ->where('status', 202)
            ->count();

        if ($user->user_type === 'AA' && $order->status === 102 && $quotasParaObservar > 0) {
            $actions[] = Action::make('observar_abono')
                ->label('Observar Abono')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Select::make('quota_id')
                        ->label('Cuota con problema')
                        ->options(fn () => OrderQuota::where('order_id', $order->id)
                            ->where('status', 202)
                            ->get()
                            ->mapWithKeys(fn ($q) => [$q->id => "Cuota {$q->quota_number} - S/ " . number_format($q->amount, 2)])
                        )
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('motivo')
                        ->label('Motivo de la observación')
                        ->required()
                        ->rows(3)
                        ->placeholder('Ej: Monto no coincide, transferencia fallida, etc.'),
                ])
                ->modalHeading('Reportar problema en abono')
                ->action(fn (array $data) => $this->observarAbono($data, $order, $user));
        }

        // Acción: Subir Comprobante Tributario (AA, estado 55)
        if ($user->user_type === 'AA' && $order->status === 55) {
            $actions[] = Action::make('subir_comprobante_tributario')
                ->label('Subir Comprobante Tributario')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('comprobante')
                        ->label('Factura / Boleta / RxH')
                        ->required()
                        ->disk('public')
                        ->directory('orders/vouchers'),
                ])
                ->modalHeading('Subir comprobante tributario')
                ->action(fn (array $data) => $this->subirComprobanteAA($order, $user));
        }

        // Acción: Validar Documentos (AF, estado 8 URGENTE)
        if ($user->user_type === 'AF' && $order->status === 8) {
            $actions[] = Action::make('validar_documentos')
                ->label('Documentos Conformes')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar validación de documentos')
                ->action(fn () => $this->transicionarEstado($order, 8, 9, 'AF', $user));
        }

        // Acción: Ingresar Código Registro (UC1, estado 9 URGENTE)
        if ($user->user_type === 'UC1' && $order->status === 9) {
            $actions[] = Action::make('codigo_registro')
                ->label('Ingresar Código de Registro')
                ->icon('heroicon-o-tag')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('codigo_registro')
                        ->label('Código de registro')
                        ->required()
                        ->maxLength(50),
                ])
                ->modalHeading('Ingresar código de registro')
                ->action(fn (array $data) => $this->guardarCodigoRegistro($order, $data, $user));
        }

        // Acción: Ingresar Código Banco (UC2, estado 91 URGENTE o 55 GENERAL)
        $esUC2ConEstado91o55 = $user->user_type === 'UC2' && in_array($order->status, [91, 55]);
        if ($esUC2ConEstado91o55) {
            $targetStatus = $order->status === 91 ? 92 : 92;
            $actions[] = Action::make('codigo_banco')
                ->label('Ingresar Código de Banco')
                ->icon('heroicon-o-credit-card')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('codigo_banco')
                        ->label('Código de banco')
                        ->required()
                        ->maxLength(50),
                ])
                ->modalHeading('Ingresar código de banco')
                ->action(fn (array $data) => $this->guardarCodigoBanco($order, $data, $user));
        }

        // Acción: Visto Bueno Final (UC3, estado 92 URGENTE)
        if ($user->user_type === 'UC3' && $order->status === 92) {
            $actions[] = Action::make('visto_bueno_uc3')
                ->label('Visto Bueno Final')
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar visto bueno final')
                ->action(fn () => $this->transicionarEstado($order, 92, 10, 'UC3', $user));
        }

        // Acción: Cierre (UC4, estado 92 GENERAL)
        if ($user->user_type === 'UC4' && $order->status === 92) {
            $actions[] = Action::make('cierre_uc4')
                ->label('Cerrar Orden')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar cierre de orden')
                ->action(fn () => $this->transicionarEstado($order, 92, 10, 'UC4', $user));
        }

        // Acción: Historial
        $actions[] = Action::make('history')
            ->label('Historial')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading('Historial de movimientos')
            ->modalContent(fn () => new HtmlString($this->buildHistoryHtml($order)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth('4xl');

        return $actions;
    }

    private function observarAbono(array $data, Order $order, $user): void
    {
        $quota = OrderQuota::findOrFail($data['quota_id']);

        // Marcar cuota como observada y guardar motivo
        $quota->update([
            'status' => 200, // PENDIENTE_POR_DEPOSITO (vuelve al inicio para que AF reabona)
            'observacion' => $data['motivo'],
            'updated_by' => $user->id,
        ]);

        // Registrar en historial
        OrderHistory::create([
            'order_id' => $order->id,
            'from_status' => 102,
            'to_status' => 5, // OBSERVADO
            'from_user' => 'AF',
            'to_user' => 'AA',
            'coment' => "Cuota {$quota->quota_number} observada: {$data['motivo']}",
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Abono observado')
            ->body("Cuota {$quota->quota_number} marcada para reabono")
            ->warning()
            ->send();

        // Notificar a AF que debe reabono
        \Filament\Notifications\Notification::make()
            ->title('Observación en abono')
            ->body("Cuota {$quota->quota_number} debe ser reabonada: {$data['motivo']}")
            ->warning()
            ->send();

        $this->dispatch('refresh');
    }

    private function subirVoucher(array $data, Order $order, $user): void
    {
        $quota = OrderQuota::findOrFail($data['quota_id']);

        $quota->update([
            'status' => 202, // CONSTANCIA_ADJUNTADA
            'updated_by' => $user->id,
        ]);

        // Verificar si todas las cuotas tienen constancia
        $allComplete = OrderQuota::where('order_id', $order->id)
            ->whereNotIn('status', [202])
            ->count() === 0;

        if ($allComplete) {
            // Marcar orden como ABONOS_COMPLETADOS
            $order->update([
                'status_old' => $order->status,
                'status' => 55,
                'updated_by' => $user->id,
            ]);

            OrderHistory::create([
                'order_id' => $order->id,
                'from_status' => 102,
                'to_status' => 55,
                'from_user' => 'AF',
                'to_user' => 'AF',
                'coment' => 'Todos los abonos completados',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        \Filament\Notifications\Notification::make()
            ->title('Comprobante subido')
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    private function guardarCronograma(array $data, Order $order): void
    {
        $user = auth()->user();

        // Generar cuotas basadas en input
        $numCuotas = (int) $data['num_cuotas'];
        $totalAmount = $order->detail?->amount_neto ?? $order->detail?->amount_ref ?? 0;
        $montoPorCuota = $totalAmount / $numCuotas;

        // Eliminar cuotas existentes
        OrderQuota::where('order_id', $order->id)->delete();

        // Crear cuotas nuevas
        for ($i = 1; $i <= $numCuotas; $i++) {
            $dueDate = isset($data['cuotas'][$i - 1]['due_date'])
                ? $data['cuotas'][$i - 1]['due_date']
                : now()->addDays($i * 30);

            OrderQuota::create([
                'order_id'    => $order->id,
                'quota_number' => $i,
                'amount'      => $montoPorCuota,
                'due_date'    => $dueDate,
                'status'      => 200, // PENDIENTE_POR_DEPOSITO
                'created_by'  => $user->id,
                'updated_by'  => $user->id,
            ]);
        }

        // Actualizar estado de orden a 102
        $order->update([
            'status_old' => $order->status,
            'status'     => 102,
            'updated_by' => $user->id,
        ]);

        // Registrar en historial
        OrderHistory::create([
            'order_id'   => $order->id,
            'from_status' => $order->status_old,
            'to_status'   => 102,
            'from_user'   => 'AA', // o el rol que sea
            'to_user'     => $user->user_type,
            'coment'      => "Cronograma generado con $numCuotas cuotas",
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Cronograma creado')
            ->body("Se crearon $numCuotas cuotas exitosamente")
            ->success()
            ->send();

        $this->redirect(route('filament.admin.resources.orders.view', $order));
    }

    private function subirComprobanteAA(Order $order, $user): void
    {
        // Transición: AA sube comprobante tributario (55 → 8)
        $order->update([
            'status_old' => $order->status,
            'status' => 8, // SUSTENTADO
            'updated_by' => $user->id,
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'from_status' => 55,
            'to_status' => 8,
            'from_user' => 'AF',
            'to_user' => 'AA',
            'coment' => 'Comprobante tributario adjuntado',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Comprobante tributario subido')
            ->body('La orden está en fase de validación de documentos')
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    private function guardarCodigoRegistro(Order $order, array $data, $user): void
    {
        // UC1: Ingresar código de registro (9 → 91)
        $order->update([
            'status_old' => $order->status,
            'status' => 91, // CODIGO_DE_REGISTRO
            'updated_by' => $user->id,
        ]);

        // Guardar código en detail si es necesario (o en una tabla separada)
        if ($order->detail) {
            $order->detail->update([
                'codigo_registro' => $data['codigo_registro'],
            ]);
        }

        OrderHistory::create([
            'order_id' => $order->id,
            'from_status' => 9,
            'to_status' => 91,
            'from_user' => 'AF',
            'to_user' => 'UC1',
            'coment' => "Código de registro: {$data['codigo_registro']}",
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Código de registro guardado')
            ->body("Código: {$data['codigo_registro']}")
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    private function guardarCodigoBanco(Order $order, array $data, $user): void
    {
        // UC2: Ingresar código de banco (91 → 92 en URGENTE, o 55 → 92 en GENERAL)
        $fromStatus = $order->status; // Puede ser 91 o 55

        $order->update([
            'status_old' => $order->status,
            'status' => 92, // CODIGO_DE_BANCO
            'updated_by' => $user->id,
        ]);

        // Guardar código en detail
        if ($order->detail) {
            $order->detail->update([
                'codigo_banco' => $data['codigo_banco'],
            ]);
        }

        OrderHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => 92,
            'from_user' => $fromStatus === 91 ? 'UC1' : 'AA',
            'to_user' => 'UC2',
            'coment' => "Código de banco: {$data['codigo_banco']}",
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Código de banco guardado')
            ->body("Código: {$data['codigo_banco']}")
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    private function transicionarEstado(Order $order, int $fromStatus, int $toStatus, string $fromUser, $user): void
    {
        $order->update([
            'status_old' => $order->status,
            'status' => $toStatus,
            'updated_by' => $user->id,
        ]);

        $statusMessages = [
            8 => 'SUSTENTADO',
            9 => 'CONFORME',
            91 => 'CODIGO_DE_REGISTRO',
            92 => 'CODIGO_DE_BANCO',
            10 => 'CERRADO',
        ];

        $statusMessage = $statusMessages[$toStatus] ?? "Estado $toStatus";

        OrderHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_user' => $fromUser,
            'to_user' => $user->user_type,
            'coment' => "Transición a $statusMessage",
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Estado actualizado')
            ->body($statusMessage)
            ->success()
            ->send();

        $this->dispatch('refresh');
    }

    private function buildHistoryHtml(Order $order): string
    {
        $history = OrderHistory::where('order_id', $order->id)->orderByDesc('id')->get();

        if ($history->isEmpty()) {
            return '<p style="color:#94a3b8;text-align:center;padding:32px 0;font-size:0.9rem;">Sin movimientos registrados.</p>';
        }

        $statusNames = Status::pluck('description', 'id')->toArray();
        $roleNames   = UserType::pluck('description', 'prefijo')->toArray();

        $html  = '<div style="padding:4px 0;overflow-x:auto;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
        $html .= '<thead><tr style="border-bottom:2px solid #e2e8f0;">';
        foreach (['Fecha', 'Usuario', 'Movimiento', 'Comentario'] as $th) {
            $html .= '<th style="padding:8px 12px;font-weight:700;color:#64748b;text-align:left;white-space:nowrap">' . $th . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($history as $i => $h) {
            $bg       = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $isObs    = $h->to_status == 5;
            $fromName = $statusNames[$h->from_status] ?? $h->from_status;
            $toName   = $statusNames[$h->to_status]   ?? $h->to_status;
            $fromRole = $roleNames[$h->from_user]     ?? $h->from_user;
            $userName = \App\Models\User::find($h->created_by)?->name ?? '—';
            $date     = $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i') : '—';
            $comment  = ($h->coment && $h->coment !== '0') ? e($h->coment) : '';

            $fromBg = $isObs ? '#fef3c7' : '#dbeafe';
            $fromFg = $isObs ? '#92400e' : '#1d4ed8';
            $toBg   = $isObs ? '#fee2e2' : '#d1fae5';
            $toFg   = $isObs ? '#991b1b' : '#065f46';
            $commentStyle = $isObs
                ? 'color:#92400e;font-style:italic;background:#fffbeb;padding:3px 8px;border-radius:4px;display:inline-block'
                : 'color:#64748b';

            $html .= "<tr style=\"background:{$bg};border-bottom:1px solid #f1f5f9;\">";
            $html .= "<td style=\"padding:8px 12px;white-space:nowrap;color:#64748b\">{$date}</td>";
            $html .= "<td style=\"padding:8px 12px;\"><span style=\"font-weight:600;color:#1e293b\">" . e($userName) . "</span><br><span style=\"font-size:0.74rem;color:#94a3b8\">{$fromRole}</span></td>";
            $html .= "<td style=\"padding:8px 12px;white-space:nowrap\">"
                   . "<span style=\"background:{$fromBg};color:{$fromFg};font-size:11px;padding:2px 7px;border-radius:4px;font-weight:700\">{$fromName}</span>"
                   . "<span style=\"color:#94a3b8;margin:0 5px\">→</span>"
                   . "<span style=\"background:{$toBg};color:{$toFg};font-size:11px;padding:2px 7px;border-radius:4px;font-weight:700\">{$toName}</span></td>";
            $html .= "<td style=\"padding:8px 12px;\">"
                   . ($comment ? "<span style=\"{$commentStyle}\">{$comment}</span>" : '<span style="color:#cbd5e1">—</span>')
                   . "</td>";
            $html .= "</tr>";
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private function getStatusMeta(int $status): array
    {
        $label = Status::label($status);
        $color = match ($status) {
            0, 10  => 'gray',
            1      => 'info',
            2, 5   => 'warning',
            3, 6, 7, 9 => 'success',
            4      => 'danger',
            8, 91, 92  => 'primary',
            55     => 'warning',
            default => 'gray',
        };
        return [$label, $color];
    }
}