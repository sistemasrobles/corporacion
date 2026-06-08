<?php

namespace App\Filament\Resources\OrderResource\Concerns;

use App\Models\Area;
use App\Models\Category;
use App\Models\Company;
use App\Models\Format;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\PaymentSchedule;
use App\Models\Sede;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\Type;
use App\Models\UserType;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

trait HasOrderForm
{
    protected function aaSchema($user, $monedas, $monedaOpts, int $orderStatus = 1, array $perms = []): array
    {
        $paymentOpts   = $this->getMasterOptions(4);
        $conditionOpts = $this->getMasterOptions(8);
        $discountOpts  = $this->getMasterOptions(12);

        $voucherTypes = Master::where('main', 15)->orderBy('description')->pluck('description', 'value')->toArray();
        $attachTypes  = Master::where('main', 18)->orderBy('description')->get();

        // Section edit permissions (default: all enabled for backward compat with CreateOrder)
        $canEditGeneral    = $perms['general']    ?? true;
        $canEditDocs       = $perms['documents']  ?? true;
        $canEditConstancia = $perms['constancia'] ?? false;
        // s1_only: only Section 1 is editable; Sections 2 and 3 are locked even if general=true
        $canEditSection23  = $canEditGeneral && !($perms['s1_only'] ?? false);

        $ucLevel        = $user->uc_level;
        $isUC           = $user->user_type === 'UC';
        $canEditCodReg  = $isUC && ($ucLevel == 1 && $orderStatus === 9  || $ucLevel == 3 && $orderStatus === 92);
        $canEditCodBank = $isUC && ($ucLevel == 2 && $orderStatus === 91 || $ucLevel == 3 && $orderStatus === 92);
        $showSection6   = in_array($orderStatus, [9, 91, 92, 10]);

        return [
            // ── CARD 1: Datos generales ───────────────────────────────────
            Section::make('1. Datos generales')
                ->compact()
                ->disabled(!$canEditGeneral)
                ->description('Empresa, tipo, moneda y clasificación')
                ->schema([
                    Grid::make(9)->schema([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->options(Company::orderBy('name')->pluck('name', 'id'))
                            ->searchable()->required()
                            ->columnSpan(2),

                        Select::make('format_id')
                            ->label('Tipo de Orden')
                            ->options(Format::orderBy('description')->pluck('description', 'id'))
                            ->searchable()->required()->live()
                            ->afterStateUpdated(fn (Set $set) => $set('category_id', null))
                            ->columnSpan(2),

                        Select::make('category_id')
                            ->label('Categoría')
                            ->options(fn ($get) => $get('format_id')
                                ? Category::where('format_id', $get('format_id'))->orderBy('description')->pluck('description', 'id')
                                : [])
                            ->searchable()->required()
                            ->placeholder('Seleccione tipo de orden primero')
                            ->columnSpan(3),

                        Select::make('currency')
                            ->label('Moneda')
                            ->options($monedaOpts)
                            ->default($monedas->first()?->value)
                            ->required()
                            ->columnSpan(2),
                    ]),

                    Grid::make(5)->schema([
                        TextInput::make('title')
                            ->label('Título / Asunto')->required()->maxLength(250)
                            ->placeholder('Ej. Adquisición de servidores')
                            ->columnSpan(2),

                        Select::make('sede_id')
                            ->label('Sede')
                            ->options(Sede::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()->required()
                            ->columnSpan(1),

                        Select::make('area_id')
                            ->label('Área')
                            ->options(Area::orderBy('description')->pluck('description', 'id'))
                            ->searchable()->required()->live()
                            ->afterStateUpdated(fn (Set $set) => $set('cc_ids', null))
                            ->columnSpan(1),

                        Select::make('cc_ids')
                            ->label('Centros de Costo')
                            ->options(fn ($get) => $get('area_id')
                                ? Area::find($get('area_id'))?->costCenters()->orderBy('cost_centers.description')->pluck('cost_centers.description', 'cost_centers.id') ?? []
                                : [])
                            ->placeholder('Seleccione un área primero')
                            ->multiple()->searchable()->required()
                            ->columnSpan(1),
                    ]),

                    Grid::make(1)->schema([
                        Textarea::make('justification')
                            ->label('Justificación')->rows(2)->maxLength(500)->required()
                            ->placeholder('Describa el motivo y sustento del requerimiento...'),
                    ]),
                ]),

            // ── CARD 2: Proveedor y condición de pago ─────────────────────
            Section::make('2. Proveedor')
                ->compact()
                ->disabled(!$canEditSection23)
                ->description('RUC, cuenta destino y términos comerciales')
                ->headerActions([
                            FormAction::make('registerSupplier')
                                ->label('Registrar nuevo proveedor')
                                ->color('warning')
                                ->icon('heroicon-o-user-plus')
                                ->visible(fn ($get) => (bool) $get('supplier_not_found'))
                                ->modalHeading('Registrar nuevo proveedor')
                                ->modalSubmitActionLabel('Guardar proveedor')
                                ->form([
                                    Section::make()->compact()->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('new_ruc')
                                                ->label('RUC')
                                                ->required()->maxLength(20)
                                                ->default(fn ($get) => $get('ruc_search'))
                                                ->columnSpan(1),
                                            TextInput::make('new_name')
                                                ->label('Razón social')->required()->maxLength(250)
                                                ->columnSpan(2),
                                        ]),
                                        Grid::make(3)->schema([
                                            TextInput::make('new_address')
                                                ->label('Domicilio fiscal')->maxLength(250),
                                            TextInput::make('new_provincia')
                                                ->label('Provincia')->maxLength(100),
                                            TextInput::make('new_district')
                                                ->label('Distrito')->maxLength(100),
                                        ]),
                                        Grid::make(3)->schema([
                                            TextInput::make('new_contact')
                                                ->label('Contacto')->maxLength(100),
                                            TextInput::make('new_phone')
                                                ->label('Teléfono')->maxLength(20),
                                            TextInput::make('new_email')
                                                ->label('Correo')->email()->maxLength(100),
                                        ]),
                                    ]),
                                    Section::make('Cuentas bancarias')->compact()->schema([
                                        Repeater::make('new_accounts')
                                            ->hiddenLabel()
                                            ->schema([
                                                Select::make('bank')
                                                    ->label('Banco')
                                                    ->options(
                                                        Master::where('main', 67)
                                                            ->orderBy('description')
                                                            ->pluck('description', 'id')
                                                    )->required()->searchable(),
                                                Select::make('currency')
                                                    ->label('Moneda')
                                                    ->options(['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)'])
                                                    ->required()->default('PEN'),
                                                TextInput::make('account_number')
                                                    ->label('N° de cuenta')->required(),
                                                TextInput::make('cci')
                                                    ->label('CCI (interbancario)'),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('+ Agregar cuenta')
                                            ->defaultItems(1),
                                    ]),
                                ])
                                ->action(function (array $data, Set $set) {
                                    $user     = auth()->user();
                                    $supplier = Supplier::create([
                                        'ruc'        => $data['new_ruc'],
                                        'name'       => $data['new_name'],
                                        'address'    => $data['new_address'] ?? null,
                                        'provincia'  => $data['new_provincia'] ?? null,
                                        'district'   => $data['new_district'] ?? null,
                                        'contact'    => $data['new_contact'] ?? null,
                                        'phone'      => $data['new_phone'] ?? null,
                                        'email'      => $data['new_email'] ?? null,
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);

                                    foreach ($data['new_accounts'] ?? [] as $i => $acc) {
                                        $bankName = Master::find($acc['bank'])?->description ?? $acc['bank'];
                                        SupplierAccount::create([
                                            'supplier_id'    => $supplier->id,
                                            'bank'           => $bankName,
                                            'currency'       => $acc['currency'],
                                            'account_number' => $acc['account_number'],
                                            'cci'            => $acc['cci'] ?? null,
                                            'is_primary'     => $i === 0,
                                            'created_by'     => $user->id,
                                            'updated_by'     => $user->id,
                                        ]);
                                    }

                                    $set('supplier_id',        $supplier->id);
                                    $set('supplier_name',      $supplier->name);
                                    $set('supplier_address',   $supplier->address ?? '');
                                    $set('supplier_district',  $supplier->district ?? '');
                                    $set('supplier_contact',   $supplier->contact ?? '');
                                    $set('supplier_email',     $supplier->email ?? '');
                                    $set('ruc_search',         $supplier->ruc);
                                    $set('supplier_not_found', false);
                                }),
                        ])
                        ->schema([
                            Hidden::make('supplier_id')->default(null),
                        Hidden::make('supplier_not_found')->default(false),
                        Hidden::make('supplier_edit_account_id'),
                    ])
                    ->headerActions([
                        FormAction::make('editSupplierAccount')
                            ->label('Editar cuenta')
                            ->icon('heroicon-o-pencil')
                            ->color('gray')
                            ->visible(fn ($get) => (bool) $get('supplier_edit_account_id'))
                            ->modalHeading('Editar cuenta bancaria')
                            ->modalSubmitActionLabel('Guardar cambios')
                            ->fillForm(function ($get): array {
                                $account = SupplierAccount::find($get('supplier_edit_account_id'));
                                if (!$account) return [];
                                $bankMaster = Master::where('main', 67)->where('description', $account->bank)->first();
                                return [
                                    'edit_bank'           => $bankMaster?->id,
                                    'edit_currency'       => $account->currency,
                                    'edit_account_number' => $account->account_number,
                                    'edit_cci'            => $account->cci,
                                    'edit_is_primary'     => (bool) $account->is_primary,
                                ];
                            })
                            ->form([
                                Grid::make(2)->schema([
                                    Select::make('edit_bank')
                                        ->label('Banco')
                                        ->options(Master::where('main', 67)->orderBy('description')->pluck('description', 'id'))
                                        ->required()->searchable(),
                                    Select::make('edit_currency')
                                        ->label('Moneda')
                                        ->options(['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)'])
                                        ->required(),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('edit_account_number')->label('N° de cuenta')->required(),
                                    TextInput::make('edit_cci')->label('CCI (interbancario)'),
                                ]),
                                Toggle::make('edit_is_primary')->label('Marcar como cuenta principal'),
                            ])
                            ->action(function (array $data, Get $get, Set $set): void {
                                $account = SupplierAccount::find($get('supplier_edit_account_id'));
                                if ($account) {
                                    $bankName = Master::find($data['edit_bank'])?->description ?? $data['edit_bank'];
                                    $account->update([
                                        'bank'           => $bankName,
                                        'currency'       => $data['edit_currency'],
                                        'account_number' => $data['edit_account_number'],
                                        'cci'            => $data['edit_cci'] ?? null,
                                        'is_primary'     => (bool) ($data['edit_is_primary'] ?? false),
                                        'updated_by'     => auth()->id(),
                                    ]);
                                    $set('supplier_edit_account_id', null);
                                    \Filament\Notifications\Notification::make()
                                        ->title('Cuenta actualizada')
                                        ->success()
                                        ->send();
                                }
                            }),
                    ])
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('ruc_search')
                                ->label('RUC')
                                ->placeholder('Ingresa el RUC del proveedor')
                                ->columnSpan(1)
                                ->suffixActions([
                                    FormAction::make('searchRuc')
                                        ->label('Buscar')
                                        ->icon('heroicon-o-magnifying-glass')
                                        ->action(function ($state, Set $set) {
                                            $supplier = Supplier::with('accounts')
                                                ->where('ruc', $state)->first();
                                            if ($supplier) {
                                                $set('supplier_id',           $supplier->id);
                                                $set('supplier_name',         $supplier->name);
                                                $set('supplier_address',      $supplier->address ?? '—');
                                                $set('supplier_district',     $supplier->district ?? '—');
                                                $set('supplier_contact',      $supplier->contact ?? '—');
                                                $set('supplier_email',        $supplier->email ?? '—');
                                                $set('supplier_not_found',    false);
                                            } else {
                                                $set('supplier_id',           null);
                                                $set('supplier_name',         '');
                                                $set('supplier_address',      '');
                                                $set('supplier_district',     '');
                                                $set('supplier_contact',      '');
                                                $set('supplier_email',        '');
                                                $set('supplier_not_found',    true);
                                            }
                                        }),
                                    FormAction::make('clearSupplier')
                                        ->label('Limpiar proveedor')
                                        ->icon('heroicon-o-x-circle')
                                        ->color('danger')
                                        ->visible(fn ($get) => (bool) $get('supplier_id'))
                                        ->action(function (Set $set) {
                                            $set('supplier_id',          null);
                                            $set('supplier_name',        '');
                                            $set('supplier_address',     '');
                                            $set('supplier_district',    '');
                                            $set('supplier_contact',     '');
                                            $set('supplier_email',       '');
                                            $set('ruc_search',           '');
                                            $set('supplier_account_id',  null);
                                            $set('supplier_not_found',   false);
                                        }),
                                ]),

                            TextInput::make('supplier_name')
                                ->label('Razón social')
                                ->readOnly()
                                ->required()
                                ->validationMessages(['required' => 'Debes buscar y seleccionar un proveedor.'])
                                ->placeholder('Se autocompleta al buscar')
                                ->extraInputAttributes(['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;'])
                                ->columnSpan(2),
                        ]),

                        Grid::make(4)->schema([
                            TextInput::make('supplier_address')->label('Domicilio fiscal')->readOnly()
                                ->extraInputAttributes(['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']),
                            TextInput::make('supplier_district')->label('Distrito')->readOnly()
                                ->extraInputAttributes(['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']),
                            TextInput::make('supplier_contact')->label('Contacto')->readOnly()
                                ->extraInputAttributes(['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']),
                            TextInput::make('supplier_email')->label('Correo')->readOnly()
                                ->extraInputAttributes(['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']),
                        ])->hidden(fn ($get) => !$get('supplier_id')),

                        Hidden::make('supplier_account_id'),

                        Placeholder::make('_supplier_accounts_table')
                            ->label('Cuentas bancarias')
                            ->content(function ($get) {
                                $supplier_id = $get('supplier_id');
                                if (!$supplier_id) {
                                    return new HtmlString('<span style="color:#94a3b8;font-style:italic">Selecciona un proveedor primero</span>');
                                }

                                $accounts = SupplierAccount::where('supplier_id', $supplier_id)->get();
                                $selected = $get('supplier_account_id');

                                if ($accounts->isEmpty()) {
                                    return new HtmlString('<span style="color:#94a3b8;font-style:italic">Sin cuentas registradas</span>');
                                }

                                $html = '<div class="overflow-x-auto rounded-lg border border-gray-200" style="max-width:100%;width:100%">';
                                $html .= '<table class="w-full divide-y divide-gray-200" style="table-layout:auto">';
                                $html .= '<thead class="bg-gray-50">';
                                $html .= '<tr>';
                                $html .= '<th style="padding:8px 12px;text-align:left;text-xs font-medium text-gray-700 uppercase"></th>';
                                $html .= '<th style="padding:8px 12px;text-align:left;text-xs font-medium text-gray-700 uppercase">Banco</th>';
                                $html .= '<th style="padding:8px 12px;text-align:left;text-xs font-medium text-gray-700 uppercase">Moneda</th>';
                                $html .= '<th style="padding:8px 12px;text-align:left;text-xs font-medium text-gray-700 uppercase">Número de cuenta</th>';
                                $html .= '<th style="padding:8px 12px;text-align:left;text-xs font-medium text-gray-700 uppercase">CCI</th>';
                                $html .= '<th style="padding:8px 12px;text-center;text-xs font-medium text-gray-700 uppercase">Principal</th>';
                                $html .= '<th style="padding:8px 12px;text-center;text-xs font-medium text-gray-700 uppercase">Acción</th>';
                                $html .= '</tr>';
                                $html .= '</thead>';
                                $html .= '<tbody class="divide-y divide-gray-200 bg-white">';

                                foreach ($accounts as $a) {
                                    $isSelected = $selected == $a->id ? 'checked' : '';
                                    $html .= '<tr class="hover:bg-gray-50" style="cursor:pointer">';
                                    $html .= '<td style="padding:8px 12px;text-center"><input type="radio" name="supplier_account_id" value="' . $a->id . '" ' . $isSelected . ' onchange="document.querySelector(\'[name=\\\"supplier_account_id\\\"]\').value = this.value; Livewire.dispatch(\'refreshForm\')" style="cursor:pointer"></td>';
                                    $html .= '<td style="padding:8px 12px;color:#1e293b;font-weight:600">' . e($a->bank) . '</td>';
                                    $html .= '<td style="padding:8px 12px;color:#1e293b"><span style="background:#dbeafe;color:#1d4ed8;font-size:11px;padding:2px 6px;border-radius:4px;font-weight:700">' . e($a->currency) . '</span></td>';
                                    $html .= '<td style="padding:8px 12px;color:#64748b">' . e($a->account_number) . '</td>';
                                    $html .= '<td style="padding:8px 12px;color:#64748b">' . ($a->cci ? e($a->cci) : '—') . '</td>';
                                    $html .= '<td style="padding:8px 12px;text-center">';
                                    if ($a->is_primary) {
                                        $html .= '<span style="background:#d1fae5;color:#065f46;font-size:11px;padding:2px 6px;border-radius:4px;font-weight:700">SÍ</span>';
                                    } else {
                                        $html .= '<span style="color:#94a3b8">—</span>';
                                    }
                                    $html .= '</td>';
                                    $html .= '<td style="padding:8px 12px;text-center"><button type="button" onclick="editAccount(' . $a->id . ')" style="border:none;background:none;padding:0;cursor:pointer;color:#0ea5e9" title="Editar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button></td>';
                                    if ($a === reset($accounts)) {
                                        $html .= '<script>function editAccount(id) { var field = document.querySelector("[name=\"supplier_edit_account_id\"]"); if(field) { field.value = id; Livewire.dispatch("refreshForm"); } }</script>';
                                    }
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody>';
                                $html .= '</table>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->visible(fn ($get) => (bool) $get('supplier_id')),

                    ]),

            // ── CARD 3: Detalle de la orden ───────────────────────────────
            Section::make('3. Detalle de la orden')
                ->compact()
                ->disabled(!$canEditSection23)
                ->description('Productos / servicios y configuración tributaria')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('grabable')
                            ->label('Calcular IGV (18%)')
                            ->helperText('Desactiva para inafectos o exonerados')
                            ->default(true)
                            ->columnSpan(1),

                        Toggle::make('apply_discount')
                            ->label('Aplicar descuento')
                            ->helperText('Descuento general sobre el total')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('discount_type_id', null);
                                }
                            })
                            ->columnSpan(1),

                        Select::make('discount_type_id')
                            ->hiddenLabel()
                            ->placeholder('Selecciona % de descuento')
                            ->options($discountOpts ?: [
                                '5'  => '5% — Pronto pago',
                                '10' => '10% — Convenio',
                                '15' => '15% — Liquidación',
                            ])
                            ->disabled(fn ($get) => !$get('apply_discount'))
                            ->columnSpan(1),
                    ]),

                    Grid::make(5)->schema([
                        Placeholder::make('_h_desc')->hiddenLabel()
                            ->content(new HtmlString('<span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px">Descripción del producto / servicio</span>'))
                            ->columnSpan(2),
                        Placeholder::make('_h_qty')->hiddenLabel()
                            ->content(new HtmlString('<span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px">Cant.</span>')),
                        Placeholder::make('_h_price')->hiddenLabel()
                            ->content(new HtmlString('<span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px">P. Unitario</span>')),
                        Placeholder::make('_h_sub')->hiddenLabel()
                            ->content(new HtmlString('<span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px">Subtotal</span>')),
                    ])->columnSpanFull()
                        ->extraAttributes(['style' => 'border-bottom:2px solid #e2e8f0;padding-bottom:4px']),

                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->schema([
                            TextInput::make('description')
                                ->hiddenLabel()
                                ->required()->columnSpan(2),

                            TextInput::make('quantity')
                                ->hiddenLabel()
                                ->numeric()->default(1)->minValue(1)
                                ->live(debounce: 1000),

                            TextInput::make('unit_price')
                                ->hiddenLabel()
                                ->numeric()->default(0)->step('0.01')->minValue(0.01)->required()
                                ->live(debounce: 1000),

                            Placeholder::make('row_subtotal')
                                ->hiddenLabel()
                                ->content(fn ($get): string =>
                                    number_format(
                                        floatval($get('quantity') ?: 0) * floatval($get('unit_price') ?: 0),
                                        2
                                    )
                                ),
                        ])
                        ->columns(5)
                        ->reorderableWithDragAndDrop(false)
                        ->extraAttributes(['class' => 'fi-items-table'])
                        ->addActionLabel('+ Agregar ítem')
                        ->defaultItems(1)
                        ->columnSpanFull()
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            // Calcular solo al agregar/eliminar items, no en cada keystroke
                            $items = $get('items') ?? [];
                            $subtotal = collect($items)->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
                            $grabable = $get('grabable');
                            $igv = $grabable ? round($subtotal * 0.18, 2) : 0;
                            $total = round($subtotal + $igv, 2);
                            if ($get('apply_discount') && $get('discount_type_id')) {
                                $pct = floatval($get('discount_type_id'));
                                $discount = round($total * $pct / 100, 2);
                                $total = round($total - $discount, 2);
                            }
                        }),

                    Textarea::make('observation')
                        ->label('Observaciones')->rows(2)->columnSpanFull(),

                    Placeholder::make('totals_display')
                        ->label('')
                        ->content(fn ($get) => $this->totalsHtml($get))
                        ->columnSpanFull(),
                ]),

            // ── CARD 3.5: Condición de pago ───────────────────────────────
            Section::make('Condición de pago')->compact()->schema([
                Grid::make(9)->schema([
                    Select::make('payment_id')
                        ->label('Forma de pago')
                        ->options($paymentOpts)->searchable()->required()
                        ->columnSpan(2),

                    Select::make('condition_payment')
                        ->label('Condición')
                        ->options($conditionOpts)->searchable()->required()
                        ->live()
                        ->columnSpan(2),

                    TextInput::make('quotas')
                        ->label('N° de cuotas')
                        ->numeric()->default(1)->minValue(1)->required()
                        ->disabled()
                        ->columnSpan(1)
                        ->dehydrated(true),

                    DatePicker::make('expiration_date')
                        ->label('Fecha de vencimiento')
                        ->required(fn ($get) => !$this->isConditionFraccionado($get('condition_payment'), $conditionOpts))
                        ->columnSpan(2),

                    Select::make('payment_schedule_id')
                        ->label('Programación')
                        ->options(PaymentSchedule::orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required()
                        ->columnSpan(2),
                ]),

                Hidden::make('plan_cuotas'),

                Placeholder::make('_plan_cuotas_placeholder')
                    ->label('Plan de cuotas configurado')
                    ->content(function ($get) use ($conditionOpts) {
                        if (!$this->isConditionFraccionado($get('condition_payment'), $conditionOpts)) {
                            return new HtmlString('<span style="color:#94a3b8;font-style:italic">No aplicable para esta condición</span>');
                        }

                        $planCuotas = $get('plan_cuotas');
                        if (is_string($planCuotas)) {
                            $planCuotas = json_decode($planCuotas, true) ?? [];
                        }

                        if (empty($planCuotas)) {
                            return new HtmlString('<span style="color:#94a3b8;font-style:italic">Sin configurar aún. Haz clic en "Configurar cuotas" →</span>');
                        }

                        $html = '<table style="width:100%;border-collapse:collapse;font-size:0.85rem">';
                        $html .= '<thead><tr style="background:#f1f5f9;border-bottom:1px solid #e2e8f0">';
                        $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Cuota</th>';
                        $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Fecha vencimiento</th>';
                        $html .= '<th style="padding:8px;text-align:right;color:#64748b;font-weight:600">Monto</th>';
                        $html .= '</tr></thead><tbody>';

                        $total = 0;
                        foreach ($planCuotas as $cuota) {
                            $monto = floatval($cuota['monto'] ?? 0);
                            $total += $monto;
                            $html .= '<tr style="border-bottom:1px solid #f1f5f9">';
                            $html .= '<td style="padding:8px;color:#1e293b;font-weight:600">Cuota ' . intval($cuota['numero'] ?? 1) . '</td>';
                            $html .= '<td style="padding:8px;color:#64748b">' . ($cuota['fecha_vencimiento'] ?? '—') . '</td>';
                            $html .= '<td style="padding:8px;text-align:right;color:#1e293b;font-weight:600">S/ ' . number_format($monto, 2) . '</td>';
                            $html .= '</tr>';
                        }

                        $html .= '<tr style="background:#f0fdf4;border-top:2px solid #22c55e;font-weight:700">';
                        $html .= '<td colspan="2" style="padding:8px;color:#15803d">Total:</td>';
                        $html .= '<td style="padding:8px;text-align:right;color:#15803d">S/ ' . number_format($total, 2) . '</td>';
                        $html .= '</tr>';
                        $html .= '</tbody></table>';

                        return new HtmlString($html);
                    })
                    ->visible(fn ($get) =>
                        $this->isConditionFraccionado($get('condition_payment'), $conditionOpts)
                    )
                    ->columnSpanFull(),
            ])
                ->headerActions([
                    FormAction::make('configurar_cuotas')
                        ->label('Configurar cuotas')
                        ->icon('heroicon-o-calendar')
                        ->visible(fn (Get $get) =>
                            $this->isConditionFraccionado($get('condition_payment'), $conditionOpts)
                        )
                        ->modalHeading('Plan de cuotas')
                        ->modalWidth('5xl')
                        ->modalSubmitActionLabel('Guardar plan')
                        ->fillForm(function (Get $get): array {
                            $planCuotas = $get('plan_cuotas');
                            if (is_string($planCuotas)) {
                                $planCuotas = json_decode($planCuotas, true) ?? [];
                            }
                            return [
                                'plan_items' => $planCuotas,
                            ];
                        })
                        ->form([
                            Placeholder::make('_info_total')
                                ->label('Verificación de totales')
                                ->content(new HtmlString(
                                    '<div style="padding:12px;background:#ede9fe;border-radius:6px;border:1px solid #c4b5fd">'
                                    . '<p style="color:#6b21a8;font-weight:600;margin:0 0 8px 0">Verifica que el total de cuotas coincida con el monto total de la orden</p>'
                                    . '<p style="color:#64748b;font-size:0.9rem;margin:0">Los montos se calcularán al abrir la modal</p>'
                                    . '</div>'
                                ))
                                ->columnSpanFull(),

                            Repeater::make('plan_items')
                                ->hiddenLabel()
                                ->schema([
                                    TextInput::make('numero')
                                        ->label('Cuota #')
                                        ->disabled()
                                        ->dehydrated(true)
                                        ->columnSpan(1),
                                    DatePicker::make('fecha_vencimiento')
                                        ->label('Fecha de vencimiento')
                                        ->required()
                                        ->columnSpan(2),
                                    TextInput::make('monto')
                                        ->label('Monto')
                                        ->numeric()
                                        ->required()
                                        ->columnSpan(1),
                                ])
                                ->columns(4)
                                ->addActionLabel('+ Agregar cuota')
                                ->defaultItems(1)
                                ->live(debounce: 100)
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (!is_array($state)) {
                                        return;
                                    }
                                    $items = [];
                                    foreach ($state as $index => $item) {
                                        if (is_array($item)) {
                                            $item['numero'] = count($items) + 1;
                                            $items[] = $item;
                                        }
                                    }
                                    $set('plan_items', $items);
                                })
                                ->columnSpanFull(),
                        ])
                        ->action(function (array $data, Set $set, Get $get) {
                            $items = $data['plan_items'] ?? [];
                            $numbered = [];
                            $totalCuotas = 0;
                            foreach ($items as $index => $item) {
                                if (is_array($item)) {
                                    $item['numero'] = count($numbered) + 1;
                                    $totalCuotas += floatval($item['monto'] ?? 0);
                                    $numbered[] = $item;
                                }
                            }

                            // Calcular total de la orden
                            $orderItems = $get('items') ?? [];
                            $subtotal = collect($orderItems)->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
                            $grabable = $get('grabable');
                            $igv = $grabable ? round($subtotal * 0.18, 2) : 0;
                            $orderTotal = round($subtotal + $igv, 2);
                            if ($get('apply_discount') && $get('discount_type_id')) {
                                $pct = floatval($get('discount_type_id'));
                                $discount = round($orderTotal * $pct / 100, 2);
                                $orderTotal = round($orderTotal - $discount, 2);
                            }

                            // Validar que coincidan
                            if (abs($totalCuotas - $orderTotal) > 0.01) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error: Total de cuotas no coincide')
                                    ->body("El total de cuotas (S/ " . number_format($totalCuotas, 2) . ") debe ser igual al monto total de la orden (S/ " . number_format($orderTotal, 2) . ")")
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $set('plan_cuotas', json_encode($numbered));
                            $set('quotas', count($numbered));
                        }),
                ]),

            // ── CARD 4: Documentos ────────────────────────────────────────
            Section::make('4. Documentos')
                ->compact()
                ->disabled(!$canEditDocs)
                ->collapsible()->collapsed()
                ->description('Comprobante de pago y documentos anexos')
                ->schema([
                    Section::make('Comprobante de pago')->compact()->schema([
                        Hidden::make('comprobantes_data'),

                        Placeholder::make('_comprobantes_placeholder')
                            ->content(function ($get) {
                                $comprobantes = $get('comprobantes_data');
                                if (is_string($comprobantes)) {
                                    $comprobantes = json_decode($comprobantes, true) ?? [];
                                }

                                if (empty($comprobantes)) {
                                    return new HtmlString('<span style="color:#94a3b8;font-style:italic">Sin comprobantes. Haz clic en "Agregar comprobante" →</span>');
                                }

                                // Obtener símbolo de moneda
                                $currencyValue = $get('currency');
                                $currencySymbols = [
                                    'PEN' => 'S/',
                                    'USD' => '$',
                                    'EUR' => '€',
                                ];
                                $currencySymbol = $currencySymbols[$currencyValue] ?? $currencyValue;

                                $html = '<div x-data="comprobantesTable()" style="width:100%">';
                                $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.85rem">';
                                $html .= '<thead><tr style="background:#f1f5f9;border-bottom:1px solid #e2e8f0">';
                                $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Tipo</th>';
                                $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">N° Documento</th>';
                                $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Monto</th>';
                                $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Fecha de Emisión</th>';
                                $html .= '<th style="padding:8px;text-align:center;color:#64748b;font-weight:600">Archivo</th>';
                                $html .= '<th style="padding:8px;text-align:center;color:#64748b;font-weight:600">Acciones</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($comprobantes as $index => $comp) {
                                    $html .= '<tr style="border-bottom:1px solid #e2e8f0">';
                                    $html .= '<td style="padding:8px">' . ($comp['type_file_label'] ?? $comp['type_file'] ?? '-') . '</td>';
                                    $html .= '<td style="padding:8px">' . ($comp['document_number'] ?? '-') . '</td>';
                                    $html .= '<td style="padding:8px;text-align:right">' . (isset($comp['amount']) ? $currencySymbol . ' ' . number_format($comp['amount'], 2) : '-') . '</td>';
                                    $html .= '<td style="padding:8px">' . ($comp['emission_date'] ?? '-') . '</td>';
                                    $html .= '<td style="padding:8px;text-align:center">';
                                    if ($comp['path'] ?? false) {
                                        $html .= '<a href="' . asset('storage/' . $comp['path']) . '" target="_blank" style="color:#3b82f6;text-decoration:underline;cursor:pointer;font-size:0.9rem">Ver documento</a>';
                                    } else {
                                        $html .= '<span style="color:#94a3b8">-</span>';
                                    }
                                    $html .= '</td>';
                                    $html .= '<td style="padding:8px;text-align:center;display:flex;gap:8px;justify-content:center">';
                                    $html .= '<button type="button" onclick="deleteComprobante(' . $index . ')" style="color:#ef4444;cursor:pointer;border:none;background:none;padding:4px;display:inline-flex;align-items:center" title="Eliminar"><svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg></button>';
                                    $html .= '<button type="button" onclick="editComprobante(' . $index . ')" style="color:#3b82f6;cursor:pointer;border:none;background:none;padding:4px;display:inline-flex;align-items:center" title="Editar"><svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg></button>';
                                    $html .= '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';
                                $html .= '</div>';
                                $html .= '<script>
                                    function deleteComprobante(index) {
                                        if (confirm("¿Eliminar este comprobante?")) {
                                            let fieldValue = document.querySelector("input[name*=\'comprobantes_data\']").value;
                                            let comprobantes = JSON.parse(fieldValue);
                                            comprobantes.splice(index, 1);
                                            document.querySelector("input[name*=\'comprobantes_data\']").value = JSON.stringify(comprobantes);
                                            location.reload();
                                        }
                                    }
                                    function editComprobante(index) {
                                        alert("Edición aún no implementada. Por ahora, elimina y agrega de nuevo.");
                                    }
                                </script>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])->headerActions([
                        FormAction::make('agregar_comprobante')
                            ->label('+ Agregar comprobante')
                            ->icon('heroicon-o-document-plus')
                            ->modalHeading('Agregar comprobante de pago')
                            ->modalWidth('2xl')
                            ->modalSubmitActionLabel('Guardar comprobante')
                            ->form([
                                Select::make('type_file')
                                    ->label('Tipo de comprobante')
                                    ->options($voucherTypes)
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                TextInput::make('type_file_label')
                                    ->label('')
                                    ->hidden()
                                    ->dehydrated(false),

                                TextInput::make('document_number')
                                    ->label('N.° de documento')
                                    ->placeholder('F001-00012345')
                                    ->required(),

                                TextInput::make('amount')
                                    ->label('Monto')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required(),

                                DatePicker::make('emission_date')
                                    ->label('Fecha de emisión')
                                    ->format('d/m/Y')
                                    ->required(),

                                FileUpload::make('path')
                                    ->label('Archivo del comprobante')
                                    ->disk('public')->directory('orders/vouchers')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(10240)
                                    ->downloadable(),
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                $comprobantes = $get('comprobantes_data');
                                if (is_string($comprobantes)) {
                                    $comprobantes = json_decode($comprobantes, true) ?? [];
                                }

                                // Obtener el label del tipo desde la BD
                                $master = \App\Models\Master::where('main', 15)->where('value', $data['type_file'])->first();
                                $data['type_file_label'] = $master?->description ?? $data['type_file'];

                                $comprobantes[] = $data;
                                $set('comprobantes_data', json_encode($comprobantes));

                                \Filament\Notifications\Notification::make()
                                    ->title('Comprobante agregado')
                                    ->success()
                                    ->send();
                            }),
                    ]),

                    Section::make('Documentos anexos')
                        ->compact()
                        ->description(new HtmlString('<span style="font-size:0.85rem;color:#64748b">PDF, JPG, PNG · máx. 10 MB c/u</span>'))
                        ->schema([
                            Hidden::make('documentos_anexos_data'),

                            Placeholder::make('_documentos_placeholder')
                                ->label('Documentos agregados')
                                ->content(function ($get) use ($attachTypes) {
                                    $documentos = $get('documentos_anexos_data');
                                    if (is_string($documentos)) {
                                        $documentos = json_decode($documentos, true) ?? [];
                                    }

                                    if (empty($documentos)) {
                                        return new HtmlString('<span style="color:#94a3b8;font-style:italic">Sin documentos. Haz clic en "Agregar documento" →</span>');
                                    }

                                    // Crear mapa de tipos
                                    $tiposMap = $attachTypes->pluck('description', 'value')->toArray();

                                    $html = '<table style="width:100%;border-collapse:collapse;font-size:0.85rem">';
                                    $html .= '<thead><tr style="background:#f1f5f9;border-bottom:1px solid #e2e8f0">';
                                    $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Tipo</th>';
                                    $html .= '<th style="padding:8px;text-align:left;color:#64748b;font-weight:600">Comentario</th>';
                                    $html .= '<th style="padding:8px;text-align:center;color:#64748b;font-weight:600">Archivo</th>';
                                    $html .= '<th style="padding:8px;text-align:center;color:#64748b;font-weight:600">Acciones</th>';
                                    $html .= '</tr></thead><tbody>';

                                    foreach ($documentos as $index => $doc) {
                                        $html .= '<tr style="border-bottom:1px solid #e2e8f0">';
                                        $html .= '<td style="padding:8px">' . ($tiposMap[$doc['type']] ?? $doc['type'] ?? '-') . '</td>';
                                        $html .= '<td style="padding:8px">' . ($doc['comentario'] ?? '-') . '</td>';
                                        $html .= '<td style="padding:8px;text-align:center">';
                                        if ($doc['path'] ?? false) {
                                            $html .= '<a href="' . asset('storage/' . $doc['path']) . '" target="_blank" style="color:#3b82f6;text-decoration:underline;cursor:pointer;font-size:0.9rem">Ver documento</a>';
                                        } else {
                                            $html .= '<span style="color:#94a3b8">-</span>';
                                        }
                                        $html .= '</td>';
                                        $html .= '<td style="padding:8px;text-align:center;display:flex;gap:8px;justify-content:center">';
                                        $html .= '<button type="button" onclick="deleteDocumento(' . $index . ')" style="color:#ef4444;cursor:pointer;border:none;background:none;padding:4px;display:inline-flex;align-items:center" title="Eliminar"><svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg></button>';
                                        $html .= '</td>';
                                        $html .= '</tr>';
                                    }

                                    $html .= '</tbody></table>';
                                    $html .= '<script>
                                        function deleteDocumento(index) {
                                            if (confirm("¿Eliminar este documento?")) {
                                                let fieldValue = document.querySelector("input[name*=\'documentos_anexos_data\']").value;
                                                let documentos = JSON.parse(fieldValue);
                                                documentos.splice(index, 1);
                                                document.querySelector("input[name*=\'documentos_anexos_data\']").value = JSON.stringify(documentos);
                                                location.reload();
                                            }
                                        }
                                    </script>';
                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])->headerActions([
                            FormAction::make('agregar_documento')
                                ->label('+ Agregar D.Anexos')
                                ->icon('heroicon-o-document-plus')
                                ->modalHeading('Agregar documento anexo')
                                ->modalWidth('2xl')
                                ->modalSubmitActionLabel('Guardar documento')
                                ->form([
                                    Select::make('type')
                                        ->label('Tipo de documento')
                                        ->options($attachTypes->pluck('description', 'value')->toArray())
                                        ->required(),

                                    TextInput::make('comentario')
                                        ->label('Comentario')
                                        ->placeholder('Opcional: observaciones sobre el documento')
                                        ->maxLength(500),

                                    FileUpload::make('path')
                                        ->label('Archivo')
                                        ->disk('public')->directory('orders/docs')
                                        ->visibility('public')
                                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                                        ->maxSize(10240)
                                        ->downloadable()
                                        ->required(),
                                ])
                                ->action(function (array $data, Set $set, Get $get) {
                                    $documentos = $get('documentos_anexos_data');
                                    if (is_string($documentos)) {
                                        $documentos = json_decode($documentos, true) ?? [];
                                    }

                                    $documentos[] = $data;
                                    $set('documentos_anexos_data', json_encode($documentos));

                                    \Filament\Notifications\Notification::make()
                                        ->title('Documento agregado')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                ]),

            // ── CARD 5: Registro Contable (UC) ───────────────────────────
            Section::make('5. Registro Contable')
                ->compact()

                ->collapsible()->collapsed()
                ->description('Códigos ingresados por el área de Contabilidad')
                ->hidden(!$showSection6)
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('codigo_registro')
                            ->label('Código de Registro')
                            ->placeholder($canEditCodReg ? 'Ingresa el código de registro' : '—')
                            ->disabled(!$canEditCodReg)
                            ->extraInputAttributes(!$canEditCodReg
                                ? ['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']
                                : [])
                            ->maxLength(100),

                        TextInput::make('codigo_banco')
                            ->label('Código de Banco')
                            ->placeholder($canEditCodBank ? 'Ingresa el código de banco' : '—')
                            ->disabled(!$canEditCodBank)
                            ->extraInputAttributes(!$canEditCodBank
                                ? ['style' => 'background:#f1f5f9;color:#475569;cursor:not-allowed;']
                                : [])
                            ->maxLength(100)
                            ->hidden($isUC && $ucLevel == 1 && $orderStatus === 9),
                    ]),
                ]),
        ];
    }

    protected function alpineTotalsHtml(): HtmlString
    {
        $script = <<<'JS'
<script>
window.orderTotals = function() {
    return {
        sub:0, igv:0, tot:0, disc:0, neto:0, grab:true, apd:false, cur:'S/ ',
        init: function() {
            var _c = this;
            setTimeout(function(){ _c.recalc(); }, 200);
            if (window.Livewire) {
                Livewire.hook('request', function(e) {
                    e.succeed(function(){ setTimeout(function(){ _c.recalc(); }, 50); });
                });
            }
        },
        getData: function() {
            try {
                var wireEl = this.$el.closest('[wire\\:id]');
                if (!wireEl) return {};
                var snap = wireEl.getAttribute('wire:snapshot');
                if (!snap) return {};
                var parsed = JSON.parse(snap);
                return (parsed && parsed.data) ? parsed.data : {};
            } catch(e) { return {}; }
        },
        recalc: function() {
            var d    = this.getData();
            var itms = Object.values((d.items) || {});
            this.grab = !!d.grabable;
            this.apd  = !!d.apply_discount;
            this.cur  = (d.currency || 'PEN') === 'USD' ? '$ ' : 'S/ ';
            var pct   = parseFloat(d.discount_type_id || 0) || 0;
            this.sub  = itms.reduce(function(s,i){ return s + parseFloat((i&&i.quantity)||0) * parseFloat((i&&i.unit_price)||0); }, 0);
            this.igv  = this.grab ? Math.round(this.sub * 18) / 100 : 0;
            this.tot  = Math.round((this.sub + this.igv) * 100) / 100;
            this.disc = this.apd  ? Math.round(this.tot * pct) / 100 : 0;
            this.neto = Math.round((this.tot - this.disc) * 100) / 100;
        },
        fmt: function(v) {
            return this.cur + parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };
};
</script>
JS;

        return new HtmlString(
            $script .
            '<div wire:key="totals-alpine" x-data="orderTotals()" '
            . ' style="display:flex;align-items:center;justify-content:flex-end;gap:24px;padding:12px 16px;'
            . 'background:#F8FAFC;border:1px solid #E2E8EF;border-radius:8px;margin-top:4px;flex-wrap:wrap;">'

            . $this->totItem2('Subtotal', '', 'fmt(sub)')
            . '<div x-show="grab">' . $this->totItem2('IGV (18%)', '', 'fmt(igv)') . '</div>'
            . '<div x-show="apd && disc>0">' . $this->totItem2('Descuento', 'color:#C0392B', '"− "+fmt(disc)') . '</div>'
            . '<div x-show="apd && disc>0">' . $this->totItem2('Neto c/ desc.', 'color:#1a6b3c', 'fmt(neto)') . '</div>'

            . '<div style="padding-left:20px;border-left:1px solid #E2E8EF;">'
            . '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#94A3B8;font-weight:600;">Total</div>'
            . '<div x-text="fmt(apd&&disc>0?neto:tot)" style="font-size:20px;font-weight:700;color:#0F172A;"></div>'
            . '</div></div>'
        );
    }

    private function totItem2(string $label, string $color, string $xText): string
    {
        $style = 'font-size:13.5px;font-weight:600;color:' . ($color ?: '#0F172A') . ';';
        return '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:1px;">'
            . '<span style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#94A3B8;font-weight:600;">' . $label . '</span>'
            . '<span x-text="' . $xText . '" style="' . $style . '"></span>'
            . '</div>';
    }

    protected function totalsHtml($get): HtmlString
    {
        $items    = $get('items') ?: [];
        $subtotal = collect($items)->sum(fn ($i) =>
            floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0)
        );
        $igv   = $get('grabable') ? round($subtotal * 0.18, 2) : 0;
        $total = round($subtotal + $igv, 2);

        $discountVal = 0;
        $netoVal     = $total;
        if ($get('apply_discount') && $get('discount_type_id')) {
            $pct         = floatval($get('discount_type_id'));
            $discountVal = round($total * $pct / 100, 2);
            $netoVal     = round($total - $discountVal, 2);
        }

        $cur = match ($get('currency')) { 'USD' => '$', default => 'S/' };

        $html  = '<div style="display:flex;align-items:center;justify-content:flex-end;gap:24px;padding:12px 16px;';
        $html .= 'background:#F8FAFC;border:1px solid #E2E8EF;border-radius:8px;margin-top:4px;flex-wrap:wrap;">';

        $html .= $this->totItem('Subtotal', "{$cur} " . number_format($subtotal, 2));
        if ($get('grabable')) {
            $html .= $this->totItem('IGV (18%)', "{$cur} " . number_format($igv, 2));
        }
        if ($get('apply_discount') && $discountVal > 0) {
            $html .= $this->totItem('Descuento', "− {$cur} " . number_format($discountVal, 2), '#C0392B');
            $html .= $this->totItem('Neto c/ desc.', "{$cur} " . number_format($netoVal, 2), '#1a6b3c');
        }

        $mainVal = $get('apply_discount') && $discountVal > 0 ? $netoVal : $total;
        $html   .= '<div style="padding-left:20px;border-left:1px solid #E2E8EF;">';
        $html   .= '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#94A3B8;font-weight:600;">Total</div>';
        $html   .= '<div style="font-size:20px;font-weight:700;color:#0F172A;">' . "{$cur} " . number_format($mainVal, 2) . '</div>';
        $html   .= '</div></div>';

        return new HtmlString($html);
    }

    protected function totItem(string $label, string $val, string $color = '#0F172A'): string
    {
        return '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:1px;">'
            . '<span style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#94A3B8;font-weight:600;">' . $label . '</span>'
            . '<span style="font-size:13.5px;font-weight:600;color:' . $color . ';">' . $val . '</span>'
            . '</div>';
    }

    protected function getMonedas()
    {
        $parentIds = Master::where('type', 'MONEDA')->whereNull('description')->pluck('id');
        return Master::whereIn('main', $parentIds)
            ->whereNotNull('description')->whereNotNull('value')
            ->get()->unique('value')->values();
    }

    protected function getMasterOptions(int $mainId): array
    {
        return Master::where('main', $mainId)
            ->whereNotNull('description')
            ->orderBy('description')
            ->pluck('description', 'id')
            ->toArray();
    }

    protected function isConditionFraccionado($conditionId, array $conditionOpts): bool
    {
        if (!$conditionId) return false;
        $selected = $conditionOpts[$conditionId] ?? '';
        return stripos($selected, 'fraccionado') !== false;
    }

    protected function getStatusMeta(int $status): array
    {
        $label = Status::label($status);
        $color = match ($status) {
            0, 10  => 'gray',
            1      => 'info',
            2, 5   => 'warning',
            3, 6, 7, 9 => 'success',
            4      => 'danger',
            8, 91, 92  => 'primary',
            default => 'gray',
        };
        return [$label, $color];
    }

    protected function buildHistoryHtml(Order $order): string
    {
        $history = OrderHistory::where('order_id', $order->id)->orderByDesc('id')->get();

        if ($history->isEmpty()) {
            return '<p style="color:#94a3b8;text-align:center;padding:32px 0;font-size:0.9rem;">Esta orden aún no tiene movimientos registrados.</p>';
        }

        $statusNames = Status::pluck('description', 'id')->toArray();
        $roleNames   = UserType::pluck('description', 'prefijo')->toArray();

        $html  = '<div style="padding:4px 0;overflow-x:auto;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
        $html .= '<thead><tr style="border-bottom:2px solid #e2e8f0;">';
        foreach (['Fecha', 'Usuario', 'Movimiento de estado', 'Comentario'] as $th) {
            $html .= '<th style="padding:8px 12px;font-weight:700;color:#64748b;text-align:left;white-space:nowrap">' . $th . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($history as $i => $h) {
            $bg           = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $fromName     = $statusNames[$h->from_status] ?? $h->from_status;
            $toName       = $statusNames[$h->to_status] ?? $h->to_status;
            $fromRole     = $roleNames[$h->from_user] ?? $h->from_user;
            $userName     = \App\Models\User::find($h->created_by)?->name ?? '—';
            $date         = $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i') : '—';
            $comment      = ($h->coment && $h->coment !== '0') ? e($h->coment) : '';
            $isObs        = $h->to_status == 5;
            $commentStyle = $isObs
                ? 'color:#92400e;font-style:italic;background:#fffbeb;padding:3px 8px;border-radius:4px;display:inline-block'
                : 'color:#64748b';

            $fromBg = $isObs ? '#fef3c7' : '#dbeafe';
            $fromFg = $isObs ? '#92400e' : '#1d4ed8';
            $toBg   = $isObs ? '#fee2e2' : '#d1fae5';
            $toFg   = $isObs ? '#991b1b' : '#065f46';

            $html .= "<tr style=\"background:{$bg};border-bottom:1px solid #f1f5f9;\">";
            $html .= "<td style=\"padding:8px 12px;white-space:nowrap;color:#64748b\">{$date}</td>";
            $html .= "<td style=\"padding:8px 12px;\"><span style=\"font-weight:600;color:#1e293b\">" . e($userName) . "</span>"
                   . "<br><span style=\"font-size:0.74rem;color:#94a3b8\">{$fromRole}</span></td>";
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
}
