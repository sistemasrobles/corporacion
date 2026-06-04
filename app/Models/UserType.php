<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $fillable = ['prefijo', 'description', 'created_by', 'updated_by'];

    public function users()
    {
        return $this->hasMany(User::class, 'user_type', 'prefijo');
    }
}
