<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderPoliticasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $politicas = [
            'JA'  => [1],
            'AA'  => [1, 2, 5, 8, 55],
            'GA'  => [2, 3, 4, 5, 100],
            'GF'  => [3, 102, 200, 201, 202],
            'AF'  => [8, 55, 200, 201, 202],
            'UC1' => [9, 91, 100],
            'UC2' => [55, 91, 92, 202],
            'UC3' => [92],
            'UC4' => [92],
            'UC5' => [101, 102],
        ];

        DB::table('orders_politicas')->truncate();

        foreach ($politicas as $tipo => $estados) {
            foreach ($estados as $status_id) {
                DB::table('orders_politicas')->insert([
                    'user_type'  => $tipo,
                    'status_id'  => $status_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Políticas de órdenes populadas.');
    }
}
