<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['cod_contable', 'description', 'created_by', 'updated_by'];

    public function costCenters()
    {
        return $this->belongsToMany(CostCenter::class, 'area_cost_center', 'area_id', 'cc_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'area_id');
    }
}
