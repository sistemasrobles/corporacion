<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\HasOrderForm;
use App\Models\Category;
use App\Models\Company;
use App\Models\Format;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderFile;
use App\Models\OrderHistory;
use App\Models\OrderProduct;
use App\Models\OrderQuota;
use App\Models\OrderSequence;
use App\Models\Sede;
use App\Models\Type;
use App\Models\TypeUser;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use HasOrderForm;

    protected static string $resource = OrderResource::class;
    protected static ?string $title   = 'Nueva Orden';

    public function mount(): void
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['JA', 'AA', 'GA'])) {
            abort(403);
        }
        parent::mount();
    }

    public function form(Form $form): Form
    {
        $user       = auth()->user();
        $isJA       = $user->user_type === 'JA';
        $monedas    = $this->getMonedas();
        $monedaOpts = $monedas->pluck('value', 'value')->toArray();

        return $isJA
            ? $form->schema($this->jaSchema($monedas, $monedaOpts))
            : $form->schema($this->aaSchema($user, $monedas, $monedaOpts));
    }

    // ─── FORMULARIO JA ───────────────────────────────────────────────────────
    private function jaSchema($monedas, $monedaOpts): array
    {
        return [
            Section::make('1. Clasificación')
                ->compact()->columns(3)
                ->schema([
                    Select::make('format_id')
                        ->label('Tipo de Orden')
                        ->options(Format::orderBy('description')->pluck('description', 'id'))
                        ->searchable()->required()->live()
                        ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),

                    Select::make('category_id')
                        ->label('Categoría')
                        ->options(fn ($get) => $get('format_id')
                            ? Category::where('format_id', $get('format_id'))->orderBy('description')->pluck('description', 'id')
                            : [])
                        ->searchable()->required()
                        ->placeholder('Seleccione tipo de orden primero'),

                    Select::make('type_id')
                        ->label('Tipo de Gestión')
                        ->options(Type::orderBy('descripcion')->pluck('descripcion', 'id'))
                        ->searchable()->required()->live()
                        ->hint(fn ($get) => $get('type_id')
                            ? (TypeUser::with('user')->where('type_id', $get('type_id'))->first())?->user?->name
                            : null
                        )
                        ->hintIcon('heroicon-m-user-circle')
                        ->hintColor('primary'),
                ]),

            Section::make('2. Identificación Económica')
                ->compact()->columns(4)
                ->schema([
                    Select::make('company_id')
                        ->label('Empresa')
                        ->options(Company::orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),

                    Select::make('currency')
                        ->label('Moneda')
                        ->options($monedaOpts)
                        ->default($monedas->first()?->value)
                        ->required(),

                    TextInput::make('suggested_amount')
                        ->label('Monto Referencial')
                        ->numeric()->minValue(0)->placeholder('0.00')->required()
                        ->helperText('Estimación del costo total'),

                    DatePicker::make('expiration_date')
                        ->label('Fecha Requerida')->required()
                        ->helperText('Fecha límite de entrega'),
                ]),

            Section::make('3. Detalle del Requerimiento y Documentación')
                ->compact()
                ->schema([
                    TextInput::make('title')
                        ->label('Título de la Orden')->required()->maxLength(250)
                        ->placeholder('Ej. Renovación de licencias anuales de software')
                        ->columnSpanFull(),

                    Grid::make(5)->schema([
                        Textarea::make('justification')
                            ->label('Justificación')->rows(5)->maxLength(250)->required()
                            ->placeholder('Describa el motivo y sustento del requerimiento...')
                            ->helperText('Máximo 250 caracteres.')
                            ->columnSpan(3),

                        FileUpload::make('quotation')
                            ->label('Cotización (Opcional)')
                            ->disk('public')->directory('orders/quotations')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120)
                            ->helperText('PDF, JPG, PNG — Máx. 5 MB')
                            ->columnSpan(2),
                    ]),
                ]),
        ];
    }

    // ─── GUARDAR ─────────────────────────────────────────────────────────────
    protected function handleRecordCreation(array $data): Order
    {
        $user   = auth()->user();
        $format = Format::findOrFail($data['format_id']);

        return $user->user_type === 'JA'
            ? $this->createJA($data, $user, $format)
            : $this->createFull($data, $user, $format);
    }

    private function createJA(array $data, $user, Format $format): Order
    {
        return DB::transaction(function () use ($data, $user, $format): Order {
            $typeUser = TypeUser::where('type_id', $data['type_id'] ?? null)->inRandomOrder()->first();

            $order = Order::create([
                'company_id'       => $data['company_id'],
                'status'           => 1,
                'title'            => $data['title'],
                'type_id'          => $data['type_id'] ?? null,
                'format_id'        => $format->abrev,
                'user_responsible' => $typeUser?->user_id ?? 0,
                'created_by'       => $user->id,
                'updated_by'       => $user->id,
            ]);

            $this->generateCode($order, $format->abrev);

            OrderDetail::create([
                'order_id'         => $order->id,
                'required_date'    => now()->toDateString(),
                'expiration_date'  => $data['expiration_date'] ?? null,
                'suggested_amount' => $data['suggested_amount'] ?? 0,
                'justification'    => $data['justification'] ?? null,
                'category_id'      => $data['category_id'] ?? null,
                'currency'         => $data['currency'],
                'created_by'       => $user->id,
                'updated_by'       => $user->id,
            ]);

            OrderHistory::create([
                'from_user'   => 'JA', 'to_user' => 'AA',
                'from_status' => 0,   'to_status' => 1,
                'coment'      => '',  'order_id'  => $order->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            if (!empty($data['quotation'])) {
                OrderFile::create([
                    'id'         => abs(crc32($order->id . '-quotation-' . microtime())),
                    'type_file'  => 9, 'order_id' => $order->id,
                    'path'       => $data['quotation'], 'principal' => 0,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            return $order;
        });
    }

    private function createFull(array $data, $user, Format $format): Order
    {
        return DB::transaction(function () use ($data, $user, $format): Order {
            $typeId = $data['type_id'] ?? TypeUser::where('user_id', $user->id)->first()?->type_id;

            $initialStatus = $user->user_type === 'GA' ? 3 : 2;
            $history = $user->user_type === 'GA'
                ? ['from' => 'GA', 'to' => 'GF', 'from_s' => 2, 'to_s' => 3]
                : ['from' => 'AA', 'to' => 'GA', 'from_s' => 1, 'to_s' => 2];

            $items    = collect($data['items'] ?? [])
                ->filter(fn ($i) => !empty($i['description']))->values();
            $subtotal = $items->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
            $igv      = ($data['grabable'] ?? false) ? round($subtotal * 0.18, 2) : 0;
            $total    = round($subtotal + $igv, 2);
            $discount = 0;
            $amtNeto  = $total;
            if (!empty($data['apply_discount']) && !empty($data['discount_type_id'])) {
                $pct      = floatval($data['discount_type_id']);
                $discount = round($total * $pct / 100, 2);
                $amtNeto  = round($total - $discount, 2);
            }

            $order = Order::create([
                'company_id'          => $data['company_id'],
                'status'              => $initialStatus,
                'title'               => $data['title'],
                'type_id'             => $typeId,
                'format_id'           => $format->abrev,
                'payment_schedule_id' => $data['payment_schedule_id'] ?? null,
                'user_responsible'    => TypeUser::where('type_id', $typeId)->inRandomOrder()->first()?->user_id ?? 0,
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);

            $this->generateCode($order, $format->abrev);

            OrderDetail::create([
                'order_id'            => $order->id,
                'required_date'       => now()->toDateString(),
                'period'              => now()->format('Ym'),
                'expiration_date'     => $data['expiration_date'] ?? null,
                'suggested_amount'    => $total,
                'justification'       => $data['justification'] ?? null,
                'category_id'         => $data['category_id'] ?? null,
                'currency'            => $data['currency'],
                'area_id'             => $data['area_id'] ?? null,
                'sede_id'             => $data['sede_id'] ?? null,
                'cc_id'               => $data['cc_ids'] ?? null,
                'supplier_id'         => $data['supplier_id'] ?? null,
                'supplier_account_id' => $data['supplier_account_id'] ?? null,
                'payment_id'          => $data['payment_id'] ?? null,
                'payment_schedule_id' => $data['payment_schedule_id'] ?? null,
                'condition_payment'   => $data['condition_payment'] ?? null,
                'quotas'              => $data['quotas'] ?? 1,
                'discount'            => $discount,
                'discount_type_id'    => $data['discount_type_id'] ?? null,
                'igv'                 => $igv,
                'sub_total'           => round($subtotal, 2),
                'total'               => $total,
                'amount_neto'         => $amtNeto,
                'grabable'            => ($data['grabable'] ?? false) ? 1 : 0,
                'items'               => null,
                'observation'         => $data['observation'] ?? null,
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);

            foreach ($items as $item) {
                $qty   = floatval($item['quantity'] ?? 1);
                $price = floatval($item['unit_price'] ?? 0);
                OrderProduct::create([
                    'order_id'    => $order->id, 'product_id' => '0',
                    'description' => $item['description'],
                    'quantity'    => $qty, 'unit_price' => $price,
                    'sub_total'   => round($qty * $price, 2),
                    'created_by'  => $user->id, 'updated_by' => $user->id,
                ]);
            }

            OrderHistory::create([
                'from_user'   => $history['from'], 'to_user'   => $history['to'],
                'from_status' => $history['from_s'], 'to_status' => $history['to_s'],
                'coment'      => '', 'order_id' => $order->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            if (!empty($data['voucher_file'])) {
                OrderFile::create([
                    'id'         => abs(crc32($order->id . '-voucher-' . microtime())),
                    'type_file'  => $data['voucher_type_id'] ?? null,
                    'order_id'   => $order->id, 'path' => $data['voucher_file'],
                    'principal'  => 1,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            foreach (Master::where('main', 18)->get() as $m) {
                $fieldName = 'doc_' . $m->value;
                if (!empty($data[$fieldName])) {
                    OrderFile::create([
                        'id'         => abs(crc32($order->id . '-' . $m->value . '-' . microtime())),
                        'type_file'  => $m->value, 'order_id' => $order->id,
                        'path'       => $data[$fieldName], 'principal' => 0,
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                }
            }

            if (!empty($data['doc_8'])) {
                OrderFile::create([
                    'id'         => abs(crc32($order->id . '-8-' . microtime())),
                    'type_file'  => 8, 'order_id' => $order->id,
                    'path'       => $data['doc_8'], 'principal' => 0,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            // Procesar cuotas según condición de pago
            $this->procesarCuotas($order, $data, $user);

            return $order;
        });
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────
    private function generateCode(Order $order, string $typeOrder): void
    {
        $year     = now()->year;
        $sequence = OrderSequence::lockForUpdate()->firstOrCreate(
            ['year_code' => $year, 'order_type' => $typeOrder],
            ['last_number' => 0]
        );
        $sequence->increment('last_number');
        $sequence->refresh();
        $order->update(['code' => $typeOrder . '-' . $year . str_pad($sequence->last_number, 6, '0', STR_PAD_LEFT)]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('cancel')
                ->label('← Cancelar')->color('gray')
                ->url($this->getResource()::getUrl('index')),

            \Filament\Actions\Action::make('save')
                ->label('Enviar para revisión →')->color('primary')
                ->action(fn () => $this->create()),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function procesarCuotas(Order $order, array $data, $user): void
    {
        // Detectar si es FRACCIONADO basado en condition_payment
        $conditionPayment = $data['condition_payment'] ?? null;
        $isFraccionado = false;

        if ($conditionPayment) {
            $condition = \App\Models\Master::find($conditionPayment);
            if ($condition && stripos($condition->description, 'fraccionado') !== false) {
                $isFraccionado = true;
            }
        }

        $totalAmount = $order->detail?->amount_neto ?? 0;

        if ($isFraccionado && !empty($data['plan_cuotas'])) {
            // Decodificar JSON si viene como string
            $planCuotas = is_string($data['plan_cuotas'])
                ? json_decode($data['plan_cuotas'], true)
                : $data['plan_cuotas'];

            $planCuotas = $planCuotas ?? [];

            // Crear cuotas desde el plan ingresado
            foreach ($planCuotas as $cuota) {
                OrderQuota::create([
                    'order_id'      => $order->id,
                    'quota_number'  => intval($cuota['numero'] ?? 1),
                    'amount'        => floatval($cuota['monto'] ?? 0),
                    'due_date'      => $cuota['fecha_vencimiento'] ?? null,
                    'status'        => 200, // PENDIENTE_POR_DEPOSITO
                    'created_by'    => $user->id,
                    'updated_by'    => $user->id,
                ]);
            }
        } else {
            // Crear 1 cuota automáticamente para AL CONTADO, CREDITO, etc.
            OrderQuota::create([
                'order_id'      => $order->id,
                'quota_number'  => 1,
                'amount'        => $totalAmount,
                'due_date'      => $order->detail?->expiration_date ?? now()->addDays(30),
                'status'        => 200, // PENDIENTE_POR_DEPOSITO
                'created_by'    => $user->id,
                'updated_by'    => $user->id,
            ]);
        }
    }
}
