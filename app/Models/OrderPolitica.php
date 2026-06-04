<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPolitica extends Model
{
    use HasFactory;

    protected $fillable = ['user_type', 'status_id'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
