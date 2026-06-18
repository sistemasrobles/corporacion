<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'ruc', 'name', 'address', 'provincia', 'district', 'contact', 'phone', 'email', 'active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function accounts()
    {
        return $this->hasMany(SupplierAccount::class, 'supplier_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'supplier_id');
    }
}
