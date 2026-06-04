<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSequence extends Model
{
    protected $table = 'order_sequences';

    public $incrementing = false;

    // Composite primary key — use where('year_code', $y)->where('order_type', $t) for lookups
    protected $primaryKey = null;

    protected $fillable = ['year_code', 'order_type', 'last_number', 'created_by', 'updated_by'];
}
