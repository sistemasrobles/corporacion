<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderQuota extends Model
{
    use HasFactory;

    protected $table = 'orders_quotas';

    protected $fillable = [
        'order_id', 'quota_number', 'amount', 'due_date', 'status',
        'monto_ok', 'rebote', 'observacion', 'constancia', 'deposit_date', 'constancia_date',
        'operation_number', 'source_company_id', 'source_bank',
        'source_account_number', 'source_cci',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'deposit_date' => 'date',
        'constancia_date' => 'datetime',
        'monto_ok' => 'boolean',
        'rebote' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
