<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestOrdersSeeder extends Seeder
{
    public function run(): void
    {
        // ── Limpiar órdenes de prueba previas (code TST-*) ──
        $prev = Order::where('code', 'like', 'TST-%')->pluck('id');
        if ($prev->isNotEmpty()) {
            OrderHistory::whereIn('order_id', $prev)->delete();
            OrderDetail::whereIn('order_id', $prev)->delete();
            Order::whereIn('id', $prev)->delete();
        }

        // ── Resetear secuencias (PostgreSQL) si están vacías ──
        foreach (['orders', 'order_details', 'order_history'] as $t) {
            $max = DB::table($t)->max('id') ?? 0;
            DB::statement("SELECT setval(pg_get_serial_sequence('{$t}','id'), GREATEST({$max}, 1))");
        }

        $aa = User::where('user_type', 'AA')->value('id') ?? 1;   // ymanrique
        $ga = User::where('user_type', 'GA')->value('id') ?? 1;   // admin

        // [status, responsable, formato, título, area, supplier, monto, moneda, obs_to_user]
        $rows = [
            [1,   $aa, 'OC', 'Compra de laptops Dell',        14, 2, 5000.00, 'PEN', null],   // JA, AA
            [1,   $aa, 'OS', 'Servicio de limpieza mensual',   1, 3, 1200.00, 'PEN', null],   // JA, AA
            [102, $aa, 'OC', 'Cronograma de materiales',      10, 1, 8000.00, 'PEN', null],   // AA, GF
            [2,   $ga, 'OC', 'Equipos de oficina',             2, 2, 3500.00, 'PEN', null],   // GA
            [3,   $ga, 'OS', 'Consultoría legal externa',      6, 2, 9000.00, 'USD', null],   // GF
            [8,   $aa, 'OC', 'Sustento de pago proveedor',     4, 3, 4200.00, 'PEN', null],   // AF
            [9,   $aa, 'OC', 'Conforme contable',              2, 1, 2700.00, 'PEN', null],   // UC1
            [100, $aa, 'OS', 'Orden aceptada',                 2, 2, 1500.00, 'PEN', null],   // UC1
            [91,  $aa, 'OC', 'Pendiente código de registro',   2, 3, 6100.00, 'PEN', null],   // UC2, UC3
            [92,  $aa, 'OC', 'Pendiente código de banco',      2, 1, 3300.00, 'PEN', null],   // UC3, UC4
            [101, $aa, 'OS', 'Confirmado para abono',          2, 2, 2100.00, 'PEN', null],   // UC5
            [55,  $aa, 'OC', 'Abonos completados',             2, 3, 4800.00, 'PEN', null],   // UC2
            [5,   $aa, 'OC', 'Observada hacia AA',             1, 2, 2500.00, 'PEN', 'AA'],   // AA (observado)
            [5,   $ga, 'OC', 'Observada hacia GA',             1, 2, 2600.00, 'PEN', 'GA'],   // GA (observado)
        ];

        $i = 0;
        foreach ($rows as [$status, $resp, $format, $title, $area, $supplier, $amount, $currency, $obsTo]) {
            $i++;
            $code = sprintf('TST-%s-%04d', $format, $i);

            $order = Order::create([
                'code'                => $code,
                'status'              => $status,
                'title'               => $title,
                'type_id'             => 1,
                'format_id'           => $format,
                'user_responsible'    => $resp,
                'company_id'          => 1,
                'payment_schedule_id' => 1,
                'created_by'          => $resp,
                'updated_by'          => $resp,
            ]);

            OrderDetail::create([
                'order_id'        => $order->id,
                'currency'        => $currency,
                'tc'              => $currency === 'USD' ? 3.75 : 1,
                'amount_neto'     => $amount,
                'sub_total'       => $amount,
                'total'           => $amount,
                'area_id'         => $area,
                'supplier_id'     => $supplier,
                'required_date'   => now()->toDateString(),
                'expiration_date' => now()->addDays(30)->toDateString(),
                'created_by'      => $resp,
                'updated_by'      => $resp,
            ]);

            // Estado 5 (OBSERVADO): el historial debe apuntar to_user = rol
            if ($status === 5 && $obsTo) {
                OrderHistory::create([
                    'from_user'   => $obsTo === 'AA' ? 'GA' : 'GF',
                    'to_user'     => $obsTo,
                    'from_status' => $obsTo === 'AA' ? 2 : 3,
                    'to_status'   => 5,
                    'coment'      => '[PRUEBA] Orden observada para ' . $obsTo,
                    'order_id'    => $order->id,
                    'created_by'  => $resp,
                    'updated_by'  => $resp,
                ]);
            }
        }

        $this->command->info("✓ {$i} órdenes de prueba creadas (code TST-*).");
    }
}