<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundFile extends Model
{
    protected $table = 'refund_files';

    public $timestamps = false;   // usa uploaded_at

    protected $fillable = [
        'refund_id', 'detail_id', 'type_file', 'file_name', 'file_path', 'file_size',
        'amount', 'issue_date', 'supplier', 'document_number', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'issue_date'  => 'date',
        'uploaded_at' => 'datetime',
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