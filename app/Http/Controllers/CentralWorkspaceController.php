<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CentralWorkspaceController
{
    public function index(): View
    {
        return view('central.home', [
            'restaurants' => Restaurant::query()->orderBy('name')->orderBy('id')->simplePaginate(25),
        ]);
    }

    public function show(Restaurant $restaurant): View
    {
        return view('central.restaurant', [
            'restaurant' => $restaurant,
            'branches' => $restaurant->branches()->orderBy('name')->get(),
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
            $restaurant = Restaurant::create($input);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This restaurant email is already registered.']);
        }

        return to_route('central.restaurants.show', $restaurant);
    }

    public function createBranch(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            $restaurant->branches()->create($input);
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
            'password' => ['required', 'string', 'min:15', 'regex:/\A[^\x00]{1,72}\z/s'],
        ]);
        $input['email'] = Str::lower($input['email']);
        $branchIds = $this->branchIds($request, $restaurant);
        $input['password'] = Hash::make($input['password']);

        try {
            DB::transaction(function () use ($restaurant, $input, $branchIds) {
                $restaurant->staff()->create($input)->branches()->syncWithPivotValues($branchIds, ['restaurant_id' => $restaurant->id]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This staff email is already registered for this restaurant.']);
        }

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateAssignments(Request $request, Restaurant $restaurant, Staff $staff): RedirectResponse
    {
        $branchIds = $this->branchIds($request, $restaurant);

        DB::transaction(function () use ($restaurant, $staff, $branchIds) {
            $restaurant->staff()->lockForUpdate()->findOrFail($staff->id)->branches()->syncWithPivotValues($branchIds, ['restaurant_id' => $restaurant->id]);
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateRestaurantStatus(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $this->updateStatus($request, $restaurant);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateBranchStatus(Request $request, Restaurant $restaurant, Branch $branch): RedirectResponse
    {
        $this->updateStatus($request, $branch);

        return to_route('central.restaurants.show', $restaurant);
    }

    public function updateStaffStatus(Request $request, Restaurant $restaurant, Staff $staff): RedirectResponse
    {
        $this->updateStatus($request, $staff);

        return to_route('central.restaurants.show', $restaurant);
    }

    private function updateStatus(Request $request, Restaurant|Branch|Staff $record): void
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        $record->is_active = $request->boolean('is_active');
        $record->save();
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
