<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAccount extends Model
{
    protected $table = 'company_accounts';

    protected $fillable = [
        'company_id', 'currency', 'bank', 'account_number', 'cci',
        'is_primary', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}