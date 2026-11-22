<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\CommercialAudit;
use App\Domain\Commercial\ModuleCatalog;
use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CentralWorkspaceController
{
    public function index(Request $request): View
    {
        $pending = $request->query('review') === 'pending';

        return view('central.home', [
            'pending' => $pending,
            'restaurants' => Restaurant::query()
                ->when($pending, fn ($query) => $query->where(fn ($matches) => $matches
                    ->where('approval_status', 'pending')
                    ->orWhereHas('branches', fn ($branches) => $branches->where('approval_status', 'pending'))))
                ->orderBy('name')->orderBy('id')->simplePaginate(25)->withQueryString(),
        ]);
    }

    public function show(Restaurant $restaurant): View
    {
        return view('central.restaurant', [
            'restaurant' => $restaurant,
            'branches' => $restaurant->branches()->orderBy('name')->get(),
            'modules' => DB::table('erp_modules')->orderBy('name')->get(),
            'restaurantGrants' => DB::table('restaurant_entitlements')->where('restaurant_id', $restaurant->id)->pluck('is_enabled', 'erp_module_id'),
            'branchGrants' => DB::table('branch_entitlements')->where('restaurant_id', $restaurant->id)->get()->groupBy('branch_id'),
            'audit' => DB::table('commercial_audit_events')->where('restaurant_id', $restaurant->id)->latest('id')->limit(20)->get(),
            'roles' => ModuleCatalog::ROLES,
            'staff' => $restaurant->staff()->with('branches')->orderBy('name')->get(),
        ]);
    }

    public function createRestaurant(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
        ]);
        $input['email'] = Str::lower($input['email']);

        try {
            $restaurant = DB::transaction(function () use ($request, $input) {
                $created = Restaurant::create($input);
                CommercialAudit::record('restaurant.approved', $request->user('central'), $created, details: ['source' => 'central']);

                return $created;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This restaurant email is already registered.']);
        }

        return to_route('central.restaurants.show', $restaurant);
    }

    public function createBranch(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            DB::transaction(function () use ($request, $restaurant, $input) {
                $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
                abort_unless($tenant->approval_status === 'approved' && ! $tenant->retired_at && $tenant->tenant_type !== 'demo', 409);
                $branch = $tenant->branches()->create($input);
                CommercialAudit::record('branch.approved', $request->user('central'), $tenant, $branch, ['source' => 'central']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This branch name is already registered for this restaurant.']);
        }

        return to_route('central.restaurants.show', $restaurant);
    }

    public function createStaff(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['sometimes', Rule::in(ModuleCatalog::ROLES)],
            'password' => ['required', 'string', 'min:15', 'regex:/\A[^\x00]{1,72}\z/s'],
        ]);
        $input['email'] = Str::lower($input['email']);
        $branchIds = $this->branchIds($request, $restaurant);
        $input['password'] = Hash::make($input['password']);

        try {
            DB::transaction(function () use ($restaurant, $input, $branchIds) {
                $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
                abort_unless($tenant->approval_status === 'approved' && ! $tenant->retired_at && $tenant->tenant_type !== 'demo', 409);
                $staff = $tenant->staff()->make(Arr::except($input, 'role'));
                $staff->forceFill(['role' => $input['role'] ?? 'cashier'])->save();
                $staff->branches()->syncWithPivotValues($branchIds, ['restaurant_id' => $tenant->id]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This staff email is already registered for this restaurant.']);
        }

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateAssignments(Request $request, Restaurant $restaurant, Staff $staff): RedirectResponse
    {
        $branchIds = $this->branchIds($request, $restaurant);

        DB::transaction(function () use ($request, $restaurant, $staff, $branchIds) {
            $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            abort_if($tenant->retired_at || $tenant->tenant_type === 'demo' || $tenant->approval_status !== 'approved', 409);
            $member = $tenant->staff()->lockForUpdate()->findOrFail($staff->id);
            $change = $member->branches()->syncWithPivotValues($branchIds, ['restaurant_id' => $tenant->id]);
            if ($change['attached'] || $change['detached']) {
                CommercialAudit::record('staff.assignments', $request->user('central'), $tenant, details: [
                    'staff_id' => $member->id, 'attached' => $change['attached'], 'detached' => $change['detached'],
                ]);
            }
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateRestaurantStatus(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $this->updateStatus($request, $restaurant, $restaurant);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateBranchStatus(Request $request, Restaurant $restaurant, Branch $branch): RedirectResponse
    {
        $this->updateStatus($request, $restaurant, $branch);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateStaffStatus(Request $request, Restaurant $restaurant, Staff $staff): RedirectResponse
    {
        $this->updateStatus($request, $restaurant, $staff);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateRole(Request $request, Restaurant $restaurant, Staff $staff): RedirectResponse
    {
        $input = $request->validate(['role' => ['required', Rule::in(ModuleCatalog::ROLES)]]);
        DB::transaction(function () use ($request, $restaurant, $staff, $input) {
            $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            abort_if($tenant->retired_at || $tenant->tenant_type === 'demo', 409);
            $member = $tenant->staff()->lockForUpdate()->findOrFail($staff->id);
            if ($member->role !== $input['role']) {
                $role = $member->role;
                $member->forceFill(['role' => $input['role']])->save();
                CommercialAudit::record('staff.role', $request->user('central'), $tenant, details: [
                    'staff_id' => $member->id, 'previous' => $role, 'role' => $member->role,
                ]);
            }
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }

    private function updateStatus(Request $request, Restaurant $restaurant, Restaurant|Branch|Staff $record): void
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $restaurant, $record) {
            $tenant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $target = match (true) {
                $record instanceof Branch => $tenant->branches()->lockForUpdate()->findOrFail($record->id),
                $record instanceof Staff => $tenant->staff()->lockForUpdate()->findOrFail($record->id),
                default => $tenant,
            };
            abort_if($tenant->retired_at || $tenant->approval_status !== 'approved', 409);
            abort_if($target instanceof Branch && $target->approval_status !== 'approved', 409);

            $active = $request->boolean('is_active');
            if ($target->is_active === $active) {
                return;
            }

            $target->is_active = $active;
            $target->save();
            $kind = match (true) {
                $target instanceof Branch => 'branch',
                $target instanceof Staff => 'staff',
                default => 'restaurant',
            };
            $details = ['active' => $active];
            if ($target instanceof Staff) {
                $details['staff_id'] = $target->id;
            }
            CommercialAudit::record($kind.'.status', $request->user('central'), $tenant, $target instanceof Branch ? $target : null, $details);
        }, 3);
    }

    private function branchIds(Request $request, Restaurant $restaurant): array
    {
        $input = $request->validate([
            'branch_ids' => ['sometimes', 'array', 'list'],
            'branch_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $branchIds = $input['branch_ids'] ?? [];

        if ($restaurant->branches()->whereKey($branchIds)->count() !== count($branchIds)) {
            throw ValidationException::withMessages(['branch_ids' => 'Select branches from this restaurant.']);
        }

        return $branchIds;
    }
}
