<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    protected $table = 'order_history';

    protected $fillable = [
        'from_user', 'to_user', 'from_status', 'to_status',
        'coment', 'order_id', 'created_by', 'updated_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
