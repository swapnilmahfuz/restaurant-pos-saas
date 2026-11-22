<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController
{
    public function staff(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'restaurant_email' => ['required', 'email', 'max:254'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/s'],
        ]);

        $restaurantEmail = Str::lower($input['restaurant_email']);

        return $this->authenticate($request, 'staff', [
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'is_active' => true,
            fn (Builder $staff) => $staff->whereHas('restaurant', fn (Builder $restaurant) => $restaurant
                ->where('email', $restaurantEmail)
                ->where('is_active', true)),
        ]);
    }

    public function central(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/s'],
        ]);

        return $this->authenticate($request, 'central', [
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'is_active' => true,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('staff.login');
    }

    private function authenticate(Request $request, string $guard, array $credentials): RedirectResponse
    {
        if (! Auth::guard($guard)->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }

        Auth::guard($guard === 'staff' ? 'central' : 'staff')->logout();
        $request->session()->regenerate();

        return to_route($guard.'.home');
    }
}
