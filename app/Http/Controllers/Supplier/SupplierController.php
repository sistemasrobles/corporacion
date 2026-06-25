<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\Master;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /** El módulo de Proveedores es exclusivo de AA y GA. */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!in_array(auth()->user()?->user_type, ['AA', 'GA'], true)) {
                abort(403);
            }
            return $next($request);
        });
    }

    /** Listado de proveedores. */
    public function index()
    {
        $suppliers = $this->collectSuppliers();
        $banks     = Master::where('main', 67)->whereNotNull('description')
            ->orderBy('description')->pluck('description', 'id');   // para el modal de registro

        return view('Supplier.index', compact('suppliers', 'banks'));
    }

    /** Filas frescas del listado (botón Recargar): re-consulta y devuelve solo el partial. */
    public function rows()
    {
        $suppliers = $this->collectSuppliers();
        $html      = view('Supplier.partials.supplier-rows', compact('suppliers'))->render();

        return response()->json($this->setRpta(1, 'OK', ['html' => $html, 'count' => $suppliers->count()]));
    }

    /** Proveedores con sus cuentas (para pintar la tabla). */
    private function collectSuppliers()
    {
        return Supplier::with('accounts')->orderBy('name')->get();
    }

    /** Registrar un nuevo proveedor con sus cuentas bancarias. */
    public function store(Request $request)
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
            'active'                    => ['nullable', 'boolean'],
            'accounts'                  => ['array'],
            'accounts.*.bank'           => ['required'],
            'accounts.*.currency'       => ['required'],
            'accounts.*.account_number' => ['required', 'string'],
            'accounts.*.cci'            => ['nullable', 'string'],
        ], [
            'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos.',
        ]);

        // RUC único (no duplicar proveedores)
        if (Supplier::where('ruc', $data['ruc'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ruc' => 'Ya existe un proveedor con ese RUC.',
            ]);
        }

        $uid = auth()->id();

        $supplier = DB::transaction(function () use ($data, $uid) {
            // Evita choques de secuencias tras migrar de MySQL
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
                'active'     => $data['active'] ?? true,
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

            return $supplier;
        });

        return response()->json($this->setRpta(1, 'Proveedor registrado', ['id' => $supplier->id]));
    }

    /** Datos de un proveedor + sus cuentas (para precargar el modal de edición). */
    public function show(Supplier $supplier)
    {
        $supplier->load('accounts');

        return response()->json($this->setRpta(1, 'OK', [
            'id'        => $supplier->id,
            'ruc'       => $supplier->ruc,
            'name'      => $supplier->name,
            'address'   => $supplier->address,
            'provincia' => $supplier->provincia,
            'district'  => $supplier->district,
            'contact'   => $supplier->contact,
            'phone'     => $supplier->phone,
            'email'     => $supplier->email,
            'active'    => (bool) $supplier->active,
            'accounts'  => $supplier->accounts->map(fn ($a) => [
                'id'             => $a->id,
                'bank'           => $a->bank,          // nombre (el select se prepara por nombre)
                'currency'       => $a->currency,
                'account_number' => $a->account_number,
                'cci'            => $a->cci,
            ])->values(),
        ]));
    }

    /** Actualiza un proveedor y sus cuentas (upsert por id: conserva las referenciadas). */
    public function update(Request $request, Supplier $supplier)
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
            'active'                    => ['nullable', 'boolean'],
            'accounts'                  => ['array'],
            'accounts.*.id'             => ['nullable'],
            'accounts.*.bank'           => ['required'],
            'accounts.*.currency'       => ['required'],
            'accounts.*.account_number' => ['required', 'string'],
            'accounts.*.cci'            => ['nullable', 'string'],
        ], [
            'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos.',
        ]);

        // RUC único (excluyéndose a sí mismo)
        if (Supplier::where('ruc', $data['ruc'])->where('id', '!=', $supplier->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ruc' => 'Ya existe otro proveedor con ese RUC.',
            ]);
        }

        $uid = auth()->id();

        DB::transaction(function () use ($data, $uid, $supplier) {
            $this->syncSequences(['supplier_accounts']);

            $supplier->update([
                'ruc'        => $data['ruc'],
                'name'       => $data['name'],
                'address'    => $data['address'] ?? null,
                'provincia'  => $data['provincia'] ?? null,
                'district'   => $data['district'] ?? null,
                'contact'    => $data['contact'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'email'      => $data['email'] ?? null,
                'active'     => $data['active'] ?? $supplier->active,
                'updated_by' => $uid,
            ]);

            // Upsert de cuentas: actualiza las existentes (conserva su id → no huérfana
            // referencias de órdenes), agrega nuevas y borra solo las quitadas.
            $keepIds = [];
            foreach ($data['accounts'] ?? [] as $i => $acc) {
                $attrs = [
                    'supplier_id'    => $supplier->id,
                    'bank'           => Master::find($acc['bank'])?->description ?? $acc['bank'],
                    'currency'       => $acc['currency'],
                    'account_number' => $acc['account_number'],
                    'cci'            => $acc['cci'] ?? null,
                    'is_primary'     => $i === 0,
                    'updated_by'     => $uid,
                ];
                $existing = !empty($acc['id'])
                    ? SupplierAccount::where('id', $acc['id'])->where('supplier_id', $supplier->id)->first()
                    : null;
                if ($existing) {
                    $existing->update($attrs);
                    $keepIds[] = $existing->id;
                } else {
                    $attrs['created_by'] = $uid;
                    $keepIds[] = SupplierAccount::create($attrs)->id;
                }
            }
            SupplierAccount::where('supplier_id', $supplier->id)
                ->whereNotIn('id', $keepIds ?: [0])->delete();
        });

        return response()->json($this->setRpta(1, 'Proveedor actualizado', ['id' => $supplier->id]));
    }

    /** Activa / inactiva un proveedor (soft-delete: no se borra, solo cambia su estado). */
    public function toggleActive(Supplier $supplier)
    {
        $supplier->update([
            'active'     => !$supplier->active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json($this->setRpta(1, $supplier->active ? 'Proveedor activado' : 'Proveedor inactivado', [
            'id'     => $supplier->id,
            'active' => (bool) $supplier->active,
        ]));
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
}