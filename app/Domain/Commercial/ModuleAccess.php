<?php

namespace App\Domain\Commercial;

use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ModuleAccess
{
    public function forRestaurant(Staff $staff): Collection
    {
        if (! $staff->is_active || ! $staff->restaurant()->operational()->exists()) {
            return collect();
        }

        return DB::table('erp_modules')
            ->join('restaurant_entitlements', 'erp_modules.id', '=', 'restaurant_entitlements.erp_module_id')
            ->where('restaurant_entitlements.restaurant_id', $staff->restaurant_id)
            ->where('restaurant_entitlements.is_enabled', true)
            ->where('erp_modules.is_enabled', true)
            ->select('erp_modules.*')
            ->orderBy('erp_modules.name')->get()
            ->filter(fn ($module) => in_array($staff->role, ModuleCatalog::MODULES[$module->code][2] ?? [], true));
    }

    public function forBranch(Staff $staff, string $branch): Collection
    {
        if (! $staff->activeBranches()->whereKey($branch)->exists()) {
            return collect();
        }

        $enabled = DB::table('branch_entitlements')->where('restaurant_id', $staff->restaurant_id)
            ->where('branch_id', $branch)->where('is_enabled', true)->pluck('erp_module_id');

        return $this->forRestaurant($staff)->whereIn('id', $enabled);
    }

    public function authorizeExternalActivity(Staff $staff): void
    {
        abort_unless($staff->is_active && $staff->restaurant()->operational()->where('tenant_type', 'production')->exists(), 403);
    }
}
