<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [
        'name', 'doc_type', 'doc_number', 'email', 'phone', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function accounts()
    {
        return $this->hasMany(BeneficiaryAccount::class, 'beneficiary_id');
    }
}