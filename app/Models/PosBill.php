<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosBill extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['simulated' => 'boolean', 'settled_at' => 'datetime', 'settled_total_minor' => 'integer'];
    }
}
