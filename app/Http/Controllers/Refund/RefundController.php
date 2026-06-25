<?php

namespace App\Http\Controllers\Refund;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\RefundStatus;
use App\Models\RefundStatusLog;
use App\Models\RefundObservation;
use App\Models\RefundPayment;
use App\Models\RefundFile;
use App\Models\RefundSequence;
use App\Models\RefundCategory;
use App\Models\Beneficiary;
use App\Models\BeneficiaryAccount;
use App\Models\Company;
use App\Models\CompanyAccount;
use App\Models\Area;
use App\Models\Master;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    /** Roles que participan en el flujo de requerimientos (ven el módulo). */
    private const ROLES = ['AA', 'GA', 'GF', 'AF', 'UC1'];

    /**
     * MÓDULO DESACTIVADO — el flujo de requerimientos cambió y ya no se usa.
     * El código y las tablas se conservan intactos; toda ruta responde 404.
     * Para reactivar: eliminar este constructor.
     */
    public function __construct()
    {
        $this->middleware(fn ($request, $next) => abort(404));
    }

    /** Listado de Órdenes de Requerimiento (vista única del módulo). */
    public function index()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, self::ROLES, true)) {
            abort(403);
        }

        $refunds   = $this->collectRefunds($user);
        $statuses  = RefundStatus::orderBy('id')->get(['id', 'name']);
        $canCreate = in_array($user->user_type, ['AA', 'GA'], true);   // AA crea las suyas; GA crea y asigna AA
        $obsTypes  = Master::where('main', 52)->whereNotNull('description')->orderBy('value')->pluck('description', 'value');
        // Cuentas de origen (empresas) para los modales de abono/reembolso del GF.
        $companyAccounts = $user->user_type === 'GF'
            ? CompanyAccount::with('company:id,name')->orderBy('company_id')->orderByDesc('is_primary')->get()
            : collect();

        return view('Requirements.index', compact('refunds', 'statuses', 'canCreate', 'obsTypes', 'companyAccounts'));
    }

    /** Filas frescas del listado (botón Recargar). */
    public function rows()
    {
        $user = auth()->user();
        if (!in_array($user->user_type, self::ROLES, true)) {
            abort(403);
        }

        $refunds = $this->collectRefunds($user);
        $html    = view('Requirements.partials.refund-rows', compact('refunds'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => $refunds->count()]));
    }

    private function collectRefunds($user)
    {
        return Refund::with([
            'company', 'beneficiary', 'statusInfo', 'category',
            // Pagos: N° operación del abono (constancia), banco/cuenta de origen (devolución) y tipos (saldado).
            'payments' => fn ($q) => $q->select('id', 'refund_id', 'payment_type', 'transaction_code', 'bank_origin', 'account_origin'),
        ])
            ->when($user->user_type === 'AA', fn ($q) => $q->where('responsible_id', $user->id))
            ->latest()
            ->get();
    }

    /** Formulario de creación (AA crea las suyas; GA crea y asigna un AA responsable). */
    public function create()
    {
        if (!in_array(auth()->user()->user_type, ['AA', 'GA'], true)) {
            abort(403);
        }

        return view('Requirements.create', $this->formData());
    }

    /** Catálogos compartidos por el formulario. */
    private function formData(): array
    {
        return [
            'companies'   => Company::orderBy('name')->pluck('name', 'id'),
            'areas'       => Area::orderBy('description')->pluck('description', 'id'),
            'categories'  => RefundCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'monedas'     => Master::where('main', 1)->whereNotNull('value')->orderBy('description')->get(['value', 'description']),
            'banks'       => Master::where('main', 67)->whereNotNull('description')->orderBy('description')->pluck('description', 'id'),
            'voucherTypes' => Master::where('main', 15)->whereNotNull('description')->orderBy('description')->pluck('description', 'value'),
            'aaUsers'     => User::where('user_type', 'AA')->orderBy('name')->pluck('name', 'id'),   // responsables posibles (GA)
            'obsTypes'    => Master::where('main', 52)->whereNotNull('description')->orderBy('value')->pluck('description', 'value'),
        ];
    }

    /**
     * Guarda la orden de requerimiento.
     * AA: la crea a su nombre y va a revisión del GA (estado 1).
     * GA: la crea, asigna un AA responsable y queda aprobada directo (estado 2, lista para el abono del GF).
     */
    public function store(Request $request)
    {
        $user  = auth()->user();
        $isGa  = $user->user_type === 'GA';
        if (!in_array($user->user_type, ['AA', 'GA'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'company_id'             => ['required'],
            'area_id'                => ['required'],
            'cost_center_id'         => ['required'],
            'category_id'            => ['required'],
            'currency'               => ['required'],
            'title'                  => ['required', 'string', 'max:250'],
            'purpose'                => ['required', 'string', 'max:1000'],
            'needed_date'            => ['required', 'date', 'after_or_equal:today'],
            'beneficiary_id'         => ['required', 'exists:beneficiaries,id'],
            'beneficiary_account_id' => ['required', 'exists:beneficiary_accounts,id'],
            // El GA debe asignar un AA responsable; el AA es responsable de la suya.
            'responsible_id'         => [$isGa ? 'required' : 'nullable', 'exists:users,id'],
            // Ítems del fondo (descripción + monto neto, sin IGV). El total se deriva de aquí.
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.description'    => ['required', 'string', 'max:255'],
            'items.*.amount'         => ['required', 'numeric', 'min:0.01'],
        ], [
            'beneficiary_id.required'         => 'Selecciona un beneficiario.',
            'beneficiary_account_id.required' => 'Selecciona la cuenta bancaria del beneficiario.',
            'responsible_id.required'         => 'Asigna el AA responsable de la orden.',
            'needed_date.required'            => 'Indica la fecha en que se necesita el fondo.',
            'needed_date.after_or_equal'      => 'La fecha en que se necesita el fondo no puede ser anterior a hoy.',
            'items.required'                  => 'Agrega al menos un ítem al fondo.',
            'items.min'                       => 'Agrega al menos un ítem al fondo.',
        ]);

        // El monto solicitado es la suma de los ítems (sin IGV).
        $itemsTotal = round(collect($data['items'])->sum(fn ($i) => (float) $i['amount']), 2);

        // Resolver el AA responsable: el AA es él mismo; el GA elige uno (validando que sea AA).
        if ($isGa) {
            $responsible = User::where('id', $data['responsible_id'])->where('user_type', 'AA')->first();
            if (!$responsible) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'responsible_id' => 'El responsable debe ser un usuario de tipo AA.',
                ]);
            }
            $responsibleId = $responsible->id;
        } else {
            $responsibleId = $user->id;
        }

        // Snapshot de la cuenta destino elegida (debe pertenecer al beneficiario)
        $acc = BeneficiaryAccount::where('id', $data['beneficiary_account_id'])
            ->where('beneficiary_id', $data['beneficiary_id'])->first();
        if (!$acc) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'beneficiary_account_id' => 'La cuenta seleccionada no pertenece al beneficiario.',
            ]);
        }
        $ben = Beneficiary::find($data['beneficiary_id']);   // para congelar nombre/documento

        // El GA crea ya aprobada (2, va al GF); el AA la envía a revisión (1, va al GA).
        $toStatus = $isGa ? 2 : 1;

        $refund = DB::transaction(function () use ($data, $user, $acc, $ben, $responsibleId, $isGa, $toStatus, $itemsTotal) {
            $refund = Refund::create([
                'code'                   => $this->nextRefundCode(),   // OR-{año}-{correlativo}
                'status'                 => $toStatus,
                'company_id'             => $data['company_id'],
                'area_id'                => $data['area_id'],
                'cost_center_id'         => $data['cost_center_id'],
                'category_id'            => $data['category_id'],
                'currency'               => $data['currency'],
                'title'                  => $data['title'],
                'purpose'                => $data['purpose'],
                'needed_date'            => $data['needed_date'],
                'beneficiary_id'         => $data['beneficiary_id'],
                'beneficiary_account_id' => $acc->id,
                'beneficiary_account'    => $acc->account_number,   // snapshot
                'beneficiary_bank'       => $acc->bank,             // snapshot
                'beneficiary_name'       => $ben?->name,                                                   // snapshot
                'beneficiary_doc'        => $ben ? trim(($ben->doc_type ?? '') . ' ' . ($ben->doc_number ?? '')) : null,  // snapshot
                'requested_amount'       => $itemsTotal,            // suma de ítems
                // Si la crea el GA, ya queda aprobada y con el monto congelado.
                'approved_amount'        => $isGa ? $itemsTotal : null,
                'approved_by'            => $isGa ? $user->id : null,
                'approved_at'            => $isGa ? now() : null,
                'created_by'             => $user->id,
                'responsible_id'         => $responsibleId,
                'updated_by'             => $user->id,
            ]);

            // Ítems del fondo (estimado, sin IGV)
            foreach ($data['items'] as $it) {
                $refund->details()->create([
                    'description'      => $it['description'],
                    'estimated_amount' => $it['amount'],
                ]);
            }

            // Bitácora de estado: creación → (revisión | aprobada)
            RefundStatusLog::create([
                'refund_id'   => $refund->id,
                'from_status' => 0,
                'to_status'   => $toStatus,
                'changed_by'  => $user->id,
                'changed_at'  => now(),
                'notes'       => $isGa ? 'Orden creada y aprobada por GA' : 'Orden creada y enviada a revisión',
            ]);

            return $refund;
        });

        return response()->json($this->setRpta(1, 'Orden de requerimiento creada', [
            'id'       => $refund->id,
            'code'     => $refund->code,
            'redirect' => route('requirements.index'),
        ]));
    }

    /**
     * Genera el código correlativo de la orden de requerimiento: OR-{año}-{0001}.
     * Secuencia propia por año, gapless y a prueba de concurrencia (debe llamarse dentro de una transacción).
     */
    private function nextRefundCode(): string
    {
        $year = now()->year;
        $seq  = RefundSequence::lockForUpdate()->firstOrCreate(['year_code' => $year], ['last_number' => 0]);
        $seq->increment('last_number');
        $seq->refresh();

        return 'OR-' . $year . str_pad($seq->last_number, 6, '0', STR_PAD_LEFT);
    }

    /** Formulario de edición (AA responsable en 0/4; GA en Por revisar 1). */
    public function edit(Refund $refund)
    {
        $user = auth()->user();
        if (!$this->userCanEdit($refund, $user)) {
            abort(403, 'No puedes editar esta orden.');
        }

        $refund->load('beneficiary.accounts', 'details');
        $ben = $refund->beneficiary;

        return view('Requirements.create', array_merge($this->formData(), [
            'mode'        => 'edit',
            'refund'      => $refund,
            'items'       => $refund->details->map(fn ($d) => [
                'description' => $d->description, 'amount' => $d->estimated_amount,
            ])->values(),
            'costCenters' => Area::find($refund->area_id)
                ?->costCenters()->orderBy('cost_centers.description')
                ->pluck('cost_centers.description', 'cost_centers.id') ?? collect(),
            'observation' => $this->lastObservation($refund),
            'benef' => $ben ? [
                'id' => $ben->id, 'name' => $ben->name, 'doc_number' => $ben->doc_number,
            ] : null,
            'benefAccounts' => $ben ? $ben->accounts->map(fn ($a) => [
                'id' => $a->id, 'bank' => $a->bank, 'currency' => $a->currency,
                'account_number' => $a->account_number, 'cci' => $a->cci, 'is_primary' => (bool) $a->is_primary,
            ])->values() : [],
        ]));
    }

    /** Última observación con autor/rol/fecha (para el banner de "corrige/subsana"). */
    private function lastObservation(Refund $refund, ?int $toStatus = null): ?array
    {
        $obs = $refund->observations()
            ->when($toStatus !== null, fn ($q) => $q->where('to_status', $toStatus))
            ->with('observer')
            ->latest('created_at')
            ->first();

        return $obs ? [
            'comment' => $obs->comment,
            'by'      => $obs->observer->name ?? $obs->role,
            'role'    => $obs->role,
            'date'    => $obs->created_at?->format('d/m/Y H:i'),
        ] : null;
    }

    /** Guarda la edición. AA (4/0): corrige y reenvía a revisión (→1). GA (1): edita y permanece en revisión. */
    public function update(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if (!$this->userCanEdit($refund, $user)) {
            return response()->json($this->setRpta(0, 'No puedes editar esta orden.', null), 403);
        }

        $data = $request->validate([
            'company_id'             => ['required'],
            'area_id'                => ['required'],
            'cost_center_id'         => ['required'],
            'category_id'            => ['required'],
            'currency'               => ['required'],
            'title'                  => ['required', 'string', 'max:250'],
            'purpose'                => ['required', 'string', 'max:1000'],
            'needed_date'            => ['required', 'date', 'after_or_equal:today'],
            'beneficiary_id'         => ['required', 'exists:beneficiaries,id'],
            'beneficiary_account_id' => ['required', 'exists:beneficiary_accounts,id'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.description'    => ['required', 'string', 'max:255'],
            'items.*.amount'         => ['required', 'numeric', 'min:0.01'],
        ], [
            'needed_date.required'       => 'Indica la fecha en que se necesita el fondo.',
            'needed_date.after_or_equal' => 'La fecha en que se necesita el fondo no puede ser anterior a hoy.',
            'items.required' => 'Agrega al menos un ítem al fondo.',
            'items.min'      => 'Agrega al menos un ítem al fondo.',
        ]);

        $itemsTotal = round(collect($data['items'])->sum(fn ($i) => (float) $i['amount']), 2);

        $acc = BeneficiaryAccount::where('id', $data['beneficiary_account_id'])
            ->where('beneficiary_id', $data['beneficiary_id'])->first();
        if (!$acc) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'beneficiary_account_id' => 'La cuenta seleccionada no pertenece al beneficiario.',
            ]);
        }
        $ben = Beneficiary::find($data['beneficiary_id']);

        $wasObserved = in_array((int) $refund->status, [0, 4], true);   // AA corrigiendo; el GA edita en estado 1

        DB::transaction(function () use ($refund, $data, $acc, $ben, $user, $itemsTotal, $wasObserved) {
            $refund->update([
                'company_id'             => $data['company_id'],
                'area_id'                => $data['area_id'],
                'cost_center_id'         => $data['cost_center_id'],
                'category_id'            => $data['category_id'],
                'currency'               => $data['currency'],
                'title'                  => $data['title'],
                'purpose'                => $data['purpose'],
                'needed_date'            => $data['needed_date'],
                'beneficiary_id'         => $data['beneficiary_id'],
                'beneficiary_account_id' => $acc->id,
                'beneficiary_account'    => $acc->account_number,
                'beneficiary_bank'       => $acc->bank,
                'beneficiary_name'       => $ben?->name,
                'beneficiary_doc'        => $ben ? trim(($ben->doc_type ?? '') . ' ' . ($ben->doc_number ?? '')) : null,
                'requested_amount'       => $itemsTotal,
                'updated_by'             => $user->id,
            ]);

            // Reemplazar los ítems del fondo
            $refund->details()->delete();
            foreach ($data['items'] as $it) {
                $refund->details()->create([
                    'description'      => $it['description'],
                    'estimated_amount' => $it['amount'],
                ]);
            }

            if ($wasObserved) {
                // AA corrigió una orden observada/creada: vuelve a revisión del GA.
                $this->transition($refund, 1, $user, 'Corregido y reenviado a revisión', ['updated_by' => $user->id]);
            } else {
                // GA editó una orden en revisión: al guardar queda aprobada y pasa al GF.
                $this->transition($refund, 2, $user, 'Editada y aprobada por GA', [
                    'approved_amount' => $itemsTotal,
                    'approved_by'     => $user->id,
                    'approved_at'     => now(),
                    'updated_by'      => $user->id,
                ]);
            }
        });

        return response()->json($this->setRpta(1, $wasObserved ? 'Orden actualizada y reenviada a revisión' : 'Orden actualizada y aprobada', [
            'id'       => $refund->id,
            'code'     => $refund->code,
            'redirect' => route('requirements.index'),
        ]));
    }

    /** Detalle de una orden de requerimiento (lectura + acciones según rol/estado). */
    public function show(Refund $refund)
    {
        $user = auth()->user();
        if (!in_array($user->user_type, self::ROLES, true)) {
            abort(403);
        }

        $refund->load([
            'company', 'area', 'costCenter', 'category', 'statusInfo', 'creator', 'responsible',
            'beneficiary', 'details',
            'payments'     => fn ($q) => $q->with('uploader')->orderBy('id'),
            'files'        => fn ($q) => $q->with('uploader')->orderBy('id'),
            'observations' => fn ($q) => $q->with('observer')->orderByDesc('created_at'),
            'statusLogs'   => fn ($q) => $q->with(['changer', 'fromStatus', 'toStatus'])->orderByDesc('changed_at'),
        ]);

        $status = (int) $refund->status;
        // ¿Es el GA y la orden está esperando su revisión?
        $canReview = $user->user_type === 'GA' && $status === 1;
        // ¿Puede editar? AA responsable (0/4) o GA (1, antes de aprobar).
        $canEdit = $this->userCanEdit($refund, $user);
        // GF registra el abono cuando está aprobada (2).
        $canDeposit = $user->user_type === 'GF' && $status === 2;
        // GF o AF adjuntan la constancia del abono (5).
        $canConstancia = in_array($user->user_type, ['GF', 'AF'], true) && $status === 5;
        // AA dueño rinde los comprobantes cuando hay constancia (6).
        $canRender = $user->user_type === 'AA' && $refund->responsible_id === $user->id && $status === 6;

        // ── Liquidación (Fase 5) ──
        $diff          = (float) $refund->difference_amount;
        $hasDevolucion = $refund->payments->contains('payment_type', 'DEVOLUCION_EMPRESA');
        // GF deposita el reembolso del faltante (8).
        $canReembolso = $user->user_type === 'GF' && $status === 8;
        // AA dueño registra la devolución del sobrante (7, diff>0, aún no registrada).
        $canDevolucion = $user->user_type === 'AA' && $refund->responsible_id === $user->id
            && $status === 7 && $diff > 0 && !$hasDevolucion;
        // AF valida la rendición (7) — conforme solo si la liquidación está saldada.
        $canConforme = $user->user_type === 'AF' && $status === 7 && ($diff <= 0 || $hasDevolucion);
        $canObserveRendicion = $user->user_type === 'AF' && $status === 7;
        // UC1 cierra (9).
        $canCerrar = $user->user_type === 'UC1' && $status === 9;

        // Empresas con cuenta de origen (modal de abono del GF y de reembolso).
        $companies = ($canDeposit || $canReembolso)
            ? Company::whereNotNull('source_account_number')->orderBy('name')->get(['id', 'name', 'source_bank', 'source_account_number'])
            : collect();

        // Tipos de comprobante (master main=15) y de observación (main=20).
        $voucherTypes = Master::where('main', 15)->whereNotNull('description')->orderBy('description')->pluck('description', 'value');
        $obsTypes     = Master::where('main', 52)->whereNotNull('description')->orderBy('value')->pluck('description', 'value');

        return view('Requirements.show', compact(
            'refund', 'canReview', 'canEdit', 'canDeposit', 'canConstancia', 'canRender',
            'canReembolso', 'canDevolucion', 'canConforme', 'canObserveRendicion', 'canCerrar',
            'companies', 'voucherTypes', 'obsTypes'
        ));
    }

    /** GA aprueba: 1 POR_REVISAR → 2 APROBADO (queda para el abono del GF). */
    public function approve(Refund $refund)
    {
        $user = $this->guardGaReview($refund);

        DB::transaction(function () use ($refund, $user) {
            $this->transition($refund, 2, $user, 'Aprobado por GA', [
                'approved_amount' => $refund->requested_amount,   // se congela el monto aprobado
                'approved_by'     => $user->id,
                'approved_at'     => now(),
                'updated_by'      => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Orden aprobada', ['redirect' => route('requirements.index')]));
    }

    /** GA observa: 1 POR_REVISAR → 4 OBSERVADO (vuelve al AA, editable). */
    public function observe(Request $request, Refund $refund)
    {
        $user = $this->guardGaReview($refund);
        $data = $request->validate([
            'obs_type' => ['required'],
            'comment'  => ['required', 'string', 'max:1000'],
        ], [
            'obs_type.required' => 'Selecciona el tipo de observación.',
            'comment.required'  => 'Escribe el motivo de la observación.',
        ]);
        $comment = $this->buildObsComment($data['obs_type'], $data['comment']);

        DB::transaction(function () use ($refund, $user, $comment) {
            RefundObservation::create([
                'refund_id'   => $refund->id,
                'from_status' => $refund->status,
                'to_status'   => 4,
                'observed_by' => $user->id,
                'role'        => $user->user_type,
                'comment'     => $comment,
                'created_at'  => now(),
            ]);
            $this->transition($refund, 4, $user, 'Observado por GA', ['updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Orden observada', ['redirect' => route('requirements.index')]));
    }

    /** GA rechaza: 1 POR_REVISAR → 3 RECHAZADO (estado final). */
    public function reject(Request $request, Refund $refund)
    {
        $user = $this->guardGaReview($refund);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => 'Escribe el motivo del rechazo.',
        ]);

        DB::transaction(function () use ($refund, $user, $data) {
            $this->transition($refund, 3, $user, 'Rechazado por GA', [
                'rejected_by'      => $user->id,
                'rejected_at'      => now(),
                'rejection_reason' => $data['reason'],
                'updated_by'       => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Orden rechazada', ['redirect' => route('requirements.index')]));
    }

    /** GF registra el abono al beneficiario: 2 APROBADO → 5 ABONADO. */
    public function abono(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'GF') {
            abort(403);
        }
        if ((int) $refund->status !== 2) {
            return response()->json($this->setRpta(0, 'La orden no está aprobada para abono.', null), 409);
        }

        $data = $request->validate([
            'source_account_id' => ['required', 'exists:company_accounts,id'],
            'payment_date'      => ['required', 'date'],
            'transaction_code'  => ['required', 'string', 'max:100'],
        ], [
            'source_account_id.required' => 'Selecciona la cuenta de origen.',
            'transaction_code.required'  => 'Indica el N° de operación del abono.',
        ]);

        $account = CompanyAccount::with('company')->findOrFail($data['source_account_id']);
        $amount  = $refund->approved_amount ?? $refund->requested_amount;

        DB::transaction(function () use ($refund, $user, $account, $data, $amount) {
            RefundPayment::create([
                'refund_id'           => $refund->id,
                'payment_type'        => 'ABONO_INICIAL',
                'amount'              => $amount,
                'payment_date'        => $data['payment_date'],
                'bank_origin'         => $account->bank,
                'account_origin'      => $account->account_number,
                'bank_destination'    => $refund->beneficiary_bank,
                'account_destination' => $refund->beneficiary_account,
                'transaction_code'    => $data['transaction_code'] ?? null,
                'notes'               => 'Abono inicial desde ' . ($account->company->name ?? '—'),
                'uploaded_by'         => $user->id,
                'uploaded_at'         => now(),
            ]);
            $this->transition($refund, 5, $user, 'Abono registrado por GF', [
                'paid_amount' => $amount,
                'updated_by'  => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Abono registrado', ['redirect' => route('requirements.index')]));
    }

    /** GF/AF adjunta la constancia del abono: 5 ABONADO → 6 CONSTANCIA_ADJUNTA. */
    public function constancia(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if (!in_array($user->user_type, ['GF', 'AF'], true)) {
            abort(403);
        }
        if ((int) $refund->status !== 5) {
            return response()->json($this->setRpta(0, 'La orden no está en estado de abono.', null), 409);
        }

        $request->validate([
            'constancia' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('constancia');
        $path = FileStorage::put($file, 'requirements/constancias');

        DB::transaction(function () use ($refund, $user, $file, $path) {
            // Adjunta el archivo al abono; el N° de operación ya quedó registrado al abonar.
            $payment = $refund->payments()->where('payment_type', 'ABONO_INICIAL')->latest('id')->first();
            if ($payment) {
                $payment->update([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }
            $this->transition($refund, 6, $user, 'Constancia de abono adjuntada', ['updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Constancia adjuntada', ['redirect' => route('requirements.index')]));
    }

    /** Rendición del AA: abre el mismo formulario de la orden en modo rendición (solo lectura + documentos de pago). Estado 6. */
    public function rendicion(Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'AA' || $refund->responsible_id !== $user->id || (int) $refund->status !== 6) {
            abort(403);
        }
        $refund->load(['beneficiary.accounts', 'details', 'payments', 'files' => fn ($q) => $q->with('uploader')->orderBy('id')]);
        $ben = $refund->beneficiary;

        return view('Requirements.create', array_merge($this->formData(), [
            'mode'        => 'rendicion',
            'refund'      => $refund,
            'items'       => $refund->details->map(fn ($d) => [
                'description' => $d->description, 'amount' => $d->estimated_amount,
            ])->values(),
            'costCenters' => Area::find($refund->area_id)
                ?->costCenters()->orderBy('cost_centers.description')
                ->pluck('cost_centers.description', 'cost_centers.id') ?? collect(),
            'benef' => $ben ? ['id' => $ben->id, 'name' => $ben->name, 'doc_number' => $ben->doc_number] : null,
            'benefAccounts' => $ben ? $ben->accounts->map(fn ($a) => [
                'id' => $a->id, 'bank' => $a->bank, 'currency' => $a->currency,
                'account_number' => $a->account_number, 'cci' => $a->cci, 'is_primary' => (bool) $a->is_primary,
            ])->values() : [],
            // Si volvió por una observación (AF estado 7→6 o UC1 estado 9→6), mostrarla para subsanar.
            'observation' => $this->lastObservation($refund, 6),
        ]));
    }

    /** AA sube un comprobante de gasto durante la rendición (estado 6). */
    public function uploadComprobante(Request $request, Refund $refund)
    {
        $this->guardRendicion($refund);
        $user = auth()->user();

        $data = $request->validate([
            'type_file'       => ['required', 'string', 'max:30'],
            'supplier'        => ['nullable', 'string', 'max:150'],
            'document_number' => ['required', 'string', 'max:80'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'issue_date'      => ['required', 'date'],
            'file'            => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        RefundFile::create([
            'refund_id'       => $refund->id,
            'type_file'       => $data['type_file'],
            'supplier'        => $data['supplier'] ?? null,
            'document_number' => $data['document_number'],
            'amount'          => $data['amount'],
            'issue_date'      => $data['issue_date'],
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => FileStorage::put($file, 'requirements/comprobantes'),
            'file_size'       => $file->getSize(),
            'uploaded_by'     => $user->id,
            'uploaded_at'     => now(),
        ]);

        return response()->json($this->setRpta(1, 'Comprobante cargado', ['redirect' => route('requirements.rendicion', $refund)]));
    }

    /** AA elimina un comprobante mientras la orden sigue en rendición (estado 6). */
    public function deleteComprobante(Refund $refund, RefundFile $file)
    {
        $this->guardRendicion($refund);
        if ($file->refund_id !== $refund->id) {
            abort(404);
        }
        if ($file->file_path) {
            FileStorage::delete($file->file_path);
        }
        $file->delete();

        return response()->json($this->setRpta(1, 'Comprobante eliminado', ['redirect' => route('requirements.rendicion', $refund)]));
    }

    /** AA finaliza la rendición: suma comprobantes, calcula diferencia y pasa 6 → 7 RENDIDO. */
    public function rendir(Refund $refund)
    {
        $this->guardRendicion($refund);
        $user = auth()->user();

        $rendered = (float) $refund->files()->sum('amount');
        if ($rendered <= 0) {
            return response()->json($this->setRpta(0, 'Agrega al menos un comprobante antes de finalizar.', null), 409);
        }

        $paid       = (float) ($refund->paid_amount ?? 0);
        // Lo ya devuelto a la empresa salió del fondo, así que reduce lo disponible.
        $devuelto   = (float) $refund->payments()->where('payment_type', 'DEVOLUCION_EMPRESA')->sum('amount');
        $difference = round(($paid - $devuelto) - $rendered, 2);   // + sobra (devolución) · - falta (reembolso)

        // Subsanación pedida por el UC1: regresa directo al UC1 (no pasa por el AF).
        if ($refund->uc1_subsanacion) {
            DB::transaction(function () use ($refund, $user, $rendered, $difference) {
                $this->transition($refund, 9, $user, 'Subsanación enviada al UC1', [
                    'rendered_amount'   => $rendered,
                    'difference_amount' => $difference,
                    'uc1_subsanacion'   => false,
                    'updated_by'        => $user->id,
                ]);
            });
            return response()->json($this->setRpta(1, 'Subsanación enviada al UC1', ['redirect' => route('requirements.index')]));
        }

        // Faltante → 8 REEMBOLSO (lo cubre el GF). Sobrante o cuadra → 7 RENDIDO (lo valida el AF).
        $to   = $difference < 0 ? 8 : 7;
        $note = $difference < 0 ? 'Rendición con faltante: requiere reembolso del GF' : 'Rendición presentada por AA';

        DB::transaction(function () use ($refund, $user, $rendered, $difference, $to, $note) {
            $this->transition($refund, $to, $user, $note, [
                'rendered_amount'   => $rendered,
                'difference_amount' => $difference,
                'updated_by'        => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Rendición presentada', ['redirect' => route('requirements.index')]));
    }

    /** GF deposita el reembolso del faltante: 8 REEMBOLSO → 7 RENDIDO (ya cuadra). */
    public function reembolso(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'GF') {
            abort(403);
        }
        if ((int) $refund->status !== 8) {
            return response()->json($this->setRpta(0, 'La orden no está en reembolso.', null), 409);
        }

        $data = $request->validate([
            'source_account_id' => ['required', 'exists:company_accounts,id'],
            'payment_date'      => ['required', 'date'],
            'transaction_code'  => ['nullable', 'string', 'max:100'],
            'constancia'        => ['required', 'file', 'max:10240'],
        ], [
            'source_account_id.required' => 'Selecciona la cuenta de origen.',
        ]);
        $account = CompanyAccount::with('company')->findOrFail($data['source_account_id']);

        $faltante = abs((float) $refund->difference_amount);
        $file     = $request->file('constancia');

        DB::transaction(function () use ($refund, $user, $account, $data, $faltante, $file) {
            RefundPayment::create([
                'refund_id'           => $refund->id,
                'payment_type'        => 'REEMBOLSO_TRABAJADOR',
                'amount'              => $faltante,
                'payment_date'        => $data['payment_date'],
                'bank_origin'         => $account->bank,
                'account_origin'      => $account->account_number,
                'bank_destination'    => $refund->beneficiary_bank,
                'account_destination' => $refund->beneficiary_account,
                'transaction_code'    => $data['transaction_code'] ?? null,
                'file_name'           => $file->getClientOriginalName(),
                'file_path'           => FileStorage::put($file, 'requirements/reembolsos'),
                'notes'               => 'Reembolso del faltante desde ' . ($account->company->name ?? '—'),
                'uploaded_by'         => $user->id,
                'uploaded_at'         => now(),
            ]);
            $this->transition($refund, 7, $user, 'Reembolso depositado por GF', [
                'paid_amount'       => (float) $refund->paid_amount + $faltante,
                'difference_amount' => 0,
                'updated_by'        => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Reembolso registrado', ['redirect' => route('requirements.index')]));
    }

    /** AA registra la devolución del sobrante a la empresa (estado 7, no cambia de estado). */
    public function devolucion(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'AA' || $refund->responsible_id !== $user->id) {
            abort(403);
        }
        if ((int) $refund->status !== 7 || (float) $refund->difference_amount <= 0) {
            return response()->json($this->setRpta(0, 'Esta orden no requiere devolución.', null), 409);
        }
        if ($refund->payments()->where('payment_type', 'DEVOLUCION_EMPRESA')->exists()) {
            return response()->json($this->setRpta(0, 'La devolución ya fue registrada.', null), 409);
        }

        $data = $request->validate([
            'payment_date'     => ['required', 'date'],
            'transaction_code' => ['nullable', 'string', 'max:100'],
            'constancia'       => ['required', 'file', 'max:10240'],
        ]);
        $file  = $request->file('constancia');
        $monto = (float) $refund->difference_amount;
        // El dinero vuelve a la cuenta de donde salió el abono.
        $abono = $refund->payments()->where('payment_type', 'ABONO_INICIAL')->latest('id')->first();

        RefundPayment::create([
            'refund_id'           => $refund->id,
            'payment_type'        => 'DEVOLUCION_EMPRESA',
            'amount'              => $monto,
            'payment_date'        => $data['payment_date'],
            'bank_origin'         => $refund->beneficiary_bank,
            'account_origin'      => $refund->beneficiary_account,
            'bank_destination'    => $abono?->bank_origin,
            'account_destination' => $abono?->account_origin,
            'transaction_code'    => $data['transaction_code'] ?? null,
            'file_name'        => $file->getClientOriginalName(),
            'file_path'        => FileStorage::put($file, 'requirements/devoluciones'),
            'notes'            => 'Devolución del sobrante a la empresa',
            'uploaded_by'      => $user->id,
            'uploaded_at'      => now(),
        ]);

        return response()->json($this->setRpta(1, 'Devolución registrada', ['redirect' => route('requirements.show', $refund)]));
    }

    /** AF da conforme a la rendición: 7 RENDIDO → 9 CONFORME (exige liquidación saldada). */
    public function conforme(Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'AF') {
            abort(403);
        }
        if ((int) $refund->status !== 7) {
            return response()->json($this->setRpta(0, 'La orden no está en validación.', null), 409);
        }
        if ((float) $refund->difference_amount > 0 && !$refund->payments()->where('payment_type', 'DEVOLUCION_EMPRESA')->exists()) {
            return response()->json($this->setRpta(0, 'Falta que el AA registre la devolución del sobrante.', null), 409);
        }

        DB::transaction(function () use ($refund, $user) {
            $this->transition($refund, 9, $user, 'Rendición conforme (AF)', ['updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Rendición conforme', ['redirect' => route('requirements.index')]));
    }

    /** AF observa la rendición y la devuelve al AA: 7 RENDIDO → 6 CONSTANCIA_ADJUNTA. */
    public function observeRendicion(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'AF') {
            abort(403);
        }
        if ((int) $refund->status !== 7) {
            return response()->json($this->setRpta(0, 'La orden no está en validación.', null), 409);
        }
        $data = $request->validate([
            'obs_type' => ['required'],
            'comment'  => ['required', 'string', 'max:1000'],
        ], [
            'obs_type.required' => 'Selecciona el tipo de observación.',
            'comment.required'  => 'Escribe el motivo de la observación.',
        ]);
        $comment = $this->buildObsComment($data['obs_type'], $data['comment']);

        DB::transaction(function () use ($refund, $user, $comment) {
            RefundObservation::create([
                'refund_id'   => $refund->id,
                'from_status' => 7,
                'to_status'   => 6,
                'observed_by' => $user->id,
                'role'        => $user->user_type,
                'comment'     => $comment,
                'created_at'  => now(),
            ]);
            $this->transition($refund, 6, $user, 'Rendición observada por AF', ['updated_by' => $user->id]);
        });

        return response()->json($this->setRpta(1, 'Rendición observada', ['redirect' => route('requirements.index')]));
    }

    /** UC1 cierra la orden: 9 CONFORME → 10 CERRADO. */
    public function cerrar(Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'UC1') {
            abort(403);
        }
        if ((int) $refund->status !== 9) {
            return response()->json($this->setRpta(0, 'La orden no está conforme para cierre.', null), 409);
        }

        DB::transaction(function () use ($refund, $user) {
            $this->transition($refund, 10, $user, 'Orden cerrada (UC1)', [
                'closed_by'  => $user->id,
                'closed_at'  => now(),
                'updated_by' => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Orden cerrada', ['redirect' => route('requirements.index')]));
    }

    /** UC1 observa una orden conforme: 9 CONFORME → 6 (vuelve al AA para subsanar; al re-rendir regresa al UC1). */
    public function observeUc1(Request $request, Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'UC1') {
            abort(403);
        }
        if ((int) $refund->status !== 9) {
            return response()->json($this->setRpta(0, 'La orden no está conforme.', null), 409);
        }
        $data = $request->validate([
            'obs_type' => ['required'],
            'comment'  => ['required', 'string', 'max:1000'],
        ], [
            'obs_type.required' => 'Selecciona el tipo de observación.',
            'comment.required'  => 'Escribe el motivo de la observación.',
        ]);
        $comment = $this->buildObsComment($data['obs_type'], $data['comment']);

        DB::transaction(function () use ($refund, $user, $comment) {
            RefundObservation::create([
                'refund_id'   => $refund->id,
                'from_status' => 9,
                'to_status'   => 6,
                'observed_by' => $user->id,
                'role'        => $user->user_type,
                'comment'     => $comment,
                'created_at'  => now(),
            ]);
            // Vuelve al AA (rendición). El flag hace que al finalizar regrese al UC1, no al AF.
            $this->transition($refund, 6, $user, 'Observado por UC1 (subsanación)', [
                'uc1_subsanacion' => true,
                'updated_by'      => $user->id,
            ]);
        });

        return response()->json($this->setRpta(1, 'Orden observada', ['redirect' => route('requirements.index')]));
    }

    /** Verifica que el actor sea el AA dueño y la orden esté en rendición (estado 6). */
    private function guardRendicion(Refund $refund): void
    {
        $user = auth()->user();
        if ($user->user_type !== 'AA' || $refund->responsible_id !== $user->id) {
            abort(403);
        }
        if ((int) $refund->status !== 6) {
            abort(response()->json($this->setRpta(0, 'La orden no está en rendición.', null), 409));
        }
    }

    /**
     * ¿Este usuario puede editar esta orden?
     * AA responsable: cuando está creada/observada (0 o 4).
     * GA: solo mientras está Por revisar (1), antes de aprobarla.
     */
    private function userCanEdit(Refund $refund, $user): bool
    {
        $status = (int) $refund->status;
        if ($user->user_type === 'AA' && $refund->responsible_id === $user->id && in_array($status, [0, 4], true)) {
            return true;
        }
        if ($user->user_type === 'GA' && $status === 1) {
            return true;
        }
        return false;
    }

    /** Verifica que el actor sea GA y la orden esté en revisión (estado 1). */
    private function guardGaReview(Refund $refund)
    {
        $user = auth()->user();
        if ($user->user_type !== 'GA') {
            abort(403);
        }
        if ((int) $refund->status !== 1) {
            abort(response()->json($this->setRpta(0, 'La orden ya no está en revisión.', null), 409));
        }
        return $user;
    }

    /** Antepone el tipo de observación (master main=20) al comentario: "[Falta de sustento] ...". */
    private function buildObsComment($obsType, string $comment): string
    {
        $desc = Master::where('main', 52)->where('value', $obsType)->value('description');
        return $desc ? '[' . $desc . '] ' . $comment : $comment;
    }

    /** Cambia el estado y deja la bitácora correspondiente. */
    private function transition(Refund $refund, int $to, $user, string $notes, array $extra = []): void
    {
        $from = (int) $refund->status;
        $refund->update(array_merge(['status' => $to], $extra));
        RefundStatusLog::create([
            'refund_id'   => $refund->id,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $user->id,
            'changed_at'  => now(),
            'notes'       => $notes,
        ]);
    }

    /** Centros de costo de un área (select dependiente). */
    public function costCenters($area)
    {
        $list = Area::find($area)
            ?->costCenters()
            ->orderBy('cost_centers.description')
            ->get(['cost_centers.id', 'cost_centers.description']) ?? collect();

        return response()->json($this->setRpta(1, 'OK', $list));
    }

    /** Buscar beneficiario por documento (autocompletado + cuentas activas). */
    public function searchBeneficiary(Request $request)
    {
        $ben = Beneficiary::with('accounts')
            ->where('doc_number', $request->input('doc'))
            ->first();

        if (!$ben) {
            return response()->json($this->setRpta(0, 'Beneficiario no encontrado', null));
        }
        if (!$ben->active) {
            return response()->json($this->setRpta(0, 'El beneficiario está inactivo.', ['exists' => true]));
        }

        return response()->json($this->setRpta(1, 'OK', [
            'beneficiary' => [
                'id'         => $ben->id,
                'name'       => $ben->name,
                'doc_type'   => $ben->doc_type,
                'doc_number' => $ben->doc_number,
                'email'      => $ben->email,
                'phone'      => $ben->phone,
            ],
            'accounts' => $ben->accounts->map(fn ($a) => [
                'id'             => $a->id,
                'bank'           => $a->bank,
                'currency'       => $a->currency,
                'account_number' => $a->account_number,
                'cci'            => $a->cci,
                'is_primary'     => (bool) $a->is_primary,
            ])->values(),
        ]));
    }

    /** Registrar un nuevo beneficiario con sus cuentas bancarias. */
    public function storeBeneficiary(Request $request)
    {
        $data = $request->validate([
            'name'                      => ['required', 'string', 'max:250'],
            'doc_type'                  => ['nullable', 'string', 'max:10'],
            'doc_number'                => ['required', 'string', 'max:20'],
            'email'                     => ['nullable', 'email', 'max:100'],
            'phone'                     => ['nullable', 'string', 'max:20'],
            'accounts'                  => ['required', 'array', 'min:1'],
            'accounts.*.bank'           => ['required'],
            'accounts.*.currency'       => ['required'],
            'accounts.*.account_number' => ['required', 'string'],
            'accounts.*.cci'            => ['nullable', 'string'],
        ], [
            'accounts.required'              => 'Agrega al menos una cuenta bancaria.',
            'accounts.min'                   => 'Agrega al menos una cuenta bancaria.',
            'accounts.*.account_number.required' => 'Ingresa el N° de cuenta.',
        ]);

        if (Beneficiary::where('doc_number', $data['doc_number'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'doc_number' => 'Ya existe un beneficiario con ese documento.',
            ]);
        }

        $uid = auth()->id();

        $ben = DB::transaction(function () use ($data, $uid) {
            $ben = Beneficiary::create([
                'name'       => $data['name'],
                'doc_type'   => $data['doc_type'] ?? null,
                'doc_number' => $data['doc_number'],
                'email'      => $data['email'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'active'     => true,
                'created_by' => $uid,
                'updated_by' => $uid,
            ]);

            foreach ($data['accounts'] ?? [] as $i => $acc) {
                BeneficiaryAccount::create([
                    'beneficiary_id' => $ben->id,
                    'bank'           => Master::find($acc['bank'])?->description ?? $acc['bank'],
                    'currency'       => $acc['currency'],
                    'account_number' => $acc['account_number'],
                    'cci'            => $acc['cci'] ?? null,
                    'is_primary'     => $i === 0,
                    'created_by'     => $uid,
                    'updated_by'     => $uid,
                ]);
            }

            return $ben->load('accounts');
        });

        return response()->json($this->setRpta(1, 'Beneficiario registrado', [
            'beneficiary' => [
                'id'         => $ben->id,
                'name'       => $ben->name,
                'doc_type'   => $ben->doc_type,
                'doc_number' => $ben->doc_number,
                'email'      => $ben->email,
                'phone'      => $ben->phone,
            ],
            'accounts' => $ben->accounts->map(fn ($a) => [
                'id'             => $a->id,
                'bank'           => $a->bank,
                'currency'       => $a->currency,
                'account_number' => $a->account_number,
                'cci'            => $a->cci,
                'is_primary'     => (bool) $a->is_primary,
            ])->values(),
        ]));
    }
}