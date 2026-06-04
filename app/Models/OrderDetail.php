<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $table = 'order_details';

    protected $fillable = [
        'order_id', 'required_date', 'period', 'suggested_amount', 'justification',
        'category_id', 'currency', 'tc', 'area_id', 'sede_id', 'cc_id', 'supplier_id', 'supplier_account_id',
        'payment_id', 'payment_schedule_id', 'condition_payment', 'quotas', 'expiration_date',
        'grabable', 'discount', 'discount_type_id', 'igv', 'sub_total', 'total', 'amount_neto',
        'observation', 'items', 'codigo_registro', 'codigo_banco', 'source_account', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'cc_id' => 'array',
        'items' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
