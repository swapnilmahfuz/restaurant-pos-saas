<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\CommercialAudit;
use App\Domain\Commercial\ModuleCatalog;
use App\Domain\Pos\Menu;
use App\Models\CentralAdmin;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SandboxController
{
    public function demo(Request $request): RedirectResponse
    {
        $current = $request->user('staff');
        $previous = $current?->restaurant;
        abort_if($request->user('central') || ($current && $previous?->tenant_type !== 'demo'), 403);

        $key = (string) Str::uuid();
        $hash = Hash::make(Str::random(40));
        $staff = DB::transaction(function () use ($previous, $current, $key, $hash) {
            if ($previous) {
                $previous = Restaurant::query()->lockForUpdate()->findOrFail($previous->id);
                abort_if($previous->retired_at, 409);
                $this->retire($previous);
            }

            $owner = $this->provision([
                'name' => 'Sample Bistro', 'email' => "demo-$key@example.invalid",
                'owner_name' => 'Sample Cashier', 'owner_email' => "staff-$key@example.invalid",
            ], $hash, 'demo', ['pos']);

            CommercialAudit::record('demo.created', $current, $owner->restaurant, details: [
                'previous_restaurant_id' => $previous?->id, 'modules' => ['pos'],
            ]);

            return $owner;
        }, 3);

        $request->session()->invalidate();
        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();

        return to_route('staff.home');
    }

    public function trial(Request $request): RedirectResponse
    {
        return $this->provisionFromRequest($request, 'trial');
    }

    public function replaceTrial(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:reset,convert']])['action'];

        return $this->provisionFromRequest($request, $action === 'convert' ? 'production' : 'trial', $restaurant);
    }

    public function expiration(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $input = $request->validate(['access_days' => ['present', 'nullable', 'integer', 'min:1', 'max:366']]);
        DB::transaction(function () use ($request, $restaurant, $input) {
            $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $days = $input['access_days'];
            abort_if($tenant->retired_at || $tenant->tenant_type === 'demo' || $tenant->approval_status !== 'approved', 409);
            abort_if($tenant->tenant_type === 'trial' && $days === null, 422);
            $tenant->forceFill(['access_expires_at' => $days === null ? null : now()->addDays((int) $days)])->save();
            CommercialAudit::record('access.expiry', $request->user('central'), $tenant, details: [
                'expires_at' => $tenant->access_expires_at?->toIso8601String(),
            ]);
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }

    private function provisionFromRequest(Request $request, string $type, ?Restaurant $previous = null): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:15', 'regex:/\A[^\x00]{1,72}\z/s', 'confirmed'],
            'access_days' => $type === 'trial' ? ['required', 'integer', 'min:1', 'max:90'] : ['exclude'],
            'modules' => $type === 'trial' ? ['sometimes', 'array', 'list'] : ['exclude'],
            'modules.*' => ['distinct', Rule::in(array_keys(ModuleCatalog::MODULES))],
        ]);

        $hash = Hash::make($input['password']);
        $modules = $input['modules'] ?? [];
        $actor = $request->user('central');

        try {
            $tenant = DB::transaction(function () use ($input, $hash, $type, $previous, $modules, $actor) {
                if ($previous) {
                    $previous = Restaurant::query()->lockForUpdate()->findOrFail($previous->id);
                    abort_unless($previous->tenant_type === 'trial' && $previous->retired_at === null, 409);
                    $this->retire($previous);
                }

                $restaurant = $this->provision($input, $hash, $type, $modules, $actor)->restaurant;
                if ($previous) {
                    CommercialAudit::record($type === 'production' ? 'trial.converted' : 'trial.reset', $actor, $previous, details: [
                        'replacement_restaurant_id' => $restaurant->id,
                    ]);
                }

                return $restaurant;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Use a restaurant email that has not been registered.']);
        }

        return to_route('central.restaurants.show', $tenant);
    }

    private function provision(array $input, string $hash, string $type, array $modules, ?CentralAdmin $actor = null): Staff
    {
        $restaurant = new Restaurant(['name' => $input['name'], 'email' => Str::lower($input['email'])]);
        $restaurant->forceFill([
            'tenant_type' => $type,
            'access_expires_at' => match ($type) {
                'demo' => now()->addHour(),
                'trial' => now()->addDays((int) $input['access_days']),
                default => null,
            },
        ])->save();
        if ($actor) {
            CommercialAudit::record('restaurant.approved', $actor, $restaurant, details: ['source' => $type]);
        }
        $staff = $restaurant->staff()->make([
            'name' => $input['owner_name'], 'email' => Str::lower($input['owner_email']), 'password' => $hash,
        ]);
        $staff->forceFill(['role' => $type === 'demo' ? 'cashier' : 'owner'])->save();

        if ($type !== 'production') {
            $branch = $restaurant->branches()->create(['name' => 'Sample Branch']);
            $staff->branches()->attach($branch, ['restaurant_id' => $restaurant->id]);
            if ($actor) {
                CommercialAudit::record('branch.approved', $actor, $restaurant, $branch, ['source' => 'trial']);
            }
            if (in_array('pos', $modules, true)) {
                Menu::sample($restaurant->id, $branch->id);
            }
            foreach (DB::table('erp_modules')->whereIn('code', $modules)->get() as $module) {
                $keys = ['restaurant_id' => $restaurant->id, 'erp_module_id' => $module->id, 'is_enabled' => true];
                DB::table('restaurant_entitlements')->insert($keys);
                DB::table('branch_entitlements')->insert($keys + ['branch_id' => $branch->id]);
                CommercialAudit::record('license.restaurant', $actor, $restaurant, details: ['module' => $module->code, 'enabled' => true]);
                CommercialAudit::record('license.branch', $actor, $restaurant, $branch, ['module' => $module->code, 'enabled' => true]);
            }
        }

        return $staff->refresh();
    }

    private function retire(Restaurant $restaurant): void
    {
        $restaurant->forceFill(['retired_at' => now(), 'is_active' => false])->save();
    }
}
