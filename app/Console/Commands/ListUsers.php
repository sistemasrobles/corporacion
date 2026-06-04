<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ListUsers extends Command
{
    protected $signature = 'list:users';

    public function handle()
    {
        $this->table(
            ['ID', 'Nombre', 'Rol'],
            User::all(['id', 'name', 'user_type'])->toArray()
        );
    }
}