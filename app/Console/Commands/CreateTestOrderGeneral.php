<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderQuota;

class CreateTestOrderGeneral extends Command
{
    protected $signature = 'create:test-order-general';

    public function handle()
    {
        // Crear orden GENERAL
        $order = Order::create([
            'company_id' => 1,
            'code' => 'OS-2026000020',
            'status' => 102,
            'title' => 'ORDEN TEST GENERAL',
            'type_id' => 1,
            'format_id' => 'OS',
            'payment_schedule_id' => 1, // GENERAL
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Crear detalle
        OrderDetail::create([
            'order_id' => $order->id,
            'supplier_id' => 1,
            'area_id' => 1,
            'category_id' => 1,
            'amount_neto' => 3000,
            'currency' => 'PEN',
            'required_date' => now()->addDays(60),
            'expiration_date' => now()->addDays(60),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Crear 2 cuotas
        OrderQuota::create([
            'order_id' => $order->id,
            'quota_number' => 1,
            'amount' => 1500,
            'due_date' => now()->addDays(15),
            'status' => 200,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        OrderQuota::create([
            'order_id' => $order->id,
            'quota_number' => 2,
            'amount' => 1500,
            'due_date' => now()->addDays(30),
            'status' => 200,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $this->info('Orden GENERAL creada: ' . $order->code);
    }
}