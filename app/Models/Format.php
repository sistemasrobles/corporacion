<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Format extends Model
{
    protected $fillable = ['description', 'abrev', 'created_by', 'updated_by'];

    public function categories()
    {
        return $this->hasMany(Category::class, 'format_id');
    }
}
