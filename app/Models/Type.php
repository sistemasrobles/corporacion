<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $fillable = ['descripcion', 'created_by', 'updated_by'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'type_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'type_user', 'type_id', 'user_id');
    }
}
