<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundObservation extends Model
{
    protected $table = 'refund_observations';

    public $timestamps = false;   // usa created_at

    protected $fillable = [
        'refund_id', 'from_status', 'to_status', 'observed_by', 'role', 'comment', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function observer()
    {
        return $this->belongsTo(User::class, 'observed_by');
    }
}