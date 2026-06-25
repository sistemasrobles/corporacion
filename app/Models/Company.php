<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'ruc', 'source_bank', 'source_account_number', 'source_cci', 'created_by', 'updated_by'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'company_id');
    }

    public function accounts()
    {
        return $this->hasMany(CompanyAccount::class, 'company_id');
    }
}
