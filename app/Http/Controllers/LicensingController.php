<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\CommercialAudit;
use App\Domain\Commercial\ModuleCatalog;
use App\Models\Branch;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LicensingController
{
    public function index(): View
    {
        return view('central.modules', [
            'modules' => DB::table('erp_modules')->orderBy('name')->get(),
            'audit' => DB::table('commercial_audit_events')->where('event', 'license.platform')->latest('id')->limit(20)->get(),
        ]);
    }

    public function platform(Request $request, string $module): RedirectResponse
    {
        $enabled = $this->enabled($request);

        DB::transaction(function () use ($request, $module, $enabled) {
            $record = $this->module($module);
            if ((bool) $record->is_enabled === $enabled) {
                return;
            }
            DB::table('erp_modules')->where('id', $record->id)->update(['is_enabled' => $enabled]);
            CommercialAudit::record('license.platform', $request->user('central'), details: [
                'module' => $module, 'enabled' => $enabled,
            ]);
        }, 3);

        return to_route('central.modules.index');
    }

    public function restaurant(Request $request, Restaurant $restaurant, string $module): RedirectResponse
    {
        return $this->entitlement($request, $restaurant, $module);
    }

    public function branch(Request $request, Restaurant $restaurant, Branch $branch, string $module): RedirectResponse
    {
        return $this->entitlement($request, $restaurant, $module, $branch);
    }

    private function entitlement(Request $request, Restaurant $restaurant, string $module, ?Branch $branch = null): RedirectResponse
    {
        $enabled = $this->enabled($request);

        DB::transaction(function () use ($request, $restaurant, $module, $branch, $enabled) {
            $record = $this->module($module);
            $restaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $branch = $branch ? $restaurant->branches()->lockForUpdate()->findOrFail($branch->id) : null;
            abort_if($enabled && (
                $restaurant->approval_status !== 'approved' || $restaurant->retired_at ||
                ($branch && $branch->approval_status !== 'approved')
            ), 409);

            $keys = ['restaurant_id' => $restaurant->id, 'erp_module_id' => $record->id];
            if ($branch) {
                $keys['branch_id'] = $branch->id;
            }

            $entitlements = DB::table($branch ? 'branch_entitlements' : 'restaurant_entitlements');
            $previous = (clone $entitlements)->where($keys)->value('is_enabled');
            if ($previous !== null && (bool) $previous === $enabled) {
                return;
            }
            $entitlements->updateOrInsert($keys, ['is_enabled' => $enabled]);
            CommercialAudit::record($branch ? 'license.branch' : 'license.restaurant', $request->user('central'), $restaurant, $branch, [
                'module' => $module, 'enabled' => $enabled,
            ]);
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }

    private function module(string $code): object
    {
        abort_unless(isset(ModuleCatalog::MODULES[$code]), 404);

        return DB::table('erp_modules')->where('code', $code)->lockForUpdate()->firstOrFail();
    }

    private function enabled(Request $request): bool
    {
        $request->validate(['enabled' => ['required', 'boolean']]);

        return $request->boolean('enabled');
    }
}
