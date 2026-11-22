<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveAccount
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $account = Auth::guard($guard)->user();

        if ($account instanceof Staff) {
            $account->load('restaurant');
        }

        if (! $account?->is_active || ($account instanceof Staff && ! $account->restaurant?->is_active)) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403);
        }

        return $next($request);
    }
}
