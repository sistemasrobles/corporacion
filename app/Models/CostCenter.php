<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $table = 'cost_centers';

    protected $fillable = ['cod_contable', 'description', 'created_by', 'updated_by'];

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_cost_center', 'cc_id', 'area_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'cc_id');
    }
}
