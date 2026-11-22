<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $fillable = ['name', 'email'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'access_expires_at' => 'datetime', 'retired_at' => 'datetime'];
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereNull('retired_at')
            ->where(fn (Builder $access) => $access
                ->where(fn (Builder $production) => $production->where('tenant_type', 'production')->whereNull('access_expires_at'))
                ->orWhere('access_expires_at', '>', now()));
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
