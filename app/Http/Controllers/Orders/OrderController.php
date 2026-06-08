<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Format;
use App\Models\Area;
use App\Models\PaymentSchedule;
use App\Models\Status;
use App\Models\Company;
use App\Models\Sede;
use App\Models\Master;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\OrderDetail;
use App\Models\OrderProduct;
use App\Models\OrderFile;
use App\Models\OrderQuota;
use App\Models\OrderHistory;
use App\Models\OrderSequence;
use App\Models\TypeUser;
use App\Models\UserType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{   


    public function create()
    {
        if (!in_array(auth()->user()->user_type, ['JA', 'AA', 'GA'], true)) {
            abort(403);
        }

        return view('Orders.create', $this->formData() + [
            'order'     => null,
            'prefill'   => null,
            'acts'      => ['approve' => false, 'observe' => false, 'reject' => false],
            'obsTypes'  => collect(),
            'orderMeta' => null,
        ]);
    }

    /** Listas de opciones compartidas por create() y edit(). */
    private function formData(): array
    {
        return [
            'companies'     => Company::orderBy('name')->pluck('name', 'id'),
            'formats'       => Format::orderBy('description')->pluck('description', 'id'),
            'sedes'         => Sede::orderBy('nombre')->pluck('nombre', 'id'),
            'areas'         => Area::orderBy('description')->pluck('description', 'id'),
            'schedules'     => PaymentSchedule::orderBy('name')->pluck('name', 'id'),
            'monedas'       => $this->getMonedas(),
            'paymentOpts'   => $this->masterOptions(4),
            'conditionOpts' => $this->masterOptions(8),
            'discountOpts'  => $this->masterOptions(12),
            'voucherTypes'  => Master::where('main', 15)->whereNotNull('description')->orderBy('description')->pluck('description', 'value'),
            'attachTypes'   => Master::where('main', 18)->whereNotNull('description')->orderBy('description')->pluck('description', 'value'),
            'banks'         => Master::where('main', 67)->whereNotNull('description')->orderBy('description')->pluck('description', 'id'),
            'fraccionadoIds' => Master::where('main', 8)->whereNotNull('description')
                ->get()->filter(fn ($m) => stripos($m->description, 'fraccionado') !== false)
                ->pluck('id')->values(),
        ];
    }

    /** Vista de solo lectura con toda la información de la orden. */
    public function show($order)
    {
        $order = Order::with(['detail.supplier.accounts', 'company', 'products', 'files', 'quotas', 'responsible'])
            ->findOrFail($order);

        $d      = $order->detail;
        $format = Format::where('abrev', $order->format_id)->first();
        $cur    = ($d?->currency === 'USD') ? '$' : 'S/';

        $ccNames = ($d && $d->cc_id)
            ? DB::table('cost_centers')->whereIn('id', (array) $d->cc_id)->pluck('description')->toArray()
            : [];

        $voucherLabels = Master::where('main', 15)->pluck('description', 'value');
        $attachLabels  = Master::where('main', 18)->pluck('description', 'value');
        $isComprobante = fn ($f) => $f->document_number || $f->amount || $f->emission_date;

        $vm = [
            'empresa'       => $order->company?->name ?? '—',
            'formato'       => $format?->description ?? $order->format_id,
            'categoria'     => Category::find($d?->category_id)?->description ?? '—',
            'moneda'        => $d?->currency ?? '—',
            'titulo'        => $order->title,
            'sede'          => Sede::find($d?->sede_id)?->nombre ?? '—',
            'area'          => Area::find($d?->area_id)?->description ?? '—',
            'centros'       => $ccNames,
            'justificacion' => $d?->justification ?? '—',
            'responsable'   => $order->responsible?->name ?? '—',
            'creador'       => User::find($order->created_by)?->name ?? '—',
            'formaPago'     => Master::find($d?->payment_id)?->description ?? '—',
            'condicion'     => Master::find($d?->condition_payment)?->description ?? '—',
            'vencimiento'   => $d?->expiration_date ? \Carbon\Carbon::parse($d->expiration_date)->format('d/m/Y') : '—',
            'programacion'  => PaymentSchedule::find($order->payment_schedule_id ?? $d?->payment_schedule_id)?->name ?? '—',
            'descuentoTipo' => Master::find($d?->discount_type_id)?->description,
        ];

        $comprobantes = $order->files->filter($isComprobante)->map(fn ($f) => [
            'label'    => $voucherLabels[$f->type_file] ?? $f->type_file,
            'document' => $f->document_number,
            'amount'   => $f->amount,
            'date'     => $f->emission_date,
            'cod_reg'  => $f->registration_code,
            'subida'   => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : null,
            'path'     => $f->path,
        ])->values();

        $documentos = $order->files->reject($isComprobante)->map(fn ($f) => [
            'label'      => $attachLabels[$f->type_file] ?? $f->type_file,
            'comentario' => $f->comentario,
            'subida'     => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : null,
            'path'       => $f->path,
        ])->values();

        $statusLabel = Status::find($order->status)?->description ?? $order->status;
        $statusClass = $this->statusClass((int) $order->status);

        return view('Orders.show', [
            'order'           => $order,
            'd'               => $d,
            'vm'              => $vm,
            'cur'             => $cur,
            'statusLabel'     => $statusLabel,
            'statusClass'     => $statusClass,
            'supplier'        => $d?->supplier,
            'selectedAccount' => $d?->supplier_account_id,
            'comprobantes'    => $comprobantes,
            'documentos'      => $documentos,
            'cuotas'          => $order->quotas->sortBy('quota_number')->values(),
            'canEdit'         => $this->canEditRecord($order),
        ]);
    }

    /** Formulario de edición con datos precargados. */
    public function edit($order)
    {
        $order = Order::with(['detail.supplier.accounts', 'products', 'files', 'quotas', 'paymentSchedule'])->findOrFail($order);
        if (!$this->canEditRecord($order)) {
            abort(403);
        }

        $d      = $order->detail;
        $format = Format::where('abrev', $order->format_id)->first();

        // Opciones dependientes ya resueltas (categoría por tipo, CC por área)
        $categoryOptions = $format
            ? Category::where('format_id', $format->id)->orderBy('description')->get(['id', 'description'])
            : collect();
        $ccOptions = $d?->area_id
            ? (Area::find($d->area_id)?->costCenters()->orderBy('cost_centers.description')->get(['cost_centers.id', 'cost_centers.description']) ?? collect())
            : collect();

        // Archivos: comprobantes (con metadatos) vs documentos anexos
        $voucherLabels = Master::where('main', 15)->pluck('description', 'value');
        $attachLabels  = Master::where('main', 18)->pluck('description', 'value');
        $isComprobante = fn ($f) => $f->document_number || $f->amount || $f->emission_date;

        $supplier = $d?->supplier;

        $prefill = [
            'company_id'          => $order->company_id,
            'format_id'           => $format?->id,
            'category_id'         => $d?->category_id,
            'currency'            => $d?->currency,
            'title'               => $order->title,
            'sede_id'             => $d?->sede_id,
            'area_id'             => $d?->area_id,
            'justification'       => $d?->justification,
            'grabable'            => (bool) ($d?->grabable),
            'apply_discount'      => floatval($d?->discount ?? 0) > 0,
            'discount_type_id'    => $d?->discount_type_id,
            'payment_id'          => $d?->payment_id,
            'condition_payment'   => $d?->condition_payment,
            'expiration_date'     => $d?->expiration_date ? \Carbon\Carbon::parse($d->expiration_date)->format('Y-m-d') : null,
            'payment_schedule_id' => $order->payment_schedule_id ?? $d?->payment_schedule_id,
            'observation'         => $d?->observation,

            'categoryOptions'     => $categoryOptions,
            'ccOptions'           => $ccOptions,
            'ccSelected'          => array_map('strval', (array) ($d?->cc_id ?? [])),

            'supplier' => $supplier ? [
                'id'       => $supplier->id,
                'ruc'      => $supplier->ruc,
                'name'     => $supplier->name,
                'address'  => $supplier->address,
                'district' => $supplier->district,
                'contact'  => $supplier->contact,
                'email'    => $supplier->email,
                'accounts' => $supplier->accounts->map(fn ($a) => [
                    'id' => $a->id, 'bank' => $a->bank, 'currency' => $a->currency,
                    'account_number' => $a->account_number, 'cci' => $a->cci, 'is_primary' => (bool) $a->is_primary,
                ])->values(),
            ] : null,
            'supplierAccountId'   => $d?->supplier_account_id,

            'items' => $order->products->map(fn ($p) => [
                'description' => $p->description,
                'quantity'    => (float) $p->quantity,
                'unit_price'  => (float) $p->unit_price,
            ])->values(),

            'comprobantes' => $order->files->filter($isComprobante)->map(fn ($f) => [
                'id'              => $f->id,
                'type_file'       => $f->type_file,
                'type_file_label' => $voucherLabels[$f->type_file] ?? $f->type_file,
                'document_number' => $f->document_number,
                'amount'          => $f->amount,
                'emission_date'   => $f->emission_date,
                'path'            => $f->path,
            ])->values(),

            'documentos' => $order->files->reject($isComprobante)->map(fn ($f) => [
                'id'         => $f->id,
                'type'       => $f->type_file,
                'type_label' => $attachLabels[$f->type_file] ?? $f->type_file,
                'comentario' => $f->comentario,
                'path'       => $f->path,
            ])->values(),

            'planCuotas' => $order->quotas->sortBy('quota_number')->map(fn ($q) => [
                'numero'            => $q->quota_number,
                'fecha_vencimiento' => $q->due_date ? \Carbon\Carbon::parse($q->due_date)->format('Y-m-d') : null,
                'monto'            => (float) $q->amount,
            ])->values(),
        ];

        $acts       = $this->orderActions($order);
        $restricted = $this->isRestrictedEdit($order);
        $obsTypes  = Master::where('main', 20)->whereNotNull('description')->orderBy('value')->pluck('description', 'value');
        $orderMeta = [
            'creador'     => User::find($order->created_by)?->name ?? '—',
            'fecha'       => $order->created_at?->format('d/m/Y'),
            'statusLabel' => Status::find($order->status)?->description ?? $order->status,
            'statusClass' => $this->statusClass((int) $order->status),
        ];

        // Datos de la observación (cuando la orden está OBSERVADA): motivo, quién, cuándo
        $obsInfo = null;
        if ((int) $order->status === 5) {
            $obs = OrderHistory::where('order_id', $order->id)->where('to_status', 5)->latest('id')->first();
            if ($obs) {
                // El comentario se guarda como "[Tipo] detalle"; lo separamos para mostrarlo limpio.
                $tipo    = null;
                $detalle = (string) $obs->coment;
                if (preg_match('/^\[(.*?)\]\s*(.*)$/s', $obs->coment ?? '', $m)) {
                    $tipo    = $m[1];
                    $detalle = $m[2];
                }
                $obsInfo = [
                    'tipo'     => $tipo,
                    'detalle'  => $detalle !== '' ? $detalle : '—',
                    'usuario'  => User::find($obs->created_by)?->name ?? '—',
                    'rol'      => UserType::where('prefijo', $obs->from_user)->value('description') ?? $obs->from_user,
                    'fecha'    => $obs->created_at ? \Carbon\Carbon::parse($obs->created_at)->format('d/m/Y H:i') : '—',
                ];
            }
        }

        return view('Orders.create', $this->formData() + compact('order', 'prefill', 'acts', 'restricted', 'obsTypes', 'orderMeta', 'obsInfo'));
    }

    /** Guarda los cambios de una orden y la avanza en el flujo (porta saveFullOrder). */
    public function update(Request $request, $order)
    {
        $order = Order::with(['detail', 'products', 'paymentSchedule'])->findOrFail($order);
        $user  = auth()->user();
        if (!$this->canEditRecord($order)) {
            abort(403);
        }

        // Edición restringida (observación post-aprobación hacia el AA): se ignora
        // todo lo que envíe el form para Proveedor, Detalle y Condición de pago;
        // esos valores se fuerzan desde la BD para que no puedan alterarse.
        $restricted = $this->isRestrictedEdit($order);
        if ($restricted) {
            $d = $order->detail;
            $request->merge([
                'supplier_id'         => $d?->supplier_id,
                'supplier_account_id' => $d?->supplier_account_id,
                'currency'            => $d?->currency,
                'grabable'            => $d?->grabable ? '1' : null,
                'apply_discount'      => floatval($d?->discount ?? 0) > 0 ? '1' : null,
                'discount_type_id'    => $d?->discount_type_id,
                'payment_id'          => $d?->payment_id,
                'condition_payment'   => $d?->condition_payment,
                'payment_schedule_id' => $order->payment_schedule_id ?? $d?->payment_schedule_id,
                'expiration_date'     => $d?->expiration_date ? \Carbon\Carbon::parse($d->expiration_date)->format('Y-m-d') : null,
                'observation'         => $d?->observation,
                'quotas'              => $d?->quotas,
                'items'               => $order->products->map(fn ($p) => [
                    'description' => $p->description,
                    'quantity'    => (float) $p->quantity,
                    'unit_price'  => (float) $p->unit_price,
                ])->values()->all(),
            ]);
        }

        $data = $request->validate([
            'company_id'          => ['required'],
            'format_id'           => ['required'],
            'category_id'         => ['required'],
            'currency'            => ['required'],
            'title'               => ['required', 'max:250'],
            'sede_id'             => ['required'],
            'area_id'             => ['required'],
            'cc_ids'              => ['required', 'array', 'min:1'],
            'justification'       => ['required', 'max:500'],
            'supplier_id'         => ['required'],
            'supplier_account_id' => ['nullable'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'numeric', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'payment_id'          => ['required'],
            'condition_payment'   => ['required'],
            'payment_schedule_id' => ['required'],
            'expiration_date'     => ['nullable', 'date'],
            'discount_type_id'    => ['nullable'],
            'quotas'              => ['nullable'],
            'plan_cuotas'         => ['nullable'],
            'observation'         => ['nullable'],
            'keep_files'          => ['nullable', 'array'],
            'comprobantes'        => ['nullable', 'array'],
            'documentos'          => ['nullable', 'array'],
        ]);

        $format = Format::findOrFail($data['format_id']);

        DB::transaction(function () use ($order, $user, $data, $format, $request, $restricted) {
            $this->syncSequences(['orders', 'order_details', 'order_products', 'order_history', 'orders_quotas']);

            // Totales
            $items = collect($data['items'])->filter(fn ($i) => !empty($i['description']))->values();
            $subtotal = $items->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
            $igv      = $request->boolean('grabable') ? round($subtotal * 0.18, 2) : 0;
            $total    = round($subtotal + $igv, 2);
            $discount = 0;
            $amtNeto  = $total;
            if ($request->boolean('apply_discount') && !empty($data['discount_type_id'])) {
                $pct      = floatval($data['discount_type_id']);
                $discount = round($total * $pct / 100, 2);
                $amtNeto  = round($total - $discount, 2);
            }

            // Avance de flujo
            $currentStatus = (int) $order->status;
            $nextStatus    = $this->resolveNextStatus($order, $currentStatus);
            $toUser        = $this->resolveToUser($order, $nextStatus);

            $order->update([
                'company_id'          => $data['company_id'],
                'title'               => $data['title'],
                'format_id'           => $format->abrev,
                'payment_schedule_id' => $data['payment_schedule_id'],
                'status'              => $nextStatus,
                'updated_by'          => $user->id,
            ]);

            $order->detail()->updateOrCreate(['order_id' => $order->id], [
                'required_date'       => now()->toDateString(),
                'period'              => now()->format('Ym'),
                'suggested_amount'    => $total,
                'justification'       => $data['justification'],
                'category_id'         => $data['category_id'],
                'currency'            => $data['currency'],
                'area_id'             => $data['area_id'],
                'sede_id'             => $data['sede_id'],
                'cc_id'               => $data['cc_ids'],
                'supplier_id'         => $data['supplier_id'],
                'supplier_account_id' => $data['supplier_account_id'] ?? null,
                'payment_id'          => $data['payment_id'],
                'payment_schedule_id' => $data['payment_schedule_id'],
                'condition_payment'   => $data['condition_payment'],
                'quotas'              => $data['quotas'] ?? 1,
                'expiration_date'     => $data['expiration_date'] ?? null,
                'discount'            => $discount,
                'discount_type_id'    => $data['discount_type_id'] ?? null,
                'igv'                 => $igv,
                'sub_total'           => round($subtotal, 2),
                'total'               => $total,
                'amount_neto'         => $amtNeto,
                'grabable'            => $request->boolean('grabable') ? 1 : 0,
                'items'               => null,
                'observation'         => $data['observation'] ?? null,
                'updated_by'          => $user->id,
            ]);

            // Productos (reemplazar)
            OrderProduct::where('order_id', $order->id)->delete();
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

            // Historial — avance de flujo
            OrderHistory::create([
                'from_user'   => $user->user_type, 'to_user'   => $toUser,
                'from_status' => $currentStatus,   'to_status' => $nextStatus,
                'coment'      => '', 'order_id' => $order->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            // Archivos: borrar los existentes que el usuario quitó, conservar el resto.
            // (id es INTEGER → centinela numérico 0, que no coincide con ningún id real.)
            $keep = array_map('intval', (array) ($data['keep_files'] ?? []));
            // Nunca eliminar comprobantes que ya tienen Código de Registro (UC1 ya los procesó)
            $coded = OrderFile::where('order_id', $order->id)->whereNotNull('registration_code')->pluck('id')->all();
            $keep  = array_values(array_unique(array_merge($keep, array_map('intval', $coded))));
            OrderFile::where('order_id', $order->id)
                ->whereNotIn('id', $keep ?: [0])
                ->delete();

            // Agregar los nuevos
            foreach ($request->input('comprobantes', []) as $i => $cb) {
                $path = null;
                if ($file = $request->file("comprobantes.$i.path")) {
                    $path = $file->store('orders/vouchers', 'public');
                }
                OrderFile::create([
                    'id'              => $this->fileId($order->id, "cb-$i"),
                    'type_file'       => $cb['type_file'] ?? null,
                    'document_number' => $cb['document_number'] ?? null,
                    'amount'          => $cb['amount'] ?? null,
                    'emission_date'   => $cb['emission_date'] ?? null,
                    'order_id'        => $order->id,
                    'path'            => $path,
                    'principal'       => 1,
                    'created_by'      => $user->id, 'updated_by' => $user->id,
                ]);
            }
            foreach ($request->input('documentos', []) as $i => $dn) {
                if (!($file = $request->file("documentos.$i.path"))) {
                    continue;
                }
                OrderFile::create([
                    'id'         => $this->fileId($order->id, "dn-$i"),
                    'type_file'  => $dn['type'] ?? null,
                    'order_id'   => $order->id,
                    'path'       => $file->store('orders/docs', 'public'),
                    'comentario' => $dn['comentario'] ?? null,
                    'principal'  => 0,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            // Cuotas (reemplazar) — en edición restringida se conservan intactas:
            // los abonos ya están en el ciclo de pago y no deben recrearse.
            if (!$restricted) {
                OrderQuota::where('order_id', $order->id)->delete();
                $this->procesarCuotas($order->refresh(), $data, $user);
            }
        });

        return response()->json($this->setRpta(1, 'Orden actualizada y reenviada', [
            'id'       => $order->id,
            'code'     => $order->code,
            'redirect' => route('orders.view'),
        ]));
    }

    /** ¿La orden es de programación URGENTE? (si no, es GENERAL). */
    private function isUrgente(Order $order): bool
    {
        $name = $order->paymentSchedule?->name ?? '';
        return stripos($name, 'urgente') !== false;
    }

    /** ¿Todos los abonos de la orden están en [202] (pagados con constancia)? */
    private function abonosCompletos(Order $order): bool
    {
        $total = $order->quotas->count();
        return $total > 0 && $order->quotas->where('status', 202)->count() === $total;
    }

    /**
     * Estado siguiente cuando se EDITA y reenvía (form de edición).
     * Solo lo usan los editores: AA@1, GA@2 y correcciones de OBSERVADO (5).
     */
    private function resolveNextStatus(Order $order, int $currentStatus): int
    {
        // OBSERVADO: vuelve al estado desde el que se observó
        if ($currentStatus === 5) {
            $obs = OrderHistory::where('order_id', $order->id)->where('to_status', 5)->latest('id')->first();
            return $obs ? (int) $obs->from_status : 1;
        }

        $urgente = $this->isUrgente($order);
        return match ($currentStatus) {
            0 => 1,
            1 => 2,                              // AA completa → GA revisa
            2 => $urgente ? 3 : 100,             // GA aprueba → GF (urgente) / UC1 (general)
            default => $currentStatus,
        };
    }

    /** Rol que ACTÚA en un estado (programación-aware). Usado para history.to_user. */
    private function resolveToUser(Order $order, int $nextStatus): string
    {
        $urgente = $this->isUrgente($order);
        return match ($nextStatus) {
            1   => 'AA',
            2   => 'GA',
            3   => 'GF',                         // urgente
            8   => 'AF',                         // urgente
            9   => 'UC1',                        // urgente
            100 => 'UC1',                        // general
            101 => 'UC5',                        // general
            55  => 'UC2',                        // general
            91  => $urgente ? 'UC2' : 'UC3',
            92  => $urgente ? 'UC3' : 'UC4',
            102 => $urgente ? 'AA' : 'GF',
            4, 10 => '',
            default => 'AA',
        };
    }

    /** ¿El estado pertenece al bloque de APROBACIÓN (pre-102, montos editables)? */
    private function isApprovalState(Order $order, int $status): bool
    {
        $urgente = $this->isUrgente($order);
        return $urgente
            ? in_array($status, [1, 2, 3], true)
            : in_array($status, [1, 2, 100, 91, 101], true);
    }

    /**
     * Guarda la orden completa (orden + detalle + ítems + archivos + cuotas + historial).
     * Lógica portada de Filament CreateOrder::createFull.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['JA', 'AA', 'GA'])) {
            abort(403);
        }

        $data = $request->validate([
            'company_id'          => ['required'],
            'format_id'           => ['required'],
            'category_id'         => ['required'],
            'currency'            => ['required'],
            'title'               => ['required', 'max:250'],
            'sede_id'             => ['required'],
            'area_id'             => ['required'],
            'cc_ids'              => ['required', 'array', 'min:1'],
            'justification'       => ['required', 'max:500'],
            'supplier_id'         => ['required'],
            'supplier_account_id' => ['nullable'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'numeric', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'payment_id'          => ['required'],
            'condition_payment'   => ['required'],
            'payment_schedule_id' => ['required'],
            'expiration_date'     => ['nullable', 'date'],
            'discount_type_id'    => ['nullable'],
            'quotas'              => ['nullable'],
            'plan_cuotas'         => ['nullable'],
            'observation'         => ['nullable'],
            'comprobantes'        => ['nullable', 'array'],
            'documentos'          => ['nullable', 'array'],
        ]);

        $format = Format::findOrFail($data['format_id']);

        $order = DB::transaction(function () use ($data, $user, $format, $request) {
            // Evita choques de secuencias tras migrar de MySQL
            $this->syncSequences(['orders', 'order_details', 'order_products', 'order_history', 'orders_quotas']);

            $typeId = TypeUser::where('user_id', $user->id)->first()?->type_id;

            // Estado inicial + movimiento de historial según el rol que crea y la programación
            $urgente = stripos(PaymentSchedule::find($data['payment_schedule_id'])?->name ?? '', 'urgente') !== false;
            if ($user->user_type === 'GA') {
                // GA crea y aprueba en un paso → urgente: GF (3) / general: UC1 (100)
                $initialStatus = $urgente ? 3 : 100;
                $history = $urgente
                    ? ['from' => 'GA', 'to' => 'GF',  'from_s' => 2, 'to_s' => 3]
                    : ['from' => 'GA', 'to' => 'UC1', 'from_s' => 2, 'to_s' => 100];
            } else {
                $initialStatus = 2;
                $history = ['from' => 'AA', 'to' => 'GA', 'from_s' => 1, 'to_s' => 2];
            }

            // Totales
            $items = collect($data['items'])->filter(fn ($i) => !empty($i['description']))->values();
            $subtotal = $items->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
            $igv      = !empty($data['grabable'] ?? $request->input('grabable')) ? round($subtotal * 0.18, 2) : 0;
            $total    = round($subtotal + $igv, 2);
            $discount = 0;
            $amtNeto  = $total;
            if ($request->boolean('apply_discount') && !empty($data['discount_type_id'])) {
                $pct      = floatval($data['discount_type_id']);
                $discount = round($total * $pct / 100, 2);
                $amtNeto  = round($total - $discount, 2);
            }

            // Orden
            $order = Order::create([
                'company_id'          => $data['company_id'],
                'status'              => $initialStatus,
                'title'               => $data['title'],
                'type_id'             => $typeId,
                'format_id'           => $format->abrev,
                'payment_schedule_id' => $data['payment_schedule_id'],
                'user_responsible'    => TypeUser::where('type_id', $typeId)->inRandomOrder()->first()?->user_id ?? 0,
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);

            $this->generateCode($order, $format->abrev);

            // Detalle
            OrderDetail::create([
                'order_id'            => $order->id,
                'required_date'       => now()->toDateString(),
                'period'              => now()->format('Ym'),
                'expiration_date'     => $data['expiration_date'] ?? null,
                'suggested_amount'    => $total,
                'justification'       => $data['justification'],
                'category_id'         => $data['category_id'],
                'currency'            => $data['currency'],
                'area_id'             => $data['area_id'],
                'sede_id'             => $data['sede_id'],
                'cc_id'               => $data['cc_ids'],                 // casteado a array
                'supplier_id'         => $data['supplier_id'],
                'supplier_account_id' => $data['supplier_account_id'] ?? null,
                'payment_id'          => $data['payment_id'],
                'payment_schedule_id' => $data['payment_schedule_id'],
                'condition_payment'   => $data['condition_payment'],
                'quotas'              => $data['quotas'] ?? 1,
                'discount'            => $discount,
                'discount_type_id'    => $data['discount_type_id'] ?? null,
                'igv'                 => $igv,
                'sub_total'           => round($subtotal, 2),
                'total'               => $total,
                'amount_neto'         => $amtNeto,
                'grabable'            => $request->boolean('grabable') ? 1 : 0,
                'items'               => null,
                'observation'         => $data['observation'] ?? null,
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);

            // Ítems (productos)
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

            // Historial
            OrderHistory::create([
                'from_user'   => $history['from'], 'to_user'   => $history['to'],
                'from_status' => $history['from_s'], 'to_status' => $history['to_s'],
                'coment'      => '', 'order_id' => $order->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            // Comprobantes de pago (con metadatos)
            foreach ($request->input('comprobantes', []) as $i => $cb) {
                $path = null;
                if ($file = $request->file("comprobantes.$i.path")) {
                    $path = $file->store('orders/vouchers', 'public');
                }
                OrderFile::create([
                    'id'              => $this->fileId($order->id, "cb-$i"),
                    'type_file'       => $cb['type_file'] ?? null,
                    'document_number' => $cb['document_number'] ?? null,
                    'amount'          => $cb['amount'] ?? null,
                    'emission_date'   => $cb['emission_date'] ?? null,
                    'order_id'        => $order->id,
                    'path'            => $path,
                    'principal'       => $i == 0 ? 1 : 0,
                    'created_by'      => $user->id, 'updated_by' => $user->id,
                ]);
            }

            // Documentos anexos
            foreach ($request->input('documentos', []) as $i => $dn) {
                if (!($file = $request->file("documentos.$i.path"))) {
                    continue;
                }
                OrderFile::create([
                    'id'         => $this->fileId($order->id, "dn-$i"),
                    'type_file'  => $dn['type'] ?? null,
                    'order_id'   => $order->id,
                    'path'       => $file->store('orders/docs', 'public'),
                    'comentario' => $dn['comentario'] ?? null,
                    'principal'  => 0,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            // Cuotas
            $this->procesarCuotas($order, $data, $user);

            return $order;
        });

        return response()->json($this->setRpta(1, 'Orden creada correctamente', [
            'id'       => $order->id,
            'code'     => $order->code,
            'redirect' => route('orders.view'),
        ]));
    }

    // ── Helpers de guardado ──

    /** Genera el código correlativo de la orden (OC-2026000001). */
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

    /** ID manual para orders_file (no autoincremental, columna INTEGER). */
    private function fileId(int $orderId, string $tag): int
    {
        // crc32 puede exceder el rango INT de PostgreSQL; lo acotamos a 1..2147483646
        return (crc32($orderId . '-' . $tag . '-' . microtime(true) . '-' . random_int(1, 99999)) % 2147483646) + 1;
    }

    /** Crea las cuotas: por plan si es fraccionado, o una sola al contado. */
    private function procesarCuotas(Order $order, array $data, $user): void
    {
        $isFraccionado = false;
        if (!empty($data['condition_payment'])) {
            $cond = Master::find($data['condition_payment']);
            $isFraccionado = $cond && stripos($cond->description, 'fraccionado') !== false;
        }

        if ($isFraccionado && !empty($data['plan_cuotas'])) {
            $plan = is_string($data['plan_cuotas']) ? json_decode($data['plan_cuotas'], true) : $data['plan_cuotas'];
            foreach (($plan ?? []) as $cuota) {
                OrderQuota::create([
                    'order_id'     => $order->id,
                    'quota_number' => intval($cuota['numero'] ?? 1),
                    'amount'       => floatval($cuota['monto'] ?? 0),
                    'due_date'     => $cuota['fecha_vencimiento'] ?? null,
                    'status'       => 200,   // PENDIENTE_POR_DEPOSITO
                    'created_by'   => $user->id, 'updated_by' => $user->id,
                ]);
            }
        } else {
            OrderQuota::create([
                'order_id'     => $order->id,
                'quota_number' => 1,
                'amount'       => $order->detail?->amount_neto ?? 0,
                'due_date'     => $order->detail?->expiration_date ?? now()->addDays(30),
                'status'       => 200,
                'created_by'   => $user->id, 'updated_by' => $user->id,
            ]);
        }
    }

    /** Resincroniza secuencias de PostgreSQL (IDs desfasados tras migrar de MySQL). */
    private function syncSequences(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($tables as $t) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$t}','id'), GREATEST((SELECT COALESCE(MAX(id),0) FROM {$t}), 1))");
        }
    }

    /** Categorías de un tipo de orden (format_id) — select dependiente. */
    public function categories($format)
    {
        $list = Category::where('format_id', $format)
            ->orderBy('description')
            ->get(['id', 'description']);

        return response()->json($this->setRpta(1, 'OK', $list));
    }

    /** Buscar proveedor por RUC (autocompletado + cuentas). */
    public function searchSupplier(Request $request)
    {
        $supplier = Supplier::with('accounts')
            ->where('ruc', $request->input('ruc'))
            ->first();

        if (!$supplier) {
            return response()->json($this->setRpta(0, 'Proveedor no encontrado', null));
        }

        return response()->json($this->setRpta(1, 'OK', [
            'supplier' => [
                'id'       => $supplier->id,
                'ruc'      => $supplier->ruc,
                'name'     => $supplier->name,
                'address'  => $supplier->address,
                'district' => $supplier->district,
                'contact'  => $supplier->contact,
                'email'    => $supplier->email,
            ],
            'accounts' => $supplier->accounts->map(fn ($a) => [
                'id'             => $a->id,
                'bank'           => $a->bank,
                'currency'       => $a->currency,
                'account_number' => $a->account_number,
                'cci'            => $a->cci,
                'is_primary'     => (bool) $a->is_primary,
            ])->values(),
        ]));
    }

    /** Registrar un nuevo proveedor con sus cuentas bancarias. */
    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'ruc'                       => ['required', 'string', 'max:20'],
            'name'                      => ['required', 'string', 'max:250'],
            'address'                   => ['nullable', 'string', 'max:250'],
            'provincia'                 => ['nullable', 'string', 'max:100'],
            'district'                  => ['nullable', 'string', 'max:100'],
            'contact'                   => ['nullable', 'string', 'max:100'],
            'phone'                     => ['nullable', 'string', 'max:20'],
            'email'                     => ['nullable', 'email', 'max:100'],
            'accounts'                  => ['array'],
            'accounts.*.bank'           => ['required'],
            'accounts.*.currency'       => ['required'],
            'accounts.*.account_number' => ['required', 'string'],
            'accounts.*.cci'            => ['nullable', 'string'],
        ]);

        $uid = auth()->id();

        $supplier = DB::transaction(function () use ($data, $uid) {
            $supplier = Supplier::create([
                'ruc'        => $data['ruc'],
                'name'       => $data['name'],
                'address'    => $data['address'] ?? null,
                'provincia'  => $data['provincia'] ?? null,
                'district'   => $data['district'] ?? null,
                'contact'    => $data['contact'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'email'      => $data['email'] ?? null,
                'created_by' => $uid,
                'updated_by' => $uid,
            ]);

            foreach ($data['accounts'] ?? [] as $i => $acc) {
                $bankName = Master::find($acc['bank'])?->description ?? $acc['bank'];
                SupplierAccount::create([
                    'supplier_id'    => $supplier->id,
                    'bank'           => $bankName,
                    'currency'       => $acc['currency'],
                    'account_number' => $acc['account_number'],
                    'cci'            => $acc['cci'] ?? null,
                    'is_primary'     => $i === 0,
                    'created_by'     => $uid,
                    'updated_by'     => $uid,
                ]);
            }

            return $supplier->load('accounts');
        });

        return response()->json($this->setRpta(1, 'Proveedor registrado', [
            'supplier' => [
                'id'       => $supplier->id,
                'ruc'      => $supplier->ruc,
                'name'     => $supplier->name,
                'address'  => $supplier->address,
                'district' => $supplier->district,
                'contact'  => $supplier->contact,
                'email'    => $supplier->email,
            ],
            'accounts' => $supplier->accounts->map(fn ($a) => [
                'id'             => $a->id,
                'bank'           => $a->bank,
                'currency'       => $a->currency,
                'account_number' => $a->account_number,
                'cci'            => $a->cci,
                'is_primary'     => (bool) $a->is_primary,
            ])->values(),
        ]));
    }

    /** Centros de costo de un área (para el multi-select dependiente). */
    public function costCenters($area)
    {
        $list = Area::find($area)
            ?->costCenters()
            ->orderBy('cost_centers.description')
            ->get(['cost_centers.id', 'cost_centers.description']) ?? collect();

        return response()->json($this->setRpta(1, 'OK', $list));
    }

    /** Monedas desde Master (type MONEDA). Devuelve colección con value/description. */
    private function getMonedas()
    {
        $parentIds = Master::where('type', 'MONEDA')->whereNull('description')->pluck('id');
        return Master::whereIn('main', $parentIds)
            ->whereNotNull('description')->whereNotNull('value')
            ->get()->unique('value')->values();
    }

    /** Opciones [id => description] desde Master por main. */
    private function masterOptions(int $main)
    {
        return Master::where('main', $main)
            ->whereNotNull('description')
            ->orderBy('description')
            ->pluck('description', 'id');
    }

    /**
     * Mis Órdenes — trae el set de trabajo del usuario (filtrado por rol)
     * y lo manda a la vista. DataTables hace búsqueda/orden/paginación/filtros
     * en el navegador.
     */
    public function index()
    {
        $user = auth()->user();

        // Datos para los dropdowns de filtros
        $formats   = Format::orderBy('description')->get();
        $areas     = Area::orderBy('description')->get();
        $schedules = PaymentSchedule::orderBy('name')->get();
        $status    = $this->allowedStatusOptions($user);   // [id => description]
        $statusNames = Status::pluck('description', 'id');  // para etiquetas de la tabla

        // El set de órdenes que corresponde al usuario logueado
        $orders = $this->scopedOrders($user)->latest()->get();

        // Closures: editar (lápiz) y acciones de flujo (aprobar/observar) por orden
        $canEdit = fn (Order $o) => $this->canEditRecord($o);
        $actions = fn (Order $o) => $this->orderActions($o);

        // Tipos de observación (modal observar) y de comprobante (modal cargar documentos)
        $obsTypes     = Master::where('main', 20)->whereNotNull('description')->orderBy('value')->pluck('description', 'value');
        $voucherTypes = Master::where('main', 15)->whereNotNull('description')->orderBy('description')->pluck('description', 'value');

        return view('Orders.view', compact(
            'formats', 'areas', 'schedules', 'status', 'statusNames', 'orders', 'canEdit', 'actions', 'obsTypes', 'voucherTypes'
        ));
    }

    /**
     * Transición de AVANCE (aprobar/sustentar/conforme/código/cerrar/reenviar)
     * según rol + estado + programación. Devuelve un array con la acción o null.
     * kind: 'confirm' (un clic) | 'code' (ingresar código).
     */
    private function advanceTransition(Order $o): ?array
    {
        $role    = auth()->user()->user_type;
        $status  = (int) $o->status;
        $urgente = $this->isUrgente($o);

        $confirm = fn (string $label, int $next, string $msg) => [
            'kind' => 'confirm', 'label' => $label, 'next' => $next,
            'to' => $this->resolveToUser($o, $next), 'msg' => $msg,
        ];
        // mode: 'perdoc' → código por documento de pago (orders_file, modal nueva)
        //       'single' → un código a nivel de orden (orders_detail, modal simple)
        $code = fn (string $field, string $codeLabel, int $next, string $msg, string $mode = 'single') => [
            'kind' => 'code', 'label' => $codeLabel, 'codeLabel' => $codeLabel, 'field' => $field,
            'mode' => $mode, 'next' => $next, 'to' => $this->resolveToUser($o, $next), 'msg' => $msg,
        ];

        // OBSERVADO (5): el rol observado reenvía. Si NO es bloque de aprobación
        // (post-102), es un "Reenviar" simple (sin editar montos); el bloque de
        // aprobación se reenvía por el formulario de edición.
        if ($status === 5) {
            $obs = OrderHistory::where('order_id', $o->id)->where('to_status', 5)->latest('id')->first();
            if (!$obs) return null;
            $from = (int) $obs->from_status;
            if ($role === $obs->to_user && !$this->isApprovalState($o, $from)) {
                return $confirm('Reenviar', $from, 'Orden reenviada');
            }
            return null;
        }

        if ($role === 'GA' && $status === 2) {
            return $confirm('Aprobar', $urgente ? 3 : 100,
                $urgente ? 'Aprobada — enviada a Gerencia Financiera' : 'Aceptada — enviada a Contabilidad');
        }

        if ($urgente) {
            if ($role === 'GF'  && $status === 3)   return $confirm('Aprobar', 102, 'Aprobada — pasa a Cronograma de Pago');
            // AA sustenta solo cuando TODOS los abonos están pagados ([202])
            if ($role === 'AA'  && $status === 102 && $this->abonosCompletos($o)) return $confirm('Sustentar', 8, 'Orden sustentada — enviada a Finanzas (AF)');
            if ($role === 'AF'  && $status === 8)   return $confirm('Conforme', 9, 'Conforme — enviada a Contabilidad (UC1)');
            if ($role === 'UC1' && $status === 9)   return $code('codigo_registro', 'Código de Registro', 91, 'Orden pasa a Código de Banco', 'perdoc');
            if ($role === 'UC2' && $status === 91)  return $code('codigo_banco', 'Código de Banco', 92, 'Código de banco guardado');
            if ($role === 'UC3' && $status === 92)  return $confirm('Cerrar', 10, 'Orden cerrada');
        } else {
            if ($role === 'UC1' && $status === 100) return $code('codigo_registro', 'Código de Registro', 91, 'Orden pasa a Código de Banco', 'perdoc');
            if ($role === 'UC3' && $status === 91)  return $confirm('Aprobar', 101, 'Aprobada — enviada a UC5');
            if ($role === 'UC5' && $status === 101) return $confirm('Confirmar', 102, 'Confirmada — pasa a Cronograma de Pago');
            if ($role === 'UC2' && $status === 55)  return $code('codigo_banco', 'Código de Banco', 92, 'Código de banco guardado');
            if ($role === 'UC4' && $status === 92)  return $confirm('Cerrar', 10, 'Orden cerrada');
        }
        return null;
    }

    /** Rol al que se dirige una observación según rol + estado + programación. */
    private function observeTarget(Order $o): ?string
    {
        $role    = auth()->user()->user_type;
        $status  = (int) $o->status;
        $urgente = $this->isUrgente($o);

        if ($role === 'GA' && $status === 2) return 'AA';
        if ($urgente) {
            if ($role === 'GF'  && $status === 3)  return 'GA';   // urgente: GF observa → GA
            if ($role === 'AF'  && $status === 8)  return 'AA';
            if ($role === 'UC1' && $status === 9)  return 'AA';
            if ($role === 'UC2' && $status === 91) return 'AA';
            if ($role === 'UC3' && $status === 92) return 'AA';
        } else {
            if ($role === 'UC1' && $status === 100) return 'AA';
            if ($role === 'UC3' && $status === 91)  return 'AA';
            if ($role === 'UC5' && $status === 101) return 'AA';
            if ($role === 'UC2' && $status === 55)  return 'AA';
            if ($role === 'UC4' && $status === 92)  return 'AA';
        }
        return null;
    }

    /** Acciones de flujo disponibles para el usuario sobre una orden (botones del listado). */
    private function orderActions(Order $o): array
    {
        $t      = $this->advanceTransition($o);
        $role   = auth()->user()->user_type;
        $status = (int) $o->status;

        return [
            'approve'      => $t && $t['kind'] === 'confirm',
            'approveLabel' => ($t && $t['kind'] === 'confirm') ? $t['label'] : 'Aprobar',
            // Cierre del flujo (UC3/UC4 @92 → 10): se gestiona desde la Vista Contable
            'close'        => $t && $t['kind'] === 'confirm' && ($t['next'] ?? null) === 10,
            'code'         => $t && $t['kind'] === 'code',
            'codeLabel'    => ($t && $t['kind'] === 'code') ? $t['codeLabel'] : 'Código',
            'codeMode'     => ($t && $t['kind'] === 'code') ? ($t['mode'] ?? 'single') : null,
            'observe'      => $this->observeTarget($o) !== null,
            'reject'       => $role === 'GA' && $status === 2,
            // AA carga comprobantes en paralelo mientras la orden está en Cronograma de Pago (urgente)
            'docs'         => $role === 'AA' && $status === 102 && $this->isUrgente($o),
        ];
    }

    /** Avanza la orden (acciones de un clic: aprobar/sustentar/conforme/cerrar/reenviar). */
    public function approve($order)
    {
        $order = Order::with('paymentSchedule')->findOrFail($order);
        $user  = auth()->user();

        $t = $this->advanceTransition($order);
        if (!$t || $t['kind'] !== 'confirm') {
            abort(403);
        }
        $current = (int) $order->status;

        DB::transaction(function () use ($order, $user, $current, $t) {
            OrderHistory::create([
                'from_user' => $user->user_type, 'to_user' => $t['to'],
                'from_status' => $current, 'to_status' => $t['next'],
                'coment' => '', 'order_id' => $order->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $order->update(['status' => $t['next'], 'updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, $t['msg'], ['redirect' => route('orders.view')]));
    }

    /** Ingresa un código (registro/banco) y avanza la orden. */
    public function code(Request $request, $order)
    {
        $order = Order::with(['paymentSchedule', 'detail'])->findOrFail($order);
        $user  = auth()->user();

        $t = $this->advanceTransition($order);
        // Solo códigos a nivel de orden (single → order_details). El Código de Registro
        // es por documento (perdoc) y se guarda en su endpoint propio.
        if (!$t || $t['kind'] !== 'code' || ($t['mode'] ?? 'single') !== 'single') {
            abort(403);
        }
        $data = $request->validate(['codigo' => ['required', 'string', 'max:100']]);
        $current = (int) $order->status;

        DB::transaction(function () use ($order, $user, $current, $t, $data) {
            $order->detail()->updateOrCreate(
                ['order_id' => $order->id],
                [$t['field'] => $data['codigo'], 'updated_by' => $user->id]
            );
            OrderHistory::create([
                'from_user' => $user->user_type, 'to_user' => $t['to'],
                'from_status' => $current, 'to_status' => $t['next'],
                'coment' => $t['codeLabel'] . ': ' . $data['codigo'], 'order_id' => $order->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $order->update(['status' => $t['next'], 'updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, $t['msg'], ['redirect' => route('orders.view')]));
    }

    /** Observar una orden (→ estado 5, regresa al rol que corresponde). */
    public function observe(Request $request, $order)
    {
        $order = Order::with('paymentSchedule')->findOrFail($order);
        $user  = auth()->user();

        $toUser = $this->observeTarget($order);
        if ($toUser === null) {
            abort(403);
        }

        $data = $request->validate([
            'obs_type'    => ['required'],
            'obs_comment' => ['required', 'string'],
        ]);

        $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
        $comment = '[' . $desc . '] ' . $data['obs_comment'];
        $current = (int) $order->status;

        DB::transaction(function () use ($order, $user, $toUser, $current, $comment) {
            OrderHistory::create([
                'from_user' => $user->user_type, 'to_user' => $toUser,
                'from_status' => $current, 'to_status' => 5,
                'coment' => $comment, 'order_id' => $order->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $order->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Observación registrada', ['redirect' => route('orders.view')]));
    }

    /** GA rechaza definitivamente una orden (status 2 → 4, RECHAZADA). */
    public function reject(Request $request, $order)
    {
        $order = Order::findOrFail($order);
        $user  = auth()->user();
        if (!($user->user_type === 'GA' && (int) $order->status === 2)) {
            abort(403);
        }

        $data = $request->validate([
            'reject_reason' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($order, $user, $data) {
            OrderHistory::create([
                'from_user' => 'GA', 'to_user' => 'AA',
                'from_status' => 2, 'to_status' => 4,
                'coment' => $data['reject_reason'], 'order_id' => $order->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $order->update(['status' => 4, 'motive_cancelation' => $data['reject_reason'], 'updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Orden rechazada', [
            'redirect' => route('orders.view'),
        ]));
    }

    /**
     * ¿El usuario puede EDITAR los montos de la orden (form de edición)?
     * Solo los "editores" (AA, GA) y solo antes de [102]. Los validadores
     * (GF, UC*) no editan montos: solo aprueban/observan con botones.
     */
    private function canEditRecord(Order $record): bool
    {
        $role   = auth()->user()->user_type;
        $status = (int) $record->status;

        // OBSERVADO (5): edita montos solo el rol observado (AA/GA) Y solo si la
        // observación viene del bloque de aprobación (pre-102). Las observaciones
        // post-102 se reenvían con el botón "Reenviar" (sin editar montos).
        if ($status === 5) {
            $obs = OrderHistory::where('order_id', $record->id)
                ->where('to_status', 5)->latest('id')->first();
            if (!$obs) return false;
            if ($role !== $obs->to_user) return false;
            // GA solo edita observaciones del bloque de aprobación (pre-102).
            if ($role === 'GA') {
                return $this->isApprovalState($record, (int) $obs->from_status);
            }
            // AA edita siempre que sea el destinatario: edición plena si la
            // observación viene del bloque de aprobación, y edición RESTRINGIDA
            // (sin proveedor/detalle/condición de pago) si viene de AF o los UC
            // post-cronograma (montos ya pagados, no se pueden alterar).
            return $role === 'AA';
        }

        return match ($role) {
            'AA' => $status === 1,    // AA completa la orden creada
            'GA' => $status === 2,    // GA revisa/ajusta antes de aprobar
            default => false,
        };
    }

    /**
     * ¿La edición debe ser RESTRINGIDA? (observación post-aprobación hacia el AA:
     * se bloquean Proveedor, Detalle de la orden y Condición de pago, porque los
     * montos/cuotas ya están en el ciclo de pago y no pueden cambiar.)
     */
    private function isRestrictedEdit(Order $record): bool
    {
        if ((int) $record->status !== 5) return false;
        $obs = OrderHistory::where('order_id', $record->id)
            ->where('to_status', 5)->latest('id')->first();
        if (!$obs) return false;
        return auth()->user()->user_type === 'AA'
            && !$this->isApprovalState($record, (int) $obs->from_status);
    }

    /**
     * Órdenes Históricas — todas las órdenes (consulta).
     * AA solo ve las suyas (user_responsible); el resto de roles ve todo.
     */
    public function history()
    {
        $user = auth()->user();

        $query = Order::with(['company', 'detail', 'responsible', 'paymentSchedule']);
        if ($user->user_type === 'AA') {
            $query->where('user_responsible', $user->id);
        }
        $orders = $query->latest()->get();

        // Datos para filtros
        $formats     = Format::orderBy('description')->get();
        $areas       = Area::orderBy('description')->get();
        $schedules   = PaymentSchedule::orderBy('name')->get();
        $status      = Status::orderBy('id')->pluck('description', 'id');   // todos los estados
        $statusNames = Status::pluck('description', 'id');

        return view('Orders.history', compact('orders', 'formats', 'areas', 'schedules', 'status', 'statusNames'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CUENTAS POR PAGAR (ciclo de abonos — orders_quotas)
    // ──────────────────────────────────────────────────────────────────────────

    /** Vista de Cuentas por Pagar: abonos de las órdenes en fase de pago. */
    public function payable()
    {
        $user = auth()->user();
        $role = $user->user_type;

        // Roles del ciclo de pago: GF/AF operan; AA observa sus abonos (urgente); UC2 (general).
        if (!in_array($role, ['AA', 'UC2', 'GF', 'AF'], true)) {
            abort(403);
        }

        $query = Order::with(['quotas', 'company', 'paymentSchedule', 'detail'])
            ->whereIn('status', [102, 55]);

        if ($role === 'AA') {
            $query->where('user_responsible', $user->id)->where('status', 102);   // urgente: verifica/observa las suyas
        } elseif ($role === 'UC2') {
            $query->where('status', 55);                                           // general: revisa al completarse
        }
        // GF / AF ven todos los abonos en proceso.

        $orders = $query->latest()->get();

        $rows = [];
        foreach ($orders as $o) {
            foreach ($o->quotas->sortBy('quota_number') as $ab) {
                $rows[] = ['order' => $o, 'abono' => $ab, 'acts' => $this->abonoActions($ab, $o)];
            }
        }

        $abStatus = [200 => 'Pendiente por depósito', 201 => 'Depositado', 202 => 'Constancia adjuntada'];

        // Cuentas de origen disponibles (datos bancarios de las empresas) para el depósito
        $companies = Company::orderBy('name')
            ->get(['id', 'name', 'source_bank', 'source_account_number', 'source_cci']);

        return view('Orders.payable', compact('rows', 'abStatus', 'companies'));
    }

    /**
     * Histórico de Pagos — listado de abonos ya pagados (constancia adjuntada, 202),
     * sin importar el estado de la orden. Consulta de lo ya liquidado.
     * AA ve los de sus órdenes; GF/AF ven todos; otros roles no acceden.
     */
    public function paymentsHistory()
    {
        $user = auth()->user();
        $role = $user->user_type;

        // Solo Gerencia Financiera (GF) y Asistente de Finanzas (AF) consultan el histórico.
        if (!in_array($role, ['GF', 'AF'], true)) {
            abort(403);
        }

        $query = OrderQuota::with(['order.company', 'order.paymentSchedule', 'order.detail'])
            ->where('status', 202);

        $abonos    = $query->get()->sortByDesc(fn ($q) => $q->deposit_date ?? $q->updated_at)->values();
        $companies = Company::orderBy('name')->pluck('name', 'id');

        return view('Orders.payments', compact('abonos', 'companies'));
    }

    /** Acciones disponibles sobre un abono según rol + estado + programación. */
    private function abonoActions(OrderQuota $q, Order $o): array
    {
        $role = auth()->user()->user_type;
        $st   = (int) $q->status;
        $urg  = $this->isUrgente($o);

        return [
            'deposit'    => $role === 'GF' && $st === 200,
            'constancia' => $st === 201 && ($role === 'AF' || ($urg && $role === 'GF')),
            'verify'     => $urg && $role === 'AA' && $st === 202 && !$q->monto_ok,
            'observe'    => $st === 202 && (($urg && $role === 'AA') || (!$urg && $role === 'UC2')),
        ];
    }

    /** GF deposita un abono (200 → 201). Selecciona la cuenta de origen (empresa). */
    public function abonoDeposit(Request $request, $quota)
    {
        $q    = OrderQuota::with('order.paymentSchedule')->findOrFail($quota);
        $user = auth()->user();
        if (!($user->user_type === 'GF' && (int) $q->status === 200)) {
            abort(403);
        }

        $data    = $request->validate(['source_company_id' => ['required', 'exists:companies,id']]);
        $company = Company::findOrFail($data['source_company_id']);
        if (!$company->source_account_number) {
            return response()->json($this->setRpta(0, 'La empresa seleccionada no tiene cuenta bancaria registrada.'));
        }

        $q->update([
            'status'                => 201,
            'deposit_date'          => now()->toDateString(),
            'source_company_id'     => $company->id,
            'source_bank'           => $company->source_bank,
            'source_account_number' => $company->source_account_number,
            'source_cci'            => $company->source_cci,
            'updated_by'            => $user->id,
        ]);

        return response()->json($this->setRpta(1, 'Depósito registrado', ['redirect' => route('orders.payable')]));
    }

    /** Sube la constancia de un abono (201 → 202). GF/AF urgente · AF general. */
    public function abonoConstancia(Request $request, $quota)
    {
        $q    = OrderQuota::with('order.paymentSchedule')->findOrFail($quota);
        $user = auth()->user();
        $o    = $q->order;
        $urg  = $this->isUrgente($o);

        $puede = (int) $q->status === 201 && ($user->user_type === 'AF' || ($urg && $user->user_type === 'GF'));
        if (!$puede) {
            abort(403);
        }

        $data = $request->validate([
            'constancia'       => ['required', 'file', 'max:10240'],
            'operation_number' => ['required', 'string', 'max:100'],
        ]);
        $path = $request->file('constancia')->store('orders/constancias', 'public');

        DB::transaction(function () use ($q, $user, $path, $o, $data) {
            $q->update([
                'status'           => 202,
                'constancia'       => $path,
                'constancia_date'  => now(),
                'operation_number' => $data['operation_number'],
                'updated_by'       => $user->id,
            ]);
            $this->checkAbonosCompletos($o->refresh());
        });

        return response()->json($this->setRpta(1, 'Constancia adjuntada', ['redirect' => route('orders.payable')]));
    }

    /** AA (urgente) marca un abono como conforme (queda en 202, monto_ok=1). */
    public function abonoVerify($quota)
    {
        $q    = OrderQuota::with('order.paymentSchedule')->findOrFail($quota);
        $user = auth()->user();
        if (!($this->isUrgente($q->order) && $user->user_type === 'AA' && (int) $q->status === 202)) {
            abort(403);
        }
        $q->update(['monto_ok' => true, 'updated_by' => $user->id]);

        return response()->json($this->setRpta(1, 'Abono verificado conforme', ['redirect' => route('orders.payable')]));
    }

    /** Observa un abono (regresa a 201). AA urgente · UC2 general. */
    public function abonoObserve(Request $request, $quota)
    {
        $q    = OrderQuota::with('order.paymentSchedule')->findOrFail($quota);
        $user = auth()->user();
        $o    = $q->order;
        $urg  = $this->isUrgente($o);

        $puede = (int) $q->status === 202 && (($urg && $user->user_type === 'AA') || (!$urg && $user->user_type === 'UC2'));
        if (!$puede) {
            abort(403);
        }

        $data = $request->validate(['observacion' => ['required', 'string']]);

        DB::transaction(function () use ($q, $user, $data, $o, $urg) {
            $q->update([
                'status' => 201, 'monto_ok' => false, 'rebote' => true,
                'observacion' => $data['observacion'], 'updated_by' => $user->id,
            ]);
            // General: si la orden ya estaba en [55], regresa a [102] (falta completar abonos)
            if (!$urg && (int) $o->status === 55) {
                OrderHistory::create([
                    'from_user' => $user->user_type, 'to_user' => 'AF',
                    'from_status' => 55, 'to_status' => 102,
                    'coment' => 'Abono observado: ' . $data['observacion'], 'order_id' => $o->id,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
                $o->update(['status' => 102, 'updated_by' => $user->id]);
            }
        });

        return response()->json($this->setRpta(1, 'Abono observado', ['redirect' => route('orders.payable')]));
    }

    /** GENERAL: cuando todos los abonos llegan a [202], la orden pasa a [55] automáticamente. */
    private function checkAbonosCompletos(Order $o): void
    {
        if ($this->isUrgente($o) || (int) $o->status !== 102) {
            return;
        }
        $total = $o->quotas()->count();
        if ($total > 0 && $o->quotas()->where('status', 202)->count() === $total) {
            OrderHistory::create([
                'from_user' => '', 'to_user' => 'UC2',
                'from_status' => 102, 'to_status' => 55,
                'coment' => 'Abonos completados', 'order_id' => $o->id,
                'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            ]);
            $o->update(['status' => 55]);
        }
    }

    /** Resumen de una orden (para el modal de aprobación): datos, condición y cronograma. */
    public function summary($order)
    {
        return response()->json($this->setRpta(1, 'OK', $this->orderSummaryData($order)));
    }

    /**
     * Vista Contable — detalle completo de la orden en SOLO LECTURA para UC1,
     * con botón de Código de Registro (modal por documento) y Observar en la cabecera.
     * Accesible únicamente en el paso de Código de Registro (urgente 9 / general 100).
     */
    public function vistaContable($order)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $acts = $this->orderActions($o);
        // Accesible en pasos de Código (Registro/Banco) o en el Cierre del flujo (UC3/UC4).
        $isCode  = (bool) $acts['code'];
        $isClose = (bool) $acts['close'];
        if (!$isCode && !$isClose) {
            abort(403);
        }

        $codeMode  = $acts['codeMode'];          // 'perdoc' (Registro) | 'single' (Banco) | null
        $codeLabel = $acts['codeLabel'];         // "Código de Registro" | "Código de Banco"
        $closeLabel = $acts['approveLabel'];     // "Cerrar"

        $vm          = $this->orderSummaryData($order);
        $observe     = $this->observeTarget($o) !== null;
        $obsTypes    = Master::where('main', 20)->whereNotNull('description')->orderBy('value')->pluck('description', 'value');
        $statusLabel = Status::find($o->status)?->description ?? $o->status;

        return view('Orders.vistacontable', compact('o', 'vm', 'acts', 'observe', 'obsTypes', 'statusLabel', 'codeMode', 'codeLabel', 'isCode', 'isClose', 'closeLabel'));
    }

    /** Arma el modelo de datos completo de una orden (resumen reutilizable: modal y vista contable). */
    private function orderSummaryData($order): array
    {
        $o = Order::with(['company', 'detail.supplier.accounts', 'products', 'files', 'quotas', 'paymentSchedule'])->findOrFail($order);
        $d = $o->detail;
        $cur = ($d?->currency === 'USD') ? '$ ' : 'S/ ';
        $fmt = fn ($v) => $cur . number_format((float) $v, 2);

        $cc = ($d && $d->cc_id)
            ? DB::table('cost_centers')->whereIn('id', (array) $d->cc_id)->pluck('description')->implode(', ')
            : '—';

        $condDesc      = Master::find($d?->condition_payment)?->description ?? '';
        $esFraccionado = stripos($condDesc, 'fraccionado') !== false;

        // Proveedor + cuenta destino (la seleccionada, o la primaria/primera)
        $sup     = $d?->supplier;
        $account = $sup ? ($sup->accounts->firstWhere('id', $d?->supplier_account_id) ?? $sup->accounts->first()) : null;

        // Documentos: comprobantes (con metadatos) y anexos
        $voucherLabels = Master::where('main', 15)->pluck('description', 'value');
        $attachLabels  = Master::where('main', 18)->pluck('description', 'value');

        return [
            'es_fraccionado' => $esFraccionado,
            'general' => [
                'empresa'       => $o->company?->name ?? '—',
                'tipo'          => Format::where('abrev', $o->format_id)->value('description') ?? $o->format_id,
                'categoria'     => Category::find($d?->category_id)?->description ?? '—',
                'moneda'        => $d?->currency ?? '—',
                'titulo'        => $o->title,
                'sede'          => Sede::find($d?->sede_id)?->nombre ?? '—',
                'area'          => Area::find($d?->area_id)?->description ?? '—',
                'cc'            => $cc ?: '—',
                'justificacion' => $d?->justification ?? '—',
            ],
            'condicion' => [
                'forma_pago'   => Master::find($d?->payment_id)?->description ?? '—',
                'condicion'    => Master::find($d?->condition_payment)?->description ?? '—',
                'programacion' => $o->paymentSchedule?->name ?? '—',
                'vencimiento'  => $d?->expiration_date ? \Carbon\Carbon::parse($d->expiration_date)->format('d/m/Y') : '—',
            ],
            'codigo_banco' => $d?->codigo_banco ?: null,
            'totales' => [
                'subtotal'  => $fmt($d?->sub_total ?? 0),
                'igv'       => $fmt($d?->igv ?? 0),
                'descuento' => $fmt($d?->discount ?? 0),
                'total'     => $fmt($d?->amount_neto ?? ($d?->total ?? 0)),
            ],
            'proveedor' => $sup ? [
                'ruc'          => $sup->ruc ?? '—',
                'razon_social' => $sup->name ?? '—',
                'direccion'    => $sup->address ?: '—',
                'distrito'     => $sup->district ?: '—',
                'contacto'     => $sup->contact ?: '—',
                'email'        => $sup->email ?: '—',
                'cuenta'       => $account ? [
                    'banco'  => $account->bank ?: '—',
                    'numero' => $account->account_number ?: '—',
                    'cci'    => $account->cci ?: '—',
                    'moneda' => $account->currency ?: '—',
                ] : null,
            ] : null,
            'items' => $o->products->map(fn ($p) => [
                'descripcion' => $p->description,
                'cantidad'    => rtrim(rtrim(number_format((float) $p->quantity, 2), '0'), '.'),
                'precio'      => $fmt($p->unit_price),
                'subtotal'    => $fmt($p->sub_total),
            ])->values(),
            // Comprobantes de pago (tienen N° de documento o monto) vs documentos anexos
            'comprobantes' => $o->files->filter(fn ($f) => $f->document_number || $f->amount)->map(fn ($f) => [
                'tipo'         => $voucherLabels[$f->type_file] ?? ($f->type_file ?? '—'),
                'numero'       => $f->document_number ?: '—',
                'monto'        => ($f->amount !== null && $f->amount !== '') ? $fmt($f->amount) : '—',
                'fecha'        => $f->emission_date ? \Carbon\Carbon::parse($f->emission_date)->format('d/m/Y') : '—',
                'cod_registro' => $f->registration_code ?: null,
                'subida'       => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : '—',
                'path'         => $f->path ? asset('storage/' . $f->path) : null,
            ])->values(),
            'anexos' => $o->files->reject(fn ($f) => $f->document_number || $f->amount)->map(fn ($f) => [
                'tipo'       => $attachLabels[$f->type_file] ?? ($f->type_file ?? '—'),
                'comentario' => $f->comentario ?: '—',
                'subida'     => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : '—',
                'path'       => $f->path ? asset('storage/' . $f->path) : null,
            ])->values(),
            'cuotas' => $o->quotas->sortBy('quota_number')->map(fn ($q) => [
                'numero'     => $q->quota_number,
                'fecha'      => $q->due_date ? \Carbon\Carbon::parse($q->due_date)->format('d/m/Y') : '—',
                'monto'      => $fmt($q->amount),
                'estado'     => [200 => 'Pendiente', 201 => 'Depositado', 202 => 'Constancia adjuntada'][$q->status] ?? $q->status,
                'estado_id'  => (int) $q->status,
                'constancia' => $q->constancia ? asset('storage/' . $q->constancia) : null,
                'const_fecha' => $q->constancia_date ? \Carbon\Carbon::parse($q->constancia_date)->format('d/m/Y H:i') : null,
                'operacion'  => $q->operation_number,
            ])->values(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // COMPROBANTES (AA carga en paralelo durante [102] — evita cuello de botella)
    // ──────────────────────────────────────────────────────────────────────────

    /** Lista los comprobantes de pago de una orden. */
    public function comprobantes($order)
    {
        $o      = Order::with('files')->findOrFail($order);
        $labels = Master::where('main', 15)->pluck('description', 'value');

        $list = $o->files
            ->filter(fn ($f) => $f->document_number || $f->amount)
            ->map(fn ($f) => [
                'id'                => $f->id,
                'type_label'        => $labels[$f->type_file] ?? $f->type_file,
                'document_number'   => $f->document_number,
                'amount'            => number_format((float) $f->amount, 2),
                'emission_date'     => $f->emission_date,
                'path'              => $f->path ? asset('storage/' . $f->path) : null,
                'registration_code' => $f->registration_code,
            ])->values();

        return response()->json($this->setRpta(1, 'OK', $list));
    }

    /** AA carga un comprobante de pago durante [102]. */
    public function uploadComprobante(Request $request, $order)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();
        if (!($user->user_type === 'AA' && (int) $o->status === 102 && $this->isUrgente($o))) {
            abort(403);
        }

        $data = $request->validate([
            'type_file'       => ['required'],
            'document_number' => ['required', 'string', 'max:100'],
            'amount'          => ['required', 'numeric'],
            'emission_date'   => ['required', 'date'],
            'file'            => ['required', 'file', 'max:10240'],
        ]);

        OrderFile::create([
            'id'              => $this->fileId($o->id, 'cb-aa'),
            'type_file'       => $data['type_file'],
            'document_number' => $data['document_number'],
            'amount'          => $data['amount'],
            'emission_date'   => $data['emission_date'],
            'order_id'        => $o->id,
            'path'            => $request->file('file')->store('orders/vouchers', 'public'),
            'principal'       => 1,
            'created_by'      => $user->id, 'updated_by' => $user->id,
        ]);

        return response()->json($this->setRpta(1, 'Comprobante cargado', null));
    }

    /** AA elimina un comprobante durante [102]. (Bloqueado si ya tiene código de registro o de banco.) */
    public function deleteComprobante($order, $file)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();
        if (!($user->user_type === 'AA' && (int) $o->status === 102 && $this->isUrgente($o))) {
            abort(403);
        }
        $f = OrderFile::where('order_id', $o->id)->where('id', $file)->first();
        if ($f && $f->registration_code) {
            return response()->json($this->setRpta(0, 'No se puede eliminar: el comprobante ya tiene Código de Registro.'));
        }
        $f?->delete();

        return response()->json($this->setRpta(1, 'Comprobante eliminado', null));
    }

    /**
     * UC1 guarda el Código de Registro de UN documento de pago (orders_file.registration_code).
     * Solo disponible en el paso de Código de Registro (transición perdoc).
     */
    public function saveRegistrationCode(Request $request, $order, $file)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();

        $t = $this->advanceTransition($o);
        if (!$t || ($t['mode'] ?? null) !== 'perdoc') {
            abort(403);
        }

        $data = $request->validate(['codigo' => ['required', 'string', 'max:100']]);

        $f = OrderFile::where('order_id', $o->id)->where('id', $file)->firstOrFail();
        $f->update(['registration_code' => $data['codigo'], 'updated_by' => $user->id]);

        return response()->json($this->setRpta(1, 'Código de registro guardado', [
            'id'                => $f->id,
            'registration_code' => $f->registration_code,
        ]));
    }

    /**
     * Avanza manualmente la orden tras asignar los códigos por documento de pago.
     * Código de Registro (UC1): 9/100→91 · Código de Banco (UC2): 91/55→92.
     */
    public function advanceRegistro($order)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();

        $t = $this->advanceTransition($o);
        if (!$t || ($t['mode'] ?? null) !== 'perdoc') {
            abort(403);
        }
        $current = (int) $o->status;

        DB::transaction(function () use ($o, $user, $current, $t) {
            OrderHistory::create([
                'from_user' => $user->user_type, 'to_user' => $t['to'],
                'from_status' => $current, 'to_status' => $t['next'],
                'coment' => $t['codeLabel'] . ' asignado por documento', 'order_id' => $o->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $o->update(['status' => $t['next'], 'updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, $t['msg'], ['redirect' => route('orders.view')]));
    }

    /** Historial de movimientos de una orden (línea de tiempo). */
    public function timeline($order)
    {
        $history     = OrderHistory::where('order_id', $order)->orderByDesc('id')->get();
        $statusNames = Status::pluck('description', 'id');
        $roleNames   = UserType::pluck('description', 'prefijo');
        $userNames   = User::pluck('name', 'id');

        $items = $history->map(fn ($h) => [
            'date'        => $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i') : '—',
            'user'        => $userNames[$h->created_by] ?? '—',
            'from_role'   => $roleNames[$h->from_user] ?? $h->from_user,
            'from_status' => $statusNames[$h->from_status] ?? $h->from_status,
            'to_status'   => $statusNames[$h->to_status] ?? $h->to_status,
            'to_status_id' => (int) $h->to_status,
            'comment'     => ($h->coment && $h->coment !== '0') ? $h->coment : '',
        ]);

        return response()->json($this->setRpta(1, 'OK', $items));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LÓGICA GENERAL (reutilizable)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Función general: órdenes que un usuario debe ver según su rol y políticas.
     * (Portado de Filament getEloquentQuery.)
     */
    private function scopedOrders($user)
    {
        $statusIds       = $this->policyStatusIds($user);
        $allowedStatuses = empty($statusIds) ? [0] : $statusIds;
        $normalStatuses  = array_diff($allowedStatuses, [5]);

        return Order::query()
            ->with(['company', 'detail', 'responsible', 'paymentSchedule', 'quotas'])

            // AA solo ve sus propias órdenes
            ->when($user->user_type === 'AA', fn ($q) => $q->where('user_responsible', $user->id))

            // Estados permitidos (+ caso especial estado 5 OBSERVADO hacia su rol)
            ->where(function ($q) use ($allowedStatuses, $normalStatuses, $user) {
                if (!empty($normalStatuses)) {
                    $q->whereIn('status', $normalStatuses);
                }
                if (in_array(5, $allowedStatuses)) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('status', 5)
                           ->whereHas('history', fn ($q3) =>
                               $q3->where('to_status', 5)->where('to_user', $user->user_type));
                    });
                }
            });
    }

    /** IDs de estados permitidos para el rol del usuario (orders_politicas). */
    private function policyStatusIds($user): array
    {
        return DB::table('orders_politicas')
            ->where('user_type', $user->user_type)
            ->pluck('status_id')
            ->toArray();
    }

    /** Clase CSS del badge de estado. */
    private function statusClass(int $s): string
    {
        return match ($s) {
            3, 7, 9 => 'status-green',
            2, 5    => 'status-yellow',
            4       => 'status-red',
            default => 'status-blue',
        };
    }

    /** Opciones de estado para el <select> de filtros: [id => description]. */
    private function allowedStatusOptions($user)
    {
        $ids   = $this->policyStatusIds($user);
        $query = Status::orderBy('id');
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
        return $query->pluck('description', 'id');
    }
}