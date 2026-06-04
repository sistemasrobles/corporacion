<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUCUsers extends Command
{
    protected $signature = 'create:uc-users';
    protected $description = 'Create UC2-UC5 users';

    public function handle()
    {
        $users = [
            ['name' => 'PIERO', 'email' => 'piero@empresa.com', 'user_type' => 'UC2'],
            ['name' => 'BRILLIT', 'email' => 'brillit@empresa.com', 'user_type' => 'UC3'],
            ['name' => 'MELANY', 'email' => 'melany@empresa.com', 'user_type' => 'UC4'],
            ['name' => 'ANNICK', 'email' => 'annick@empresa.com', 'user_type' => 'UC5'],
        ];

        foreach ($users as $userData) {
            $exists = DB::table('users')->where('email', $userData['email'])->exists();

            if (!$exists) {
                DB::table('users')->insert([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'user_type' => $userData['user_type'],
                    'password' => Hash::make('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("✅ {$userData['name']} ({$userData['user_type']}) creado");
            } else {
                $this->warn("⚠️  {$userData['email']} ya existe");
            }
        }

        $this->info("\n✅ Usuarios UC creados exitosamente");
    }
}