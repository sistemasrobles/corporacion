<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderQuota;
use App\Models\Order;
use App\Models\PaymentSchedule;

class CheckAccountsPayable extends Command
{
    protected $signature = 'check:accounts-payable';
    protected $description = 'Check accounts payable data';

    public function handle()
    {
        $this->info('=== DIAGNÓSTICO CUENTAS POR PAGAR ===');

        $totalQuotas = OrderQuota::count();
        $this->line("Total cuotas en BD: $totalQuotas");

        $totalOrdenes = Order::count();
        $this->line("Total órdenes: $totalOrdenes");

        $conSchedule = Order::whereNotNull('payment_schedule_id')->count();
        $this->line("Órdenes con payment_schedule_id: $conSchedule");

        $this->line("\nPayment Schedules:");
        PaymentSchedule::all()->each(function ($s) {
            $this->line("  - ID: {$s->id}, Name: {$s->name}");
        });

        $this->line("\nPrimeras 5 órdenes con detalles:");
        Order::with('paymentSchedule')->limit(5)->get()->each(function ($o) {
            $schedule = $o->paymentSchedule?->name ?? 'SIN SCHEDULE';
            $this->line("  - {$o->code} (schedule: $schedule)");
        });

        $this->line("\nCuotas por orden:");
        Order::with('quotas')->limit(3)->get()->each(function ($o) {
            $count = $o->quotas()->count();
            $this->line("  - {$o->code}: $count cuotas");
        });

        $this->line("\nCuotas GENERAL:");
        $quotasGENERAL = OrderQuota::whereHas('order', function ($q) {
            $q->whereHas('paymentSchedule', function ($ps) {
                $ps->where('name', 'GENERAL');
            });
        })->count();
        $this->line("  Cuotas GENERAL: $quotasGENERAL");

        $this->line("\nCuotas URGENTE:");
        $quotasURGENTE = OrderQuota::whereHas('order', function ($q) {
            $q->whereHas('paymentSchedule', function ($ps) {
                $ps->where('name', 'URGENTE');
            });
        })->count();
        $this->line("  Cuotas URGENTE: $quotasURGENTE");
    }
}