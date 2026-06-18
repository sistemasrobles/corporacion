<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundSequence extends Model
{
    protected $table = 'refund_sequences';

    protected $fillable = ['year_code', 'last_number'];
}