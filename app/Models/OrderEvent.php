<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $table = 'orders_events';

    protected $fillable = [
        'order_id', 'order_quota_id', 'event_type', 'description', 'file',
        'created_by', 'updated_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function quota()
    {
        return $this->belongsTo(OrderQuota::class, 'order_quota_id');
    }
}