<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('centrix-login', function (Request $request) {
            return Limit::perMinute(15)->by('login:'.$request->ip());
        });
        RateLimiter::for('centrix-password', function (Request $request) {
            return Limit::perMinute(5)->by('password:'.$request->ip());
        });
    }
}
