<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAccount extends Model
{
    protected $table = 'supplier_accounts';

    protected $fillable = [
        'supplier_id', 'currency', 'bank', 'account_number', 'cci',
        'is_primary', 'created_by', 'updated_by',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
