<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\HasOrderForm;
use App\Models\Company;
use App\Models\Format;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderFile;
use App\Models\OrderHistory;
use App\Models\OrderProduct;
use App\Models\Status;
use App\Models\UserType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    use HasOrderForm;

    protected static string $resource = OrderResource::class;

    // ──────────────────────────────────────────────────────────────────────────
    // MOUNT — access control
    // ──────────────────────────────────────────────────────────────────────────

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user  = auth()->user();
        $order = $this->record;

        if (!$this->userCanEdit($order, $user)) {
            abort(403);
        }
    }

    private function userCanEdit(Order $order, $user): bool
    {
        $role   = $user->user_type;
        $status = (int) $order->status;
        $level  = $user->uc_level ?? 0;

        if ($status === 5) {
            $toUser = $this->getLastObsToUser($order->id);
            return match ($role) {
                'JA' => $toUser === 'JA',
                'AA' => $toUser === 'AA',
                'GA' => $toUser === 'GA',
                'GF' => $toUser === 'GF',
                'AF' => $toUser === 'AF',
                default => false,
            };
        }

        return match ($role) {
            'JA' => false, // JA creates via CreateOrder; only edits when observed (status=5)
            'AA' => in_array($status, [1, 7]),
            'GA' => $status === 2,
            'GF' => $status === 3,
            'AF' => in_array($status, [6, 8]),
            'UC' => match ($level) {
                1 => $status === 9,
                2 => $status === 91,
                3 => $status === 92,
                default => false,
            },
            default => false,
        };
    }

    private function getLastObsToUser(int $orderId): ?string
    {
        return OrderHistory::where('order_id', $orderId)
            ->where('to_status', 5)
            ->latest('id')
            ->value('to_user');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TITLE / SUBHEADING
    // ──────────────────────────────────────────────────────────────────────────

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return new HtmlString(
            '<span style="color:#4338ca">' . e($this->record->code) . '</span>'
        );
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $order  = $this->record;
        $status = (int) $order->status;
        $date   = $order->created_at->format('d/m/Y');
        $creator = \App\Models\User::find($order->created_by)?->name ?? '—';

        [$statusLabel, $statusColor] = $this->getStatusMeta($status);

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

    // ──────────────────────────────────────────────────────────────────────────
    // FORM — with section permissions per role/status
    // ──────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        $user       = auth()->user();
        $monedas    = $this->getMonedas();
        $monedaOpts = $monedas->pluck('value', 'value')->toArray();
        $status     = (int) ($this->record?->status ?? 1);
        $perms      = $this->computeEditPerms($user, $status, $this->record);

        return $form->schema($this->aaSchema($user, $monedas, $monedaOpts, $status, $perms));
    }

    private function computeEditPerms($user, int $status, ?Order $record = null): array
    {
        $role = $user->user_type;

        // AA at status=7: only documents (voucher upload)
        if ($role === 'AA' && $status === 7) {
            return ['general' => false, 'documents' => true, 'constancia' => false];
        }

        // AA at status=5: restrict based on who observed
        if ($role === 'AA' && $status === 5 && $record) {
            $obsFrom = OrderHistory::where('order_id', $record->id)
                ->where('to_status', 5)
                ->latest('id')
                ->value('from_user');

            // Observed by AF or UC → only Section 1 (Datos generales), rest blocked
            if (in_array($obsFrom, ['AF', 'UC'])) {
                return ['general' => true, 's1_only' => true, 'documents' => false, 'constancia' => false];
            }

            // Observed by GA or others → full edit
            return ['general' => true, 'documents' => true, 'constancia' => false];
        }

        // AF at status=6: only constancia upload
        if ($role === 'AF' && $status === 6) {
            return ['general' => false, 'documents' => false, 'constancia' => true];
        }

        // GA: full editing
        if ($role === 'GA') {
            return ['general' => true, 'documents' => true, 'constancia' => false];
        }

        // Roles that only use action buttons (no form editing)
        if ($role === 'GF' || ($role === 'AF' && $status === 8) || $role === 'UC') {
            return ['general' => false, 'documents' => false, 'constancia' => false];
        }

        // JA (observed by AA, status=5): only general data, no documents
        if ($role === 'JA') {
            return ['general' => true, 'documents' => false, 'constancia' => false];
        }

        // AA at status=1: full editing
        return ['general' => true, 'documents' => true, 'constancia' => false];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FILL FORM
    // ──────────────────────────────────────────────────────────────────────────

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order  = $this->record->load(['detail.supplier', 'files', 'products']);
        $detail = $order->detail;

        $format = Format::where('abrev', $data['format_id'])->first();
        if ($format) {
            $data['format_id'] = $format->id;
        }

        if ($detail) {
            $data['category_id']          = $detail->category_id;
            $data['currency']             = $detail->currency;
            $data['area_id']              = $detail->area_id;
            $data['cc_ids']               = $detail->cc_id;
            $data['supplier_id']          = $detail->supplier_id;
            $data['payment_id']           = $detail->payment_id;
            $data['payment_schedule_id']  = $order->payment_schedule_id ?? $detail->payment_schedule_id;
            $data['condition_payment']    = $detail->condition_payment;
            $data['quotas']               = $detail->quotas;
            $data['expiration_date']      = $detail->expiration_date;
            $data['grabable']             = (bool) $detail->grabable;
            $data['discount_type_id']     = $detail->discount_type_id;
            $data['discount']             = $detail->discount;
            $data['amount_neto']          = $detail->amount_neto;
            $data['amount_ref']           = $detail->amount_ref ?? $detail->suggested_amount;
            $data['apply_discount']       = floatval($detail->discount) > 0;
            $data['supplier_account_id']  = $detail->supplier_account_id;
            $data['codigo_registro']      = $detail->codigo_registro;
            $data['codigo_banco']         = $detail->codigo_banco;

            if ($order->products->isNotEmpty()) {
                $data['items'] = $order->products->map(fn ($p) => [
                    'description' => $p->description,
                    'quantity'    => $p->quantity,
                    'unit_price'  => $p->unit_price,
                ])->toArray();
            } elseif ($detail->items) {
                $data['items'] = is_array($detail->items) ? $detail->items : json_decode($detail->items, true);
            } elseif ($detail->observation) {
                $decoded = json_decode($detail->observation, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data['items']       = $decoded;
                    $data['observation'] = null;
                } else {
                    $data['observation'] = $detail->observation;
                }
            }

            if ($detail->supplier) {
                $data['ruc_search']        = $detail->supplier->ruc;
                $data['supplier_name']     = $detail->supplier->name;
                $data['supplier_address']  = $detail->supplier->address ?? '';
                $data['supplier_district'] = $detail->supplier->district ?? '';
                $data['supplier_contact']  = $detail->supplier->contact ?? '';
                $data['supplier_email']    = $detail->supplier->email ?? '';
            }
        }

        $voucherFile = $order->files->firstWhere('principal', 1);
        if ($voucherFile) {
            $data['voucher_file']    = $voucherFile->path;
            $data['voucher_type_id'] = $voucherFile->type_file;
        }

        foreach ($order->files->where('principal', 0) as $f) {
            $data['doc_' . $f->type_file] = $f->path;
        }

        return $data;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SAVE — branched by role/status
    // ──────────────────────────────────────────────────────────────────────────

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user          = auth()->user();
        $role          = $user->user_type;
        $currentStatus = (int) $record->status;

        // AF uploading constancia (status=6)
        if ($role === 'AF' && $currentStatus === 6) {
            return $this->saveConstancia($record, $data, $user);
        }

        // AA uploading voucher/comprobante (status=7)
        if ($role === 'AA' && $currentStatus === 7) {
            return $this->saveVoucher($record, $data, $user);
        }

        // Full save: JA@5, AA@1, AA@5
        return $this->saveFullOrder($record, $data, $user);
    }

    protected function saveGAOrderData(Model $record, array $data, $user): void
    {
        $items    = $data['items'] ?? [];
        $subtotal = array_sum(array_map(fn ($i) => floatval($i['unit_price'] ?? 0) * floatval($i['quantity'] ?? 1), $items));
        $igv      = ($data['grabable'] ?? false) ? round($subtotal * 0.18, 2) : 0;
        $total    = round($subtotal + $igv, 2);
        $discount = floatval($data['discount'] ?? 0);
        $amtNeto  = round($total - $discount, 2);

        $format = Format::find($data['format_id'] ?? null);
        $record->update([
            'title'               => $data['title'] ?? $record->title,
            'format_id'           => $format?->abrev ?? $record->format_id,
            'payment_schedule_id' => $data['payment_schedule_id'] ?? $record->payment_schedule_id,
            'updated_by'          => $user->id,
        ]);

        $record->detail()->updateOrCreate(['order_id' => $record->id], [
            'category_id'         => $data['category_id'] ?? null,
            'currency'            => $data['currency'] ?? 'PEN',
            'area_id'             => $data['area_id'] ?? null,
            'cc_id'               => $data['cc_ids'] ?? [],
            'supplier_id'         => $data['supplier_id'] ?? null,
            'supplier_account_id' => $data['supplier_account_id'] ?? null,
            'payment_id'          => $data['payment_id'] ?? null,
            'payment_schedule_id' => $data['payment_schedule_id'] ?? null,
            'condition_payment'   => $data['condition_payment'] ?? null,
            'quotas'              => $data['quotas'] ?? 1,
            'expiration_date'     => $data['expiration_date'] ?? null,
            'discount'            => $discount,
            'discount_type_id'    => $data['discount_type_id'] ?? null,
            'igv'                 => $igv,
            'sub_total'           => round($subtotal, 2),
            'total'               => $total,
            'amount_neto'         => $amtNeto,
            'grabable'            => ($data['grabable'] ?? false) ? 1 : 0,
            'observation'         => $data['observation'] ?? null,
            'updated_by'          => $user->id,
        ]);

        OrderProduct::where('order_id', $record->id)->delete();
        foreach ($items as $item) {
            $qty   = floatval($item['quantity'] ?? 1);
            $price = floatval($item['unit_price'] ?? 0);
            OrderProduct::create([
                'order_id'    => $record->id, 'product_id' => '0',
                'description' => $item['description'],
                'quantity'    => $qty, 'unit_price' => $price,
                'sub_total'   => round($qty * $price, 2),
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);
        }
    }

    private function saveConstancia(Model $record, array $data, $user): Model
    {
        DB::transaction(function () use ($record, $data, $user) {
            if (!empty($data['doc_8'])) {
                $existing = OrderFile::where('order_id', $record->id)->where('type_file', 8)->first();
                if ($existing) {
                    $existing->update(['path' => $data['doc_8'], 'updated_by' => $user->id]);
                } else {
                    OrderFile::create([
                        'id'         => abs(crc32($record->id . '-8-' . microtime())),
                        'type_file'  => 8, 'order_id' => $record->id,
                        'path'       => $data['doc_8'], 'principal' => 0,
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                }
            }
            OrderHistory::create([
                'from_user' => 'AF', 'to_user' => 'AA',
                'from_status' => 6, 'to_status' => 7,
                'coment' => '', 'order_id' => $record->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $record->update(['status' => 7, 'updated_by' => $user->id]);
        });
        return $record->refresh();
    }

    private function saveVoucher(Model $record, array $data, $user): Model
    {
        DB::transaction(function () use ($record, $data, $user) {
            if (!empty($data['voucher_file'])) {
                $voucherTypeId = $data['voucher_type_id'] ?? null;
                $existing = OrderFile::where('order_id', $record->id)->where('principal', 1)->first();
                if ($existing) {
                    $existing->update(['type_file' => $voucherTypeId, 'path' => $data['voucher_file'], 'updated_by' => $user->id]);
                } else {
                    OrderFile::create([
                        'id'         => abs(crc32($record->id . '-voucher-' . microtime())),
                        'type_file'  => $voucherTypeId, 'order_id' => $record->id,
                        'path'       => $data['voucher_file'], 'principal' => 1,
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                }
            }
            OrderHistory::create([
                'from_user' => 'AA', 'to_user' => 'AF',
                'from_status' => 7, 'to_status' => 8,
                'coment' => '', 'order_id' => $record->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $record->update(['status' => 8, 'updated_by' => $user->id]);
        });
        return $record->refresh();
    }

    private function saveFullOrder(Model $record, array $data, $user): Model
    {
        $format = Format::find($data['format_id']);

        $items    = collect($data['items'] ?? [])->filter(fn ($i) => !empty($i['description']))->values();
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

        $currentStatus = (int) $record->status;
        $nextStatus    = $this->resolveNextStatus($record, $currentStatus);
        $toUser        = $this->resolveToUser($nextStatus);

        DB::transaction(function () use ($record, $data, $user, $format, $subtotal, $igv, $total, $discount, $amtNeto, $items, $currentStatus, $nextStatus, $toUser) {
            $record->update([
                'company_id'          => $data['company_id'],
                'title'               => $data['title'],
                'type_id'             => $data['type_id'] ?? $record->type_id,
                'format_id'           => $format?->abrev ?? $record->format_id,
                'payment_schedule_id' => $data['payment_schedule_id'] ?? null,
                'status'              => $nextStatus,
                'updated_by'          => $user->id,
            ]);

            $record->detail()->updateOrCreate(
                ['order_id' => $record->id],
                [
                    'required_date'       => now()->toDateString(),
                    'period'              => now()->format('Ym'),
                    'suggested_amount'    => $total ?: ($data['amount_ref'] ?? 0),
                    'amount_ref'          => $data['amount_ref'] ?? null,
                    'justification'       => $data['justification'] ?? null,
                    'category_id'         => $data['category_id'] ?? null,
                    'currency'            => $data['currency'],
                    'area_id'             => $data['area_id'] ?? null,
                    'cc_id'               => $data['cc_ids'] ?? null,
                    'supplier_id'         => $data['supplier_id'] ?? null,
                    'supplier_account_id' => $data['supplier_account_id'] ?? null,
                    'payment_id'          => $data['payment_id'] ?? null,
                    'payment_schedule_id' => $data['payment_schedule_id'] ?? null,
                    'condition_payment'   => $data['condition_payment'] ?? null,
                    'quotas'              => $data['quotas'] ?? 1,
                    'expiration_date'     => $data['expiration_date'] ?? null,
                    'discount'            => $discount,
                    'discount_type_id'    => $data['discount_type_id'] ?? null,
                    'igv'                 => $igv,
                    'sub_total'           => round($subtotal, 2),
                    'total'               => $total,
                    'amount_neto'         => $amtNeto,
                    'grabable'            => ($data['grabable'] ?? false) ? 1 : 0,
                    'items'               => null,
                    'observation'         => $data['observation'] ?? null,
                    'codigo_registro'     => $data['codigo_registro'] ?? null,
                    'codigo_banco'        => $data['codigo_banco'] ?? null,
                    'updated_by'          => $user->id,
                ]
            );

            OrderProduct::where('order_id', $record->id)->delete();
            foreach ($items as $item) {
                $qty   = floatval($item['quantity'] ?? 1);
                $price = floatval($item['unit_price'] ?? 0);
                OrderProduct::create([
                    'order_id'    => $record->id, 'product_id' => '0',
                    'description' => $item['description'],
                    'quantity'    => $qty, 'unit_price' => $price,
                    'sub_total'   => round($qty * $price, 2),
                    'created_by'  => $user->id, 'updated_by' => $user->id,
                ]);
            }

            OrderHistory::create([
                'from_user'   => $user->user_type, 'to_user' => $toUser,
                'from_status' => $currentStatus,   'to_status' => $nextStatus,
                'coment'      => '', 'order_id' => $record->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            // Comprobante (principal=1)
            if (!empty($data['voucher_file'])) {
                $voucherTypeId = $data['voucher_type_id'] ?? null;
                $existing = OrderFile::where('order_id', $record->id)->where('principal', 1)->first();
                if ($existing) {
                    $existing->update(['type_file' => $voucherTypeId, 'path' => $data['voucher_file'], 'updated_by' => $user->id]);
                } else {
                    OrderFile::create([
                        'id'         => abs(crc32($record->id . '-voucher-' . microtime())),
                        'type_file'  => $voucherTypeId, 'order_id' => $record->id,
                        'path'       => $data['voucher_file'], 'principal' => 1,
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                }
            }

            // Documentos anexos (principal=0)
            foreach (Master::where('main', 18)->get() as $m) {
                $fieldName = 'doc_' . $m->value;
                if (!empty($data[$fieldName])) {
                    $existing = OrderFile::where('order_id', $record->id)
                        ->where('type_file', $m->value)->where('principal', 0)->first();
                    if ($existing) {
                        $existing->update(['path' => $data[$fieldName], 'updated_by' => $user->id]);
                    } else {
                        OrderFile::create([
                            'id'         => abs(crc32($record->id . '-' . $m->value . '-' . microtime())),
                            'type_file'  => $m->value, 'order_id' => $record->id,
                            'path'       => $data[$fieldName], 'principal' => 0,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                    }
                }
            }
        });

        return $record->refresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FLOW HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveNextStatus(Order $record, int $currentStatus): int
    {
        if ($currentStatus === 5) {
            $obs = OrderHistory::where('order_id', $record->id)
                ->where('to_status', 5)->latest('id')->first();
            return $obs ? $obs->from_status : 1;
        }

        return match ($currentStatus) {
            0  => 1,
            1  => 2,
            2  => 3,
            3  => 6,
            6  => 7,
            7  => 8,
            8  => 9,
            9  => 91,
            91 => 92,
            92 => 10,
            default => $currentStatus,
        };
    }

    private function resolveToUser(int $nextStatus): string
    {
        return match ($nextStatus) {
            1  => 'AA',
            2  => 'GA',
            3  => 'GF',
            6  => 'AF',
            7  => 'AA',
            8  => 'AF',
            9, 91, 92 => 'UC',
            10 => '',
            default => 'AA',
        };
    }

    private function resolveObserveToUser(string $role, int $status): string
    {
        return match (true) {
            $role === 'AA' && $status === 1  => 'JA',
            $role === 'GA'                   => 'AA',
            $role === 'GF'                   => 'GA',
            $role === 'AA' && $status === 7  => 'AF',
            $role === 'AF'                   => 'AA',
            $role === 'UC'                   => 'AA',
            default                          => 'AA',
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HEADER ACTIONS
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $user        = auth()->user();
        $order       = $this->record;
        $role        = $user->user_type;
        $level       = $user->uc_level ?? 0;
        $status      = (int) $order->status;
        $roleLabels  = UserType::pluck('description', 'prefijo')->toArray();

        $actions = [
            // Historial
            Action::make('history')
                ->label('Historial')->icon('heroicon-o-clock')->color('gray')
                ->modalHeading('Historial de movimientos')
                ->modalContent(fn () => new HtmlString($this->buildHistoryHtml($order)))
                ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')->modalWidth('4xl'),

            // Cancelar
            Action::make('cancel')
                ->label('← Cancelar')->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];

        // ── OBSERVE (roles that can observe) ──────────────────────────────────
        $canObserve = match ($role) {
            'AA' => in_array($status, [1, 7]),
            'GA' => $status === 2,
            'GF' => $status === 3,
            'AF' => $status === 8,
            'UC' => in_array($status, [9, 91, 92]),
            default => false,
        };

        if ($canObserve) {
            $obsToUser = $this->resolveObserveToUser($role, $status);
            $actions[] = Action::make('observe')
                ->label('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')
                ->modalSubmitActionLabel('Enviar observación')
                ->form([
                    Select::make('obs_type')
                        ->label('Tipo de observación')
                        ->options(Master::where('main', 20)->orderBy('value')->pluck('description', 'value'))
                        ->required()->searchable(),
                    Textarea::make('obs_comment')
                        ->label('Comentario')->required()->rows(3)
                        ->placeholder('Detalla la observación encontrada...'),
                ])
                ->action(function (array $data) use ($user, $order, $role, $status, $obsToUser) {
                    $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
                    $comment = '[' . $desc . '] ' . $data['obs_comment'];
                    DB::transaction(function () use ($order, $user, $role, $status, $obsToUser, $comment) {
                        OrderHistory::create([
                            'from_user'   => $role,   'to_user' => $obsToUser,
                            'from_status' => $status, 'to_status' => 5,
                            'coment'      => $comment, 'order_id' => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Observación registrada')->warning()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── GA: RECHAZAR + APROBAR ─────────────────────────────────────────────
        if ($role === 'GA' && in_array($status, [2, 5])) {
            $actions[] = Action::make('ga_reject')
                ->label('Rechazar')->icon('heroicon-o-x-circle')->color('danger')
                ->modalHeading('Rechazar orden')->modalSubmitActionLabel('Confirmar rechazo')
                ->form([
                    Textarea::make('reject_reason')->label('Motivo del rechazo')
                        ->required()->rows(3)->placeholder('Indica el motivo...'),
                ])
                ->action(function (array $data) use ($order, $user, $status) {
                    DB::transaction(function () use ($order, $user, $status, $data) {
                        OrderHistory::create([
                            'from_user' => 'GA', 'to_user' => 'AA',
                            'from_status' => $status, 'to_status' => 4,
                            'coment' => $data['reject_reason'], 'order_id' => $order->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 4, 'motive_cancelation' => $data['reject_reason'], 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden rechazada')->danger()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });

            $actions[] = Action::make('ga_approve')
                ->label('Aprobar →')->icon('heroicon-o-check-circle')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar orden')
                ->modalDescription('La orden pasará a ' . ($roleLabels['GF'] ?? 'GF') . ' para su revisión.')
                ->action(function () use ($order, $user, $status, $roleLabels) {
                    $formData = $this->form->getState();
                    DB::transaction(function () use ($order, $user, $status, $formData) {
                        $this->saveGAOrderData($order, $formData, $user);
                        OrderHistory::create([
                            'from_user' => 'GA', 'to_user' => 'GF',
                            'from_status' => $status, 'to_status' => 3,
                            'coment' => '', 'order_id' => $order->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 3, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden aprobada — enviada a ' . ($roleLabels['GF'] ?? 'GF'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── GF: APROBAR (con cuenta de origen) ────────────────────────────────
        if ($role === 'GF' && in_array($status, [3, 5])) {
            $company = Company::find($order->company_id);
            $accountInfo = $company
                ? ($company->source_bank ? $company->source_bank . ' · N° ' . $company->source_account_number . ($company->source_cci ? ' · CCI ' . $company->source_cci : '') : null)
                : null;

            $actions[] = Action::make('gf_approve')
                ->label('Aprobar →')->icon('heroicon-o-check-circle')->color('success')
                ->modalHeading('Aprobar orden — Cuenta de origen')
                ->modalSubmitActionLabel('Confirmar y aprobar')
                ->form([
                    \Filament\Forms\Components\Placeholder::make('account_display')
                        ->label('Cuenta bancaria de salida')
                        ->content($accountInfo
                            ? new HtmlString('<span style="font-weight:600;color:#1e293b">' . e($accountInfo) . '</span>')
                            : new HtmlString('<span style="color:#dc2626;font-weight:600">⛔ Esta empresa no tiene cuenta de origen configurada. Configure la cuenta en el módulo de Empresas antes de aprobar.</span>')),
                    TextInput::make('source_account_ref')
                        ->label('Referencia / N° de operación')
                        ->placeholder('Ej. 0012345678')
                        ->required((bool) $accountInfo)
                        ->maxLength(200)
                        ->hidden(!$accountInfo),
                ])
                ->action(function (array $data, \Filament\Actions\Action $action) use ($order, $user, $status, $accountInfo, $roleLabels) {
                    if (!$accountInfo) {
                        Notification::make()
                            ->title('Sin cuenta de origen')
                            ->body('Configure la cuenta bancaria de la empresa antes de aprobar.')
                            ->danger()->send();
                        $action->halt();
                        return;
                    }
                    DB::transaction(function () use ($order, $user, $status, $data) {
                        $order->detail()->updateOrCreate(
                            ['order_id' => $order->id],
                            ['source_account' => $data['source_account_ref'] ?? null, 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user'   => 'GF', 'to_user' => 'AF',
                            'from_status' => $status, 'to_status' => 6,
                            'coment'      => 'Cuenta origen: ' . ($data['source_account_ref'] ?? '—'),
                            'order_id'    => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 6, 'updated_by' => $user->id]);
                    });
                    Notification::make()
                        ->title('Orden aprobada — enviada a ' . ($roleLabels['AF'] ?? 'AF'))
                        ->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── AF@8: CONFORME ────────────────────────────────────────────────────
        if ($role === 'AF' && $status === 8) {
            $actions[] = Action::make('af_conforme')
                ->label('Conforme →')->icon('heroicon-o-check-badge')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar sustento')
                ->modalDescription('Se confirmará que el sustento de pago es correcto. La orden pasará a ' . ($roleLabels['UC'] ?? 'UC') . '.')
                ->action(function () use ($order, $user) {
                    DB::transaction(function () use ($order, $user) {
                        OrderHistory::create([
                            'from_user'   => 'AF', 'to_user' => 'UC',
                            'from_status' => 8, 'to_status' => 9,
                            'coment'      => '', 'order_id' => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 9, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Sustento confirmado — enviado a Contabilidad')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── UC nivel 1 (@9): INGRESAR CÓDIGO REGISTRO ─────────────────────────
        if ($role === 'UC' && $level === 1 && $status === 9) {
            $actions[] = Action::make('uc1_code')
                ->label('Ingresar código →')->icon('heroicon-o-hashtag')->color('primary')
                ->modalHeading('Código de Registro Contable')
                ->modalSubmitActionLabel('Guardar y continuar')
                ->form([
                    TextInput::make('codigo_registro')
                        ->label('Código de Registro Contable')
                        ->required()->maxLength(100)
                        ->placeholder('Ej. REG-2026-00123')
                        ->default($order->detail?->codigo_registro),
                ])
                ->action(function (array $data) use ($order, $user) {
                    DB::transaction(function () use ($order, $user, $data) {
                        $order->detail()->updateOrCreate(
                            ['order_id' => $order->id],
                            ['codigo_registro' => $data['codigo_registro'], 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user'   => 'UC', 'to_user' => 'UC',
                            'from_status' => 9, 'to_status' => 91,
                            'coment'      => 'Cód. Registro: ' . $data['codigo_registro'],
                            'order_id'    => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 91, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Código de registro ingresado')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── UC nivel 2 (@91): INGRESAR CÓDIGO BANCO ───────────────────────────
        if ($role === 'UC' && $level === 2 && $status === 91) {
            $actions[] = Action::make('uc2_code')
                ->label('Ingresar código →')->icon('heroicon-o-hashtag')->color('primary')
                ->modalHeading('Código de Banco')
                ->modalSubmitActionLabel('Guardar y continuar')
                ->form([
                    TextInput::make('codigo_banco')
                        ->label('Código de Banco')
                        ->required()->maxLength(100)
                        ->placeholder('Ej. BAN-2026-00456')
                        ->default($order->detail?->codigo_banco),
                ])
                ->action(function (array $data) use ($order, $user) {
                    DB::transaction(function () use ($order, $user, $data) {
                        $order->detail()->updateOrCreate(
                            ['order_id' => $order->id],
                            ['codigo_banco' => $data['codigo_banco'], 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user'   => 'UC', 'to_user' => 'UC',
                            'from_status' => 91, 'to_status' => 92,
                            'coment'      => 'Cód. Banco: ' . $data['codigo_banco'],
                            'order_id'    => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 92, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Código de banco ingresado')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── UC nivel 3 (@92): TERMINAR ────────────────────────────────────────
        if ($role === 'UC' && $level === 3 && $status === 92) {
            $actions[] = Action::make('uc3_finish')
                ->label('Terminar proceso →')->icon('heroicon-o-check-badge')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Cerrar orden')
                ->modalDescription('Se marcará la orden como CERRADA. Esta acción no se puede deshacer.')
                ->action(function () use ($order, $user) {
                    DB::transaction(function () use ($order, $user) {
                        OrderHistory::create([
                            'from_user'   => 'UC', 'to_user' => '',
                            'from_status' => 92, 'to_status' => 10,
                            'coment'      => 'Proceso contable completado.',
                            'order_id'    => $order->id,
                            'created_by'  => $user->id, 'updated_by' => $user->id,
                        ]);
                        $order->update(['status' => 10, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden cerrada exitosamente')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // ── GUARDAR Y ENVIAR (solo roles que usan el formulario) ──────────────
        $showSave = match (true) {
            $role === 'JA' && $status === 5                      => true,
            $role === 'AA' && in_array($status, [1, 5, 7])      => true,
            $role === 'AF' && $status === 6                      => true,
            default                                              => false,
        };

        if ($showSave) {
            $saveLabel = $status === 5 ? 'Guardar y reenviar →' : 'Guardar y enviar →';
            $actions[] = Action::make('save')
                ->label($saveLabel)->color('primary')
                ->action(fn () => $this->save());
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STATUS META (local — shadows trait)
    // ──────────────────────────────────────────────────────────────────────────

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
            default => 'gray',
        };
        return [$label, $color];
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

    // ──────────────────────────────────────────────────────────────────────────
    // FILAMENT OVERRIDES
    // ──────────────────────────────────────────────────────────────────────────

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
