<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class CentralAdmin extends Authenticatable
{
    protected $fillable = ['email', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
