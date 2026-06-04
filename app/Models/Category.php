<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['description', 'format_id', 'created_by', 'updated_by'];

    public function format()
    {
        return $this->belongsTo(Format::class, 'format_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'category_id');
    }
}
