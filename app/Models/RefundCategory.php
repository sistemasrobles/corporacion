<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundCategory extends Model
{
    protected $table = 'refund_categories';

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}