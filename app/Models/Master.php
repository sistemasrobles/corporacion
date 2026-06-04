<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Master extends Model
{
    protected $fillable = ['type', 'description', 'description_min', 'value', 'value2', 'main', 'created_by', 'updated_by'];
}
