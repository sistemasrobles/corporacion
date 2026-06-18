<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundStatusLog extends Model
{
    protected $table = 'refund_status_log';

    public $timestamps = false;   // usa changed_at

    protected $fillable = [
        'refund_id', 'from_status', 'to_status', 'changed_by', 'changed_at', 'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function fromStatus()
    {
        return $this->belongsTo(RefundStatus::class, 'from_status');
    }

    public function toStatus()
    {
        return $this->belongsTo(RefundStatus::class, 'to_status');
    }
}