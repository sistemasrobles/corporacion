<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundStatus extends Model
{
    protected $table = 'refund_status';

    public $incrementing = false;     // ids fijos 0..10
    public $timestamps   = false;

    protected $fillable = [
        'id', 'code', 'name', 'description', 'generated_by', 'visible_to', 'is_final',
    ];

    protected $casts = [
        'is_final' => 'boolean',
    ];
}