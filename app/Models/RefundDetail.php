<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundDetail extends Model
{
    protected $table = 'refund_details';

    protected $fillable = [
        'refund_id', 'description', 'supplier', 'category_id',
        'estimated_amount', 'actual_amount', 'notes',
    ];

    protected $casts = [
        'estimated_amount' => 'decimal:2',
        'actual_amount'    => 'decimal:2',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function category()
    {
        return $this->belongsTo(RefundCategory::class, 'category_id');
    }
}