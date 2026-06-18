<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryAccount extends Model
{
    protected $table = 'beneficiary_accounts';

    protected $fillable = [
        'beneficiary_id', 'bank', 'currency', 'account_number', 'cci', 'is_primary', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }
}