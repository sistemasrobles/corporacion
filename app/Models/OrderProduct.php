<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $table = 'order_products';

    protected $fillable = ['product_id', 'order_id', 'description', 'quantity', 'unit_price', 'sub_total', 'created_by', 'updated_by'];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'sub_total'  => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
