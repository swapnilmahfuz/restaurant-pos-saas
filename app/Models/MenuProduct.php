<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuProduct extends Model
{
    protected $fillable = ['restaurant_id', 'category_id', 'name', 'description', 'price_minor', 'active'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'active' => 'boolean'];
    }
}
