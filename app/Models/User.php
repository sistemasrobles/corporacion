<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'user_type', 'uc_level', 'password', 'created_by', 'updated_by'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function userType()
    {
        return $this->belongsTo(UserType::class, 'user_type', 'prefijo');
    }

    public function types()
    {
        return $this->belongsToMany(Type::class, 'type_user', 'user_id', 'type_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
