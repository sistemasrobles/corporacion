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
use App\Models\OrderEvent;
use App\Models\OrderQuota;
use App\Models\OrderHistory;
use App\Models\OrderSequence;
use App\Models\Type;
use App\Models\TypeUser;
use App\Models\UserType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{   


    public function create()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['JA', 'AA', 'GA'], true)) {
            abort(403);
        }

        // El JA usa un formulario liviano (solicitud); el AA luego la completa.
        if ($user->user_type === 'JA') {
            return $this->createJa();
        }

        return view('Orders.create', $this->formData() + [
            'order'     => null,
            'prefill'   => null,
            'acts'      => ['approve' => false, 'observe' => false, 'reject' => false],
            'obsTypes'  => collect(),
            'orderMeta' => null,
            // El GA elige la Gestión (y con ella el AA responsable); el AA no la ve.
            'gestiones' => $user->user_type === 'GA' ? $this->aaGestiones() : collect(),
        ]);
    }

    /**
     * Formulario de solicitud del JA (réplica del de Filament, simplificado).
     * Sin moneda/monto/proveedor/cuotas: solo clasificación, gestión, fecha de
     * vencimiento, título, justificación y una cotización opcional. El responsable
     * se resuelve por la Gestión (type_user) y la orden nace en CREADO para que el
     * AA asignado la complete.
     */
    /**
     * Gestiones seleccionables: solo las que tienen un responsable de rol AA en type_user.
     * Cada item: [id, descripcion, responsible]. Se excluye "GESTION GLOBAL" (responsable GA).
     * Usada por el formulario del JA y por el del GA (que también elige a quién asignar).
     */
    private function aaGestiones()
    {
        $typeUsers = TypeUser::with('user')->get()
            ->filter(fn ($tu) => optional($tu->user)->user_type === 'AA')
            ->keyBy('type_id');

        return Type::whereIn('id', $typeUsers->keys())
            ->orderBy('descripcion')
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'descripcion' => $t->descripcion,
                'responsible' => $typeUsers[$t->id]->user->name ?? '—',
            ])
            ->values();
    }

    private function createJa()
    {
        $gestiones = $this->aaGestiones();
        $rules = $this->docRules();

        return view('Orders.create-ja', [
            'formats'         => Format::orderBy('description')->pluck('description', 'id'),
            'companies'       => Company::orderBy('name')->pluck('name', 'id'),
            'gestiones'       => $gestiones,
            'cotizacionLabel' => $rules['cotizacionLabel'] ?? 'Cotización',
        ]);
    }

    /**
     * Guarda la solicitud del JA: crea la orden en CREADO (1) con el responsable
     * derivado de la Gestión y, si se adjunta, registra la cotización como anexo.
     */
    public function storeJa(Request $request)
    {
        $user = auth()->user();
        if ($user->user_type !== 'JA') {
            abort(403);
        }

        $data = $request->validate([
            'company_id'      => ['required'],
            'format_id'       => ['required'],
            'category_id'     => ['required'],
            'type_id'         => ['required', 'exists:types,id'],
            'title'           => ['required', 'max:250'],
            'justification'   => ['required', 'max:250'],
            'expiration_date' => ['required', 'date', 'after_or_equal:today'],
            'cotizacion'      => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'expiration_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
            'justification.max'              => 'La justificación no puede superar los 250 caracteres.',
            'cotizacion.max'                 => 'La cotización no puede superar los 5 MB.',
            'cotizacion.mimes'               => 'La cotización debe ser PDF, JPG o PNG.',
        ]);

        // Responsable de la orden según la Gestión elegida (tabla intermedia type_user).
        $responsible = TypeUser::where('type_id', $data['type_id'])->value('user_id');
        if (!$responsible) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'type_id' => 'La gestión seleccionada no tiene un responsable asignado.',
            ]);
        }

        $format = Format::findOrFail($data['format_id']);
        $rules  = $this->docRules();

        $order = DB::transaction(function () use ($data, $user, $format, $request, $responsible, $rules) {
            $this->syncSequences(['orders', 'order_details', 'order_history']);

            $order = Order::create([
                'company_id'       => $data['company_id'],
                'status'           => 1,                       // CREADO → el AA responsable la completa
                'title'            => $data['title'],
                'type_id'          => $data['type_id'],
                'format_id'        => $format->abrev,
                'user_responsible' => $responsible,
                'created_by'       => $user->id,
                'updated_by'       => $user->id,
            ]);
            $this->generateCode($order, $format->abrev);

            OrderDetail::create([
                'order_id'         => $order->id,
                'required_date'    => now()->toDateString(),
                'period'           => now()->format('Ym'),
                'expiration_date'  => $data['expiration_date'],
                'category_id'      => $data['category_id'],
                'justification'    => $data['justification'],
                'suggested_amount' => 0,
                'created_by'       => $user->id,
                'updated_by'       => $user->id,
            ]);

            // Cotización opcional → anexo tipo COTIZACIÓN (resuelto dinámicamente).
            if ($file = $request->file('cotizacion')) {
                OrderFile::create([
                    'type_file'  => $rules['cotizacion'] ?? null,
                    'order_id'   => $order->id,
                    'path'       => $file->store('orders/docs', 'public'),
                    'principal'  => 0,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            // Historial: el JA crea y la asigna al AA responsable (estado CREADO).
            OrderHistory::create([
                'from_user'   => 'JA', 'to_user'   => 'AA',
                'from_status' => 0,    'to_status' => 1,
                'coment'      => '', 'order_id' => $order->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);

            return $order;
        });

        return response()->json($this->setRpta(1, 'Solicitud creada correctamente', [
            'id'       => $order->id,
            'code'     => $order->code,
            'redirect' => route('orders.view'),
        ]));
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
            'docRules'      => $this->docRules(),
        ];
    }

    /**
     * Regla "Recibo x Honorario": resuelve dinámicamente los `value` de masters
     * (sin hardcodear). Si hay un comprobante Recibo x Honorario → exige anexo
     * Informe Laboral; y si NO tiene retención → exige además Suspensión 4ta/5ta.
     */
    private function docRules(): array
    {
        $find = fn (int $main, string $like) => Master::where('main', $main)
            ->whereRaw('UPPER(description) LIKE ?', [$like])
            ->orderBy('value')->first();

        $recibo     = $find(15, '%HONORARIO%');
        $informe    = $find(18, '%INFORME LABORAL%');
        $suspension = $find(18, '%SUSPENSION%4TA%');
        $factura    = $find(15, '%FACTURA%');
        $boleta     = $find(15, '%BOLETA%');
        $cotizacion = $find(18, '%COTIZACION%');
        $guia       = $find(18, '%GUIA%REMISION%');
        $evidencia  = $find(18, '%EVIDENCIA%');
        $proyectos  = Area::whereRaw('UPPER(description) LIKE ?', ['%PROYECTOS%'])->first();
        $ordenServ  = Format::whereRaw('UPPER(description) LIKE ?', ['%SERVICIO%'])->first();

        return [
            'recibo'            => $recibo?->value,
            'informe'           => $informe?->value,
            'suspension'        => $suspension?->value,
            'informeLabel'      => $informe?->description ?? 'Informe Laboral',
            'suspensionLabel'   => $suspension?->description ?? 'Suspensión 4ta/5ta',
            // Programación General: comprobante Factura/Boleta y, si área Proyectos, anexos Cotización + Guía
            'factura'           => $factura?->value,
            'boleta'            => $boleta?->value,
            'cotizacion'        => $cotizacion?->value,
            'guia'              => $guia?->value,
            'evidencia'         => $evidencia?->value,
            'cotizacionLabel'   => $cotizacion?->description ?? 'Cotización',
            'guiaLabel'         => $guia?->description ?? 'Guía de Remisión',
            'evidenciaLabel'    => $evidencia?->description ?? 'Evidencias',
            'proyectosArea'     => $proyectos?->id,
            'ordenServicio'     => $ordenServ?->id,   // tipo de orden = Orden de Servicio
        ];
    }

    /** ¿La condición de pago es fraccionada? (masters main 8) */
    private function esFraccionado($conditionId): bool
    {
        return Master::where('main', 8)->where('id', $conditionId)
            ->whereRaw('UPPER(description) LIKE ?', ['%FRACCIONADO%'])->exists();
    }

    /**
     * Reglas de la orden al registrar/editar:
     *  1) Si NO es fraccionado → fecha de vencimiento obligatoria.
     *  2) Programación GENERAL → comprobante Factura o Boleta obligatorio.
     *  3) GENERAL + área Proyectos → anexos Cotización y Guía de Remisión obligatorios.
     */
    private function validarReglasOrden(bool $urgente, $conditionId, $expirationDate, $areaId, array $compTypes, array $anexoTypes, $planCuotasJson = null, bool $requirePlan = true, $formatId = null): void
    {
        if ($this->esFraccionado($conditionId)) {
            // Fraccionado: el plan de cuotas es obligatorio (se omite en edición restringida)
            $plan = json_decode($planCuotasJson ?? '[]', true) ?: [];
            if ($requirePlan && !$plan) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'plan_cuotas' => 'Debes configurar el plan de cuotas para el pago fraccionado.',
                ]);
            }
        } elseif (empty($expirationDate)) {
            // No fraccionado (al contado/crédito): la fecha de vencimiento es obligatoria
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expiration_date' => 'La fecha de vencimiento es obligatoria cuando el pago no es fraccionado.',
            ]);
        }

        if ($urgente) return;   // reglas 2 y 3 solo aplican a GENERAL

        $r    = $this->docRules();
        $comp = array_map('strval', $compTypes);
        $tieneFacturaBoleta = ($r['factura'] && in_array((string) $r['factura'], $comp, true))
            || ($r['boleta'] && in_array((string) $r['boleta'], $comp, true));
        if (!$tieneFacturaBoleta) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'comprobantes' => 'En programación General debes adjuntar un comprobante de pago (Factura o Boleta).',
            ]);
        }

        if ($r['proyectosArea'] && (string) $areaId === (string) $r['proyectosArea']) {
            $anx    = array_map('strval', $anexoTypes);
            $faltan = [];
            if ($r['cotizacion'] && !in_array((string) $r['cotizacion'], $anx, true)) $faltan[] = $r['cotizacionLabel'];
            if ($r['guia'] && !in_array((string) $r['guia'], $anx, true))             $faltan[] = $r['guiaLabel'];
            // Si además es Orden de Servicio → exige Evidencias
            $esServicio = $r['ordenServicio'] && (string) $formatId === (string) $r['ordenServicio'];
            if ($esServicio && $r['evidencia'] && !in_array((string) $r['evidencia'], $anx, true)) $faltan[] = $r['evidenciaLabel'];
            if ($faltan) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'documentos' => 'Para el área Proyectos (programación General) son obligatorios los anexos: ' . implode(', ', $faltan) . '.',
                ]);
            }
        }
    }

    /**
     * Valida la regla de Recibo x Honorario sobre los comprobantes/anexos enviados.
     * $comprobantes: array de ['type_file' => ..., 'has_retention' => bool].
     * $anexoTypes: array de los type_file de los documentos anexos presentes.
     * Lanza ValidationException si falta algún anexo obligatorio.
     */
    private function validarReciboHonorario(array $comprobantes, array $anexoTypes): void
    {
        $r = $this->docRules();
        if (!$r['recibo']) return;

        $recibos = array_filter($comprobantes, fn ($c) => (string) ($c['type_file'] ?? '') === (string) $r['recibo']);
        if (!$recibos) return;

        $errores = [];
        if ($r['informe'] && !in_array((string) $r['informe'], array_map('strval', $anexoTypes), true)) {
            $errores[] = 'Para un Recibo x Honorario debes adjuntar el documento anexo "' . $r['informeLabel'] . '".';
        }
        $sinRetencion = array_filter($recibos, fn ($c) => empty($c['has_retention']));
        if ($sinRetencion && $r['suspension'] && !in_array((string) $r['suspension'], array_map('strval', $anexoTypes), true)) {
            $errores[] = 'El Recibo x Honorario sin retención exige el documento anexo "' . $r['suspensionLabel'] . '".';
        }

        if ($errores) {
            throw \Illuminate\Validation\ValidationException::withMessages(['documentos' => $errores]);
        }
    }

    /** La suma del plan de cuotas debe coincidir con el total de la orden (tolerancia 0.01). */
    private function validarSumaCuotas($planCuotasJson, float $total): void
    {
        $plan = json_decode($planCuotasJson ?? '[]', true) ?: [];
        if (!$plan) return;
        $sum = array_sum(array_map(fn ($c) => floatval($c['monto'] ?? 0), $plan));
        if (abs($sum - $total) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'plan_cuotas' => 'La suma de las cuotas (' . number_format($sum, 2)
                    . ') no coincide con el total de la orden (' . number_format($total, 2) . ').',
            ]);
        }
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

        // Nombres de quien subió cada archivo (created_by), precargados para evitar N+1.
        $uploaderNames = User::whereIn('id', $order->files->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        $comprobantes = $order->files->filter($isComprobante)->map(fn ($f) => [
            'label'    => $voucherLabels[$f->type_file] ?? $f->type_file,
            'document' => $f->document_number,
            'amount'   => $f->amount,
            'date'     => $f->emission_date,
            'cod_reg'  => $f->registration_code,
            'coment'   => $f->comentario,
            'subida'   => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : null,
            'uploader' => $uploaderNames[$f->created_by] ?? '—',
            'path'     => $f->path,
        ])->values();

        $documentos = $order->files->reject($isComprobante)->map(fn ($f) => [
            'label'      => $attachLabels[$f->type_file] ?? $f->type_file,
            'comentario' => $f->comentario,
            'subida'     => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : null,
            'uploader'   => $uploaderNames[$f->created_by] ?? '—',
            'path'       => $f->path,
        ])->values();

        $statusLabel = Status::find($order->status)?->description ?? $order->status;
        $statusClass = $this->statusClass((int) $order->status);

        // Quién subió la constancia de cada cuota: del último evento 'constancia_abono' (registro
        // append-only). Mapa [quota_id => nombre], con los nombres precargados (sin N+1).
        $constanciaEvents = OrderEvent::where('order_id', $order->id)
            ->where('event_type', 'constancia_abono')
            ->orderByDesc('id')->get(['order_quota_id', 'created_by']);
        $evUploaderNames = User::whereIn('id', $constanciaEvents->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $constanciaUploaders = $constanciaEvents->groupBy('order_quota_id')
            ->map(fn ($evs) => $evUploaderNames[$evs->first()->created_by] ?? '—');

        return view('Orders.show', [
            'order'           => $order,
            'd'               => $d,
            'vm'              => $vm,
            'cur'             => $cur,
            'statusLabel'     => $statusLabel,
            'statusClass'     => $statusClass,
            'supplier'        => $d?->supplier,
            'selectedAccount' => $d?->supplier_account_id,
            'destAccount'     => $this->resolveDestAccount($d),   // cuenta destino usada por la orden (snapshot)
            'comprobantes'    => $comprobantes,
            'documentos'      => $documentos,
            'cuotas'          => $order->quotas->sortBy('quota_number')->values(),
            'constanciaUploaders' => $constanciaUploaders,
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
                'phone'    => $supplier->phone,
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
                'has_retention'   => $f->has_retention,
                'comentario'      => $f->comentario,
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

        $acts           = $this->orderActions($order);
        $restricted     = $this->isRestrictedEdit($order);
        $scheduleLocked = $this->isScheduleLocked($order);   // programación bloqueada (post-aprobación GA)
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

        return view('Orders.create', $this->formData() + compact('order', 'prefill', 'acts', 'restricted', 'scheduleLocked', 'obsTypes', 'orderMeta', 'obsInfo'));
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

        // La programación (urgente/general) define la rama del flujo. Una vez que el GA aprobó,
        // cambiarla desincronizaría la máquina de estados → se fuerza desde la BD (ignora el form).
        if ($this->isScheduleLocked($order)) {
            $request->merge(['payment_schedule_id' => $order->payment_schedule_id ?? $order->detail?->payment_schedule_id]);
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
            'supplier_account_id' => ['required'],
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
        ], [
            'supplier_account_id.required' => 'Selecciona la cuenta bancaria del proveedor.',
        ]);

        // Regla Recibo x Honorario sobre el conjunto final de archivos (conservados + nuevos)
        $keptFiles  = OrderFile::where('order_id', $order->id)
            ->whereIn('id', array_map('intval', (array) ($data['keep_files'] ?? [])) ?: [0])->get();
        $compsFinal = $keptFiles->filter(fn ($f) => $f->document_number || $f->amount)
            ->map(fn ($f) => ['type_file' => $f->type_file, 'has_retention' => $f->has_retention])->values()->all();
        foreach ($request->input('comprobantes', []) as $cb) {
            $compsFinal[] = ['type_file' => $cb['type_file'] ?? null, 'has_retention' => !empty($cb['has_retention'])];
        }
        $anexosFinal = $keptFiles->reject(fn ($f) => $f->document_number || $f->amount)->pluck('type_file')->all();
        foreach ($request->input('documentos', []) as $dn) {
            $anexosFinal[] = $dn['type'] ?? null;
        }
        $this->validarReciboHonorario($compsFinal, $anexosFinal);

        // Reglas de programación: vencimiento (no fraccionado), comprobante (General), anexos (Proyectos)
        $urgenteUpd = stripos(PaymentSchedule::find($data['payment_schedule_id'])?->name ?? '', 'urgente') !== false;
        $this->validarReglasOrden(
            $urgenteUpd,
            $data['condition_payment'],
            $data['expiration_date'] ?? null,
            $data['area_id'],
            array_map(fn ($c) => $c['type_file'] ?? null, $compsFinal),
            $anexosFinal,
            $data['plan_cuotas'] ?? null,
            !$restricted,   // en edición restringida las cuotas no se tocan → no exigir plan
            $data['format_id']
        );

        $format = Format::findOrFail($data['format_id']);
        $rules  = $this->docRules();

        DB::transaction(function () use ($order, $user, $data, $format, $request, $restricted, $rules) {
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

            // El plan de cuotas (si hay) debe sumar el total de la orden.
            // En edición restringida las cuotas no se tocan, así que se omite.
            if (!$restricted) {
                $this->validarSumaCuotas($data['plan_cuotas'] ?? null, $amtNeto);
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
                ...$this->destAccountSnapshot($data['supplier_account_id'] ?? null),   // snapshot cuenta destino
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
                $esRecibo = $rules['recibo'] && (string) ($cb['type_file'] ?? '') === (string) $rules['recibo'];
                OrderFile::create([
                    'type_file'       => $cb['type_file'] ?? null,
                    'document_number' => $cb['document_number'] ?? null,
                    'amount'          => $cb['amount'] ?? null,
                    'emission_date'   => $cb['emission_date'] ?? null,
                    'has_retention'   => $esRecibo ? !empty($cb['has_retention']) : null,
                    'comentario'      => $cb['comentario'] ?? null,
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
            'supplier_account_id' => ['required'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'numeric', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'payment_id'          => ['required'],
            'condition_payment'   => ['required'],
            'payment_schedule_id' => ['required'],
            'expiration_date'     => ['nullable', 'date', 'after_or_equal:today'],
            'discount_type_id'    => ['nullable'],
            'quotas'              => ['nullable'],
            'plan_cuotas'         => ['nullable'],
            'observation'         => ['nullable'],
            'comprobantes'        => ['nullable', 'array'],
            'documentos'          => ['nullable', 'array'],
            // Gestión: obligatoria para el GA (elige a quién asignar); opcional para el resto.
            'type_id'             => [$user->user_type === 'GA' ? 'required' : 'nullable', 'exists:types,id'],
        ], [
            'type_id.required'    => 'Selecciona el Tipo de Gestión (define el responsable de la orden).',
            'supplier_account_id.required' => 'Selecciona la cuenta bancaria del proveedor.',
            'expiration_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
            'justification.max'              => 'La justificación no puede superar los 500 caracteres.',
        ]);

        // Cronograma de cuotas: ninguna fecha de vencimiento puede ser anterior a hoy.
        foreach (json_decode($data['plan_cuotas'] ?? '[]', true) ?: [] as $i => $cuota) {
            $f = $cuota['fecha_vencimiento'] ?? null;
            if ($f && \Carbon\Carbon::parse($f)->startOfDay()->lt(now()->startOfDay())) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'plan_cuotas' => 'La fecha de la cuota ' . ($i + 1) . ' no puede ser anterior a hoy.',
                ]);
            }
        }

        // Regla Recibo x Honorario → anexos obligatorios (Informe Laboral / Suspensión)
        $this->validarReciboHonorario(
            $request->input('comprobantes', []),
            array_map(fn ($d) => $d['type'] ?? null, $request->input('documentos', []))
        );

        // Reglas de programación: vencimiento (no fraccionado), comprobante (General), anexos (Proyectos)
        $urgente = stripos(PaymentSchedule::find($data['payment_schedule_id'])?->name ?? '', 'urgente') !== false;
        $this->validarReglasOrden(
            $urgente,
            $data['condition_payment'],
            $data['expiration_date'] ?? null,
            $data['area_id'],
            array_map(fn ($c) => $c['type_file'] ?? null, $request->input('comprobantes', [])),
            array_map(fn ($d) => $d['type'] ?? null, $request->input('documentos', [])),
            $data['plan_cuotas'] ?? null,
            true,
            $data['format_id']
        );

        $format = Format::findOrFail($data['format_id']);
        $rules  = $this->docRules();

        $order = DB::transaction(function () use ($data, $user, $format, $request, $rules) {
            // Evita choques de secuencias tras migrar de MySQL
            $this->syncSequences(['orders', 'order_details', 'order_products', 'order_history', 'orders_quotas']);

            // Gestión: el GA la elige en el formulario (combo); el resto usa la suya (type_user
            // del creador). Fallback al mapeo del creador si no llega type_id.
            $typeId = $request->filled('type_id')
                ? (int) $request->input('type_id')
                : TypeUser::where('user_id', $user->id)->value('type_id');

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

            // El plan de cuotas (si hay) debe sumar el total de la orden
            $this->validarSumaCuotas($data['plan_cuotas'] ?? null, $amtNeto);

            // Orden
            $order = Order::create([
                'company_id'          => $data['company_id'],
                'status'              => $initialStatus,
                'title'               => $data['title'],
                'type_id'             => $typeId,
                'format_id'           => $format->abrev,
                'payment_schedule_id' => $data['payment_schedule_id'],
                // Responsable = el AA de la gestión elegida (type_user). El GA decide a quién
                // asignar vía el combo; el AA usa su propia gestión.
                'user_responsible'    => TypeUser::where('type_id', $typeId)->value('user_id') ?? 0,
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
                ...$this->destAccountSnapshot($data['supplier_account_id'] ?? null),   // snapshot cuenta destino
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
                $esRecibo = $rules['recibo'] && (string) ($cb['type_file'] ?? '') === (string) $rules['recibo'];
                OrderFile::create([
                    'type_file'       => $cb['type_file'] ?? null,
                    'document_number' => $cb['document_number'] ?? null,
                    'amount'          => $cb['amount'] ?? null,
                    'emission_date'   => $cb['emission_date'] ?? null,
                    'has_retention'   => $esRecibo ? !empty($cb['has_retention']) : null,
                    'comentario'      => $cb['comentario'] ?? null,
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

        // Solo proveedores activos pueden usarse en órdenes. Si está inactivo, se guía a activarlo.
        if (!$supplier->active) {
            return response()->json($this->setRpta(0, 'El proveedor existe pero está INACTIVO. Actívalo desde Mantenimiento de Proveedores para poder usarlo.', ['exists' => true]));
        }

        return response()->json($this->setRpta(1, 'OK', [
            'supplier' => [
                'id'       => $supplier->id,
                'ruc'      => $supplier->ruc,
                'name'     => $supplier->name,
                'address'  => $supplier->address,
                'district' => $supplier->district,
                'contact'  => $supplier->contact,
                'phone'    => $supplier->phone,
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
            'ruc'                       => ['required', 'string', 'digits:11'],
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
        ], [
            'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos.',
        ]);

        $uid = auth()->id();

        $supplier = DB::transaction(function () use ($data, $uid) {
            // Evita choques de secuencias tras migrar de MySQL (suppliers / supplier_accounts)
            $this->syncSequences(['suppliers', 'supplier_accounts']);

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
                'phone'    => $supplier->phone,
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
        $attachTypes  = Master::where('main', 18)->whereNotNull('description')->orderBy('description')->pluck('description', 'value');
        $docRules     = $this->docRules();

        return view('Orders.view', compact(
            'formats', 'areas', 'schedules', 'status', 'statusNames', 'orders', 'canEdit', 'actions',
            'obsTypes', 'voucherTypes', 'attachTypes', 'docRules'
        ));
    }

    /**
     * Devuelve las filas de Mis Órdenes ya renderizadas (mismo partial que la carga inicial),
     * con datos frescos de la BD. Usado por el botón "Recargar" para refrescar la tabla sin
     * recargar la página (emula AJAX reutilizando el Blade existente).
     */
    public function ordersRows()
    {
        $user        = auth()->user();
        $orders      = $this->scopedOrders($user)->latest()->get();
        $statusNames = Status::pluck('description', 'id');
        $canEdit     = fn (Order $o) => $this->canEditRecord($o);
        $actions     = fn (Order $o) => $this->orderActions($o);
        $isGA        = $user->user_type === 'GA';

        $html = view('Orders.partials.order-rows', compact('orders', 'statusNames', 'canEdit', 'actions', 'isGA'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => $orders->count()]));
    }

    /**
     * Aprobación masiva (solo GA): aprueba varias órdenes en POR_REVISAR [2] de una vez.
     * Si alguna seleccionada no está en estado 2, no aprueba ninguna y avisa.
     */
    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        if ($user->user_type !== 'GA') {
            abort(403);
        }

        $data   = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $orders = Order::with('paymentSchedule')->whereIn('id', $data['ids'])->get();

        // Validación: todas deben estar en POR_REVISAR (2)
        $noAprobables = $orders->filter(fn ($o) => (int) $o->status !== 2);
        if ($noAprobables->isNotEmpty()) {
            return response()->json($this->setRpta(0,
                'No se puede: ' . $noAprobables->count() . ' orden(es) seleccionada(s) no están en estado POR REVISAR. La aprobación masiva solo aplica a ese estado.'
            ));
        }

        $aprobadas = 0;
        DB::transaction(function () use ($orders, $user, &$aprobadas) {
            $this->syncSequences(['order_history']);   // evita choque de secuencias al insertar historial
            foreach ($orders as $o) {
                $t = $this->advanceTransition($o);
                if (!$t || $t['kind'] !== 'confirm') {
                    continue;
                }
                $current = (int) $o->status;
                OrderHistory::create([
                    'from_user' => $user->user_type, 'to_user' => $t['to'],
                    'from_status' => $current, 'to_status' => $t['next'],
                    'coment' => '', 'order_id' => $o->id,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
                $o->update(['status' => $t['next'], 'updated_by' => $user->id]);
                $aprobadas++;
            }
        });

        return response()->json($this->setRpta(1, "Se aprobaron {$aprobadas} orden(es).", ['redirect' => route('orders.view')]));
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

        // Al sustentar (AA: 102 → 8): debe haber al menos un comprobante de pago y cumplirse
        // los anexos del Recibo x Honorario.
        if ($current === 102 && (int) $t['next'] === 8) {
            $order->loadMissing('files');
            $tieneComprobante = $order->files->contains(fn ($f) => $f->document_number || $f->amount);
            if (!$tieneComprobante) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'comprobante' => 'No se puede sustentar: la orden no tiene ningún comprobante de pago adjunto.',
                ]);
            }
            $this->validarReciboHonorarioOrden($order);
        }

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
     * ¿La programación (tipo: urgente/general) está bloqueada para edición?
     * Es editable solo en la zona pre-aprobación del GA (estados 1 CREADO / 2 POR REVISAR,
     * y observadas que vienen de esa zona). Una vez aprobada/aceptada por el GA, la rama del
     * flujo queda fijada y cambiarla vararía la orden, así que se bloquea.
     */
    private function isScheduleLocked(Order $record): bool
    {
        $status = (int) $record->status;
        if (in_array($status, [1, 2], true)) return false;   // pre-aprobación: editable
        if ($status !== 5) return true;                       // ya en el flujo: bloqueada
        // Observada: editable solo si la observación vino de la zona pre-aprobación (GA en 2).
        $obs = OrderHistory::where('order_id', $record->id)
            ->where('to_status', 5)->latest('id')->first();
        return !$obs || !in_array((int) $obs->from_status, [1, 2], true);
    }

    /**
     * Scope de Órdenes Históricas según el rol:
     *  - AA: solo las suyas (user_responsible).
     *  - JA: solo las que creó (created_by).
     *  - resto: todas.
     */
    private function scopedHistory($user)
    {
        return Order::with(['company', 'detail', 'responsible', 'paymentSchedule'])
            ->when($user->user_type === 'AA', fn ($q) => $q->where('user_responsible', $user->id))
            ->when($user->user_type === 'JA', fn ($q) => $q->where('created_by', $user->id));
    }

    /**
     * Órdenes Históricas — todas las órdenes (consulta).
     */
    public function history()
    {
        $user = auth()->user();

        $orders = $this->scopedHistory($user)->latest()->get();

        // Datos para filtros
        $schedules   = PaymentSchedule::orderBy('name')->get();
        // Tipo de orden (Compra / Servicio): el filtro coincide con orders.format_id (abrev OC/OS).
        $tipos       = Format::orderBy('description')->pluck('description', 'abrev');
        // Estados del flujo de la orden para el filtro. Los estados de abono
        // (PENDIENTE_POR_DEPOSITO/DEPOSITADO/CONSTANCIA_ADJUNTADA, rango 200+)
        // pertenecen al ciclo de Cuentas por Pagar y no aplican aquí.
        $status      = Status::where('id', '<', 200)->orderBy('id')->pluck('description', 'id');
        $statusNames = Status::pluck('description', 'id');   // etiquetas completas para la tabla
        // Responsables: siempre rol AA. El GA, al crear, asigna la orden a un AA específico
        // (vía la gestión), así que el GA nunca es responsable. Query ligera por rol,
        // independiente del número de órdenes. Un AA solo se ve a sí mismo.
        $responsibles = User::where('user_type', 'AA')
            ->when($user->user_type === 'AA', fn ($q) => $q->where('id', $user->id))
            ->orderBy('name')->pluck('name', 'id');
        // Empresas: directo de la tabla companies (independiente del número de órdenes).
        $companies  = Company::orderBy('name')->pluck('name', 'id');
        // Monedas: de masters (main=1). value = código que guarda order_details.currency (PEN/USD).
        $currencies = Master::where('main', 1)->whereNotNull('value')
            ->orderBy('description')->pluck('description', 'value');

        return view('Orders.history', compact('orders', 'schedules', 'tipos', 'status', 'statusNames', 'responsibles', 'companies', 'currencies'));
    }

    /** Filas frescas de Órdenes Históricas (botón Recargar): re-consulta la BD y devuelve solo el partial. */
    public function historyRows()
    {
        $user = auth()->user();

        $orders      = $this->scopedHistory($user)->latest()->get();
        $statusNames = Status::pluck('description', 'id');

        $html = view('Orders.partials.history-rows', compact('orders', 'statusNames'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => $orders->count()]));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CUENTAS POR PAGAR (ciclo de abonos — orders_quotas)
    // ──────────────────────────────────────────────────────────────────────────

    /** Vista de Cuentas por Pagar: abonos de las órdenes en fase de pago. */
    public function payable()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['AA', 'UC2', 'GF', 'AF'], true)) {
            abort(403);
        }

        $rows = $this->collectPayableRows($user);

        // Cuentas de origen disponibles (datos bancarios de las empresas) para el depósito
        $companies = Company::orderBy('name')
            ->get(['id', 'name', 'source_bank', 'source_account_number', 'source_cci']);
        // Opciones de filtros (de sus tablas fuente).
        $schedules  = PaymentSchedule::orderBy('name')->get();
        $tipos      = Format::orderBy('description')->pluck('description', 'abrev');   // abrev OC/OS = orders.format_id
        $currencies = Master::where('main', 1)->whereNotNull('value')                 // value = order_details.currency (PEN/USD)
            ->orderBy('description')->pluck('description', 'value');

        return view('Orders.payable', compact('rows', 'companies', 'schedules', 'tipos', 'currencies'));
    }

    /** Filas frescas de Cuentas por Pagar (botón Recargar): re-consulta la BD y devuelve el partial. */
    public function payableRows()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['AA', 'UC2', 'GF', 'AF'], true)) {
            abort(403);
        }

        $rows = $this->collectPayableRows($user);
        $html = view('Orders.partials.payable-rows', compact('rows'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => count($rows)]));
    }

    /** Abonos en proceso de pago según el rol (filas para Cuentas por Pagar). */
    private function collectPayableRows($user): array
    {
        $role = $user->user_type;

        // Roles del ciclo de pago: GF/AF operan; AA observa sus abonos (urgente); UC2 (general).
        $query = Order::with(['quotas', 'company', 'paymentSchedule', 'detail'])
            ->whereIn('status', [102, 55]);

        if ($role === 'AA') {
            $query->where('user_responsible', $user->id)->where('status', 102);   // urgente: verifica/observa las suyas
        } elseif ($role === 'UC2') {
            $query->where('status', 55);                                           // general: revisa al completarse
        }
        // GF / AF ven todos los abonos en proceso.

        $rows = [];
        foreach ($query->latest()->get() as $o) {
            foreach ($o->quotas->sortBy('quota_number') as $ab) {
                $rows[] = ['order' => $o, 'abono' => $ab, 'acts' => $this->abonoActions($ab, $o)];
            }
        }

        return $rows;
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

        $abonos    = $this->collectPaymentsRows();
        $companies = Company::orderBy('name')->pluck('name', 'id');
        // Opciones de filtros (de sus tablas fuente).
        $schedules  = PaymentSchedule::orderBy('name')->get();
        $tipos      = Format::orderBy('description')->pluck('description', 'abrev');   // abrev OC/OS = orders.format_id
        $currencies = Master::where('main', 1)->whereNotNull('value')                 // value = order_details.currency
            ->orderBy('description')->pluck('description', 'value');

        return view('Orders.payments', compact('abonos', 'companies', 'schedules', 'tipos', 'currencies'));
    }

    /** Filas frescas del Histórico de Pagos (botón Recargar): re-consulta y devuelve el partial. */
    public function paymentsRows()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['GF', 'AF'], true)) {
            abort(403);
        }

        $abonos = $this->collectPaymentsRows();
        $html   = view('Orders.partials.payments-rows', compact('abonos'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => $abonos->count()]));
    }

    /** Abonos ya pagados (constancia adjuntada, 202), ordenados por fecha de depósito desc. */
    private function collectPaymentsRows()
    {
        return OrderQuota::with(['order.company', 'order.paymentSchedule', 'order.detail'])
            ->where('status', 202)
            ->get()
            ->sortByDesc(fn ($q) => $q->deposit_date ?? $q->updated_at)
            ->values();
    }

    /** Acciones disponibles sobre un abono según rol + estado + programación. */
    private function abonoActions(OrderQuota $q, Order $o): array
    {
        $role = auth()->user()->user_type;
        $st   = (int) $q->status;
        $urg  = $this->isUrgente($o);

        return [
            'deposit'    => $role === 'GF' && $st === 200,
            'constancia' => $st === 201 && ($role === 'AF' || $role === 'GF'),   // GF sube constancia en urgente y general
            'verify'     => false,   // "Conforme" deshabilitado: no condiciona el avance (era solo informativo)
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

        $puede = (int) $q->status === 201 && ($user->user_type === 'AF' || $user->user_type === 'GF');   // GF sube constancia en urgente y general
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
                'observacion'      => null,   // subsanado: la observación ya quedó en el histórico (orders_events)
                'rebote'           => false,
                'updated_by'       => $user->id,
            ]);

            // Histórico append-only: cada constancia subida queda registrada (no se sobrescribe)
            OrderEvent::create([
                'order_id'       => $o->id,
                'order_quota_id' => $q->id,
                'event_type'     => 'constancia_abono',
                'description'    => $data['operation_number'],
                'file'           => $path,
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
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

            // Histórico: queda registrada la observación (no se pierde al subsanar)
            OrderEvent::create([
                'order_id'       => $o->id,
                'order_quota_id' => $q->id,
                'event_type'     => 'observacion_abono',
                'description'    => $data['observacion'],
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
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
                // Rol del actor que completó el último abono (AF general / GF), para que el timeline lo muestre.
                'from_user' => auth()->user()?->user_type ?? '', 'to_user' => 'UC2',
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

    /**
     * Cuenta bancaria DESTINO que usa una orden, como array [bank, currency, account_number, cci].
     * Prioriza el snapshot guardado en la orden (dest_*); si está vacío (órdenes previas a la
     * migración) cae a la cuenta viva del proveedor por supplier_account_id.
     */
    private function resolveDestAccount($detail): ?array
    {
        if (!$detail) return null;

        if ($detail->dest_account_number) {
            return [
                'bank'           => $detail->dest_bank,
                'currency'       => $detail->dest_currency,
                'account_number' => $detail->dest_account_number,
                'cci'            => $detail->dest_cci,
            ];
        }

        // Fallback en vivo (órdenes anteriores al snapshot)
        $a = $detail->supplier?->accounts->firstWhere('id', $detail->supplier_account_id)
            ?? $detail->supplier?->accounts->first();

        return $a ? [
            'bank'           => $a->bank,
            'currency'       => $a->currency,
            'account_number' => $a->account_number,
            'cci'            => $a->cci,
        ] : null;
    }

    /** Snapshot de la cuenta destino para guardar en order_details (columnas dest_*). */
    private function destAccountSnapshot($accountId): array
    {
        $a = $accountId ? SupplierAccount::find($accountId) : null;
        return [
            'dest_bank'           => $a?->bank,
            'dest_account_number' => $a?->account_number,
            'dest_cci'            => $a?->cci,
            'dest_currency'       => $a?->currency,
        ];
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
        // Nombres de quien subió cada archivo (created_by), precargados para evitar N+1.
        $uploaderNames = User::whereIn('id', $o->files->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        // Quién subió la constancia de cada cuota: último evento 'constancia_abono'. Mapa [quota_id => nombre].
        $constanciaEvents = OrderEvent::where('order_id', $o->id)
            ->where('event_type', 'constancia_abono')
            ->orderByDesc('id')->get(['order_quota_id', 'created_by']);
        $evUploaderNames = User::whereIn('id', $constanciaEvents->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $constanciaUploaders = $constanciaEvents->groupBy('order_quota_id')
            ->map(fn ($evs) => $evUploaderNames[$evs->first()->created_by] ?? '—');

        return [
            'es_fraccionado' => $esFraccionado,
            'observacion'    => $d?->observation,   // observaciones del detalle de la orden
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
                'celular'      => $sup->phone ?: '—',
                'email'        => $sup->email ?: '—',
                'cuenta'       => ($destCuenta = $this->resolveDestAccount($d)) ? [
                    'banco'  => $destCuenta['bank'] ?: '—',
                    'numero' => $destCuenta['account_number'] ?: '—',
                    'cci'    => $destCuenta['cci'] ?: '—',
                    'moneda' => $destCuenta['currency'] ?: '—',
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
                'comentario'   => $f->comentario ?: null,
                'subida'       => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : '—',
                'uploader'     => $uploaderNames[$f->created_by] ?? '—',
                'path'         => $f->path ? asset('storage/' . $f->path) : null,
            ])->values(),
            'anexos' => $o->files->reject(fn ($f) => $f->document_number || $f->amount)->map(fn ($f) => [
                'tipo'       => $attachLabels[$f->type_file] ?? ($f->type_file ?? '—'),
                'comentario' => $f->comentario ?: '—',
                'subida'     => $f->created_at ? \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') : '—',
                'uploader'   => $uploaderNames[$f->created_by] ?? '—',
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
                'subido_por' => $constanciaUploaders[$q->id] ?? '—',
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
                'comentario'        => $f->comentario,
                'path'              => $f->path ? asset('storage/' . $f->path) : null,
                'registration_code' => $f->registration_code,
                'has_retention'     => $f->has_retention,
            ])->values();

        return response()->json($this->setRpta(1, 'OK', $list));
    }

    /** Lista los documentos anexos de una orden (para la carga del AA en [102]). */
    public function anexos($order)
    {
        $o      = Order::with('files')->findOrFail($order);
        $labels = Master::where('main', 18)->pluck('description', 'value');

        $list = $o->files
            ->reject(fn ($f) => $f->document_number || $f->amount)
            ->map(fn ($f) => [
                'id'         => $f->id,
                'type_label' => $labels[$f->type_file] ?? $f->type_file,
                'comentario' => $f->comentario,
                'path'       => $f->path ? asset('storage/' . $f->path) : null,
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
            'has_retention'   => ['nullable'],
            'comentario'      => ['nullable', 'string', 'max:500'],
            'file'            => ['required', 'file', 'max:10240'],
        ]);

        $rules    = $this->docRules();
        $esRecibo = $rules['recibo'] && (string) $data['type_file'] === (string) $rules['recibo'];

        OrderFile::create([
            'type_file'       => $data['type_file'],
            'document_number' => $data['document_number'],
            'amount'          => $data['amount'],
            'emission_date'   => $data['emission_date'],
            'has_retention'   => $esRecibo ? $request->boolean('has_retention') : null,
            'comentario'      => $data['comentario'] ?? null,
            'order_id'        => $o->id,
            'path'            => $request->file('file')->store('orders/vouchers', 'public'),
            'principal'       => 1,
            'created_by'      => $user->id, 'updated_by' => $user->id,
        ]);

        return response()->json($this->setRpta(1, 'Comprobante cargado', null));
    }

    /** AA carga un documento anexo durante [102]. */
    public function uploadAnexo(Request $request, $order)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();
        if (!($user->user_type === 'AA' && (int) $o->status === 102 && $this->isUrgente($o))) {
            abort(403);
        }

        $data = $request->validate([
            'type_file'  => ['required'],
            'comentario' => ['nullable', 'string', 'max:500'],
            'file'       => ['required', 'file', 'max:10240'],
        ]);

        OrderFile::create([
            'type_file'  => $data['type_file'],
            'comentario' => $data['comentario'] ?? null,
            'order_id'   => $o->id,
            'path'       => $request->file('file')->store('orders/docs', 'public'),
            'principal'  => 0,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        return response()->json($this->setRpta(1, 'Documento anexo cargado', null));
    }

    /** AA elimina un documento anexo durante [102]. */
    public function deleteAnexo($order, $file)
    {
        $o    = Order::with('paymentSchedule')->findOrFail($order);
        $user = auth()->user();
        if (!($user->user_type === 'AA' && (int) $o->status === 102 && $this->isUrgente($o))) {
            abort(403);
        }
        OrderFile::where('order_id', $o->id)->where('id', $file)
            ->where(fn ($q) => $q->whereNull('document_number')->whereNull('amount'))
            ->delete();

        return response()->json($this->setRpta(1, 'Documento anexo eliminado', null));
    }

    /** Regla Recibo x Honorario sobre los archivos ya guardados de una orden. */
    private function validarReciboHonorarioOrden(Order $o): void
    {
        $files  = $o->files()->get();
        $comps  = $files->filter(fn ($f) => $f->document_number || $f->amount)
            ->map(fn ($f) => ['type_file' => $f->type_file, 'has_retention' => $f->has_retention])->values()->all();
        $anexos = $files->reject(fn ($f) => $f->document_number || $f->amount)->pluck('type_file')->all();
        $this->validarReciboHonorario($comps, $anexos);
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
        $o    = Order::with(['paymentSchedule', 'files'])->findOrFail($order);
        $user = auth()->user();

        $t = $this->advanceTransition($o);
        if (!$t || ($t['mode'] ?? null) !== 'perdoc') {
            abort(403);
        }

        // Todos los documentos de pago deben tener un Código de Registro GUARDADO (en BD).
        $comprobantes = $o->files->filter(fn ($f) => $f->document_number || $f->amount);
        if ($comprobantes->isEmpty() || $comprobantes->contains(fn ($f) => blank($f->registration_code))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'codigo' => 'Asigna y guarda el Código de Registro de todos los documentos de pago antes de continuar.',
            ]);
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
        // Orden cronológico real (por fecha/hora; id como desempate). El id no es fiable
        // como orden temporal por el desfase de secuencias heredado de la migración.
        $history     = OrderHistory::where('order_id', $order)
            ->orderByDesc('created_at')->orderByDesc('id')->get();
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
                    // OBSERVADO (5): visible solo para el rol al que apunta la ÚLTIMA observación.
                    // Una orden pudo ser observada hacia varios roles a lo largo del flujo; comparar
                    // contra TODO el historial (whereHas) la mostraba a roles de observaciones ya
                    // resueltas. Comparamos el to_user del registro de historial más reciente.
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('status', 5)
                           ->whereRaw(
                               '(select oh.to_user from order_history oh where oh.order_id = orders.id '
                               . 'order by oh.created_at desc, oh.id desc limit 1) = ?',
                               [$user->user_type]
                           );
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