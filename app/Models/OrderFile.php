<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFile extends Model
{
    protected $table = 'orders_file';

    // id autoincremental (sequence orders_file_id_seq); la BD asigna la PK.
    protected $fillable = ['type_file', 'document_number', 'registration_code', 'has_retention', 'amount', 'emission_date', 'order_id', 'path', 'comentario', 'principal', 'created_by', 'updated_by'];

    protected $casts = [
        'has_retention' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
