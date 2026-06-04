<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Status;

class ListStatus extends Command
{
    protected $signature = 'list:status';

    public function handle()
    {
        $this->table(
            ['ID', 'Descripción'],
            Status::orderBy('id')->get(['id', 'description'])->toArray()
        );
    }
}
