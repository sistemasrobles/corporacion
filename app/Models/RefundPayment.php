<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundPayment extends Model
{
    protected $table = 'refund_payments';

    public $timestamps = false;   // usa uploaded_at

    protected $fillable = [
        'refund_id', 'payment_type', 'amount', 'payment_date',
        'bank_origin', 'account_origin', 'bank_destination', 'account_destination', 'transaction_code',
        'file_name', 'file_path', 'notes', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
        'uploaded_at'  => 'datetime',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}