<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;
use App\Filament\Auth\CustomLogoutResponse;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, CustomLogoutResponse::class);
    }


    public function boot(): void
    {
        URL::forceScheme('https');

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(5000)->by($request->user()?->id ?: $request->ip());
        });
    }
}
