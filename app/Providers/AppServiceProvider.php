<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(5)->by('registration:ip:'.$request->ip()),
            Limit::perHour(100)->by('registration:global'),
        ]);
        RateLimiter::for('public-demo', fn (Request $request) => [
            Limit::perHour(10)->by('demo:ip:'.$request->ip()),
            Limit::perHour(150)->by('demo:global'),
        ]);

        foreach (['staff', 'central'] as $guard) {
            RateLimiter::for($guard.'-login', function (Request $request) use ($guard): array {
                $identity = [$request->input('email'), $guard === 'staff' ? $request->input('restaurant_email') : null];
                $identity = array_map(
                    fn ($value) => is_string($value) ? Str::lower(trim(substr($value, 0, 254))) : '',
                    $identity,
                );
                $ip = $request->ip();

                return [
                    Limit::perMinute(30)->by($guard.':ip:'.$ip),
                    Limit::perMinute(5)->by($guard.':identity:'.hash('sha256', serialize([$ip, ...$identity]))),
                ];
            });
        }
    }
}
