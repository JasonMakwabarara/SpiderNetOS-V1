<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
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
        // Platform super-admin gate (Phase 8 folds this into a spatie role).
        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());

        // Per-user + per-tenant API rate limits.
        RateLimiter::for('api', function ($request) {
            $user = $request->user();

            if ($user) {
                return [
                    Limit::perMinute(120)->by('tenant:'.$user->tenant_id),
                    Limit::perMinute(60)->by('user:'.$user->id),
                ];
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}