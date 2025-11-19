<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Admin Gates - Admin can access everything
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });

        // Staff Gates - Staff can access staff-level functions
        Gate::define('staff', function ($user) {
            return $user->isStaff() || $user->isAdmin();
        });

        // Specific permission gates
        Gate::define('manage-settings', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('approve-reschedules', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('approve-discounts', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('manage-master-data', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('view-reports', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('export-data', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('manage-users', function ($user) {
            return $user->isAdmin();
        });
    }
}

