<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFile extends Model
{
    protected $table = 'orders_file';

    public $incrementing = false;

    protected $fillable = ['id', 'type_file', 'document_number', 'registration_code', 'amount', 'emission_date', 'order_id', 'path', 'comentario', 'principal', 'created_by', 'updated_by'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
