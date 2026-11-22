<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\CommercialAudit;
use App\Models\Branch;
use App\Models\Restaurant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingController
{
    public function register(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:15', 'regex:/\A[^\x00]{1,72}\z/s', 'confirmed'],
        ]);

        $hash = Hash::make($input['password']);

        try {
            DB::transaction(function () use ($input, $hash) {
                $restaurant = new Restaurant(['name' => $input['name'], 'email' => Str::lower($input['email'])]);
                $restaurant->forceFill(['approval_status' => 'pending', 'is_active' => false])->save();
                $owner = $restaurant->staff()->make([
                    'name' => $input['owner_name'], 'email' => Str::lower($input['owner_email']), 'password' => $hash,
                ]);
                $owner->forceFill(['role' => 'owner'])->save();
                CommercialAudit::record('restaurant.requested', null, $restaurant);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Keep the response identical for registered and unregistered restaurant emails.
        }

        return to_route('registration.received');
    }

    public function requestBranch(Request $request): RedirectResponse
    {
        $staff = $request->user('staff');
        abort_unless($staff->role === 'owner' && $staff->restaurant->tenant_type !== 'demo', 403);
        $input = $request->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            DB::transaction(function () use ($staff, $input) {
                $restaurant = $staff->restaurant()->operational()->lockForUpdate()->firstOrFail();
                $branch = $restaurant->branches()->make($input);
                $branch->forceFill(['approval_status' => 'pending', 'is_active' => false])->save();
                $staff->branches()->attach($branch, ['restaurant_id' => $restaurant->id]);
                CommercialAudit::record('branch.requested', $staff, $restaurant, $branch);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This branch name is already registered for this restaurant.']);
        }

        return to_route('staff.home');
    }

    public function decideRestaurant(Request $request, Restaurant $restaurant): RedirectResponse
    {
        return $this->decide($request, $restaurant);
    }

    public function decideBranch(Request $request, Restaurant $restaurant, Branch $branch): RedirectResponse
    {
        return $this->decide($request, $restaurant, $branch);
    }

    private function decide(Request $request, Restaurant $restaurant, ?Branch $branch = null): RedirectResponse
    {
        $input = $request->validate(['decision' => ['required', 'in:approved,rejected']]);
        $decision = $input['decision'];

        DB::transaction(function () use ($request, $restaurant, $branch, $decision) {
            $restaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $target = $branch ? $restaurant->branches()->lockForUpdate()->findOrFail($branch->id) : $restaurant;
            abort_unless($target->approval_status === 'pending' && $restaurant->retired_at === null, 409);
            abort_if($branch && $decision === 'approved' && $restaurant->approval_status !== 'approved', 409);
            $target->forceFill(['approval_status' => $decision, 'is_active' => $decision === 'approved'])->save();
            CommercialAudit::record(($branch ? 'branch.' : 'restaurant.').$decision, $request->user('central'), $restaurant, $branch);
        }, 3);

        return to_route('central.restaurants.show', $restaurant);
    }
}
