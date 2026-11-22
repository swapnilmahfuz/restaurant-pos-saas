<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'pos_payment_methods' => 'array'];
    }
}
