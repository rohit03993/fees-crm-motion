<?php

namespace App\Providers;

use App\Models\Discount;
use App\Models\Payment;
use App\Models\Reschedule;
use App\Models\Student;
use App\Policies\DiscountPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReschedulePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\StudentPolicy;
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
        Student::class => StudentPolicy::class,
        Payment::class => PaymentPolicy::class,
        Reschedule::class => ReschedulePolicy::class,
        Discount::class => DiscountPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();

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

        // Master data permissions
        Gate::define('manage-courses', [MasterDataPolicy::class, 'manageCourses']);
        Gate::define('manage-branches', [MasterDataPolicy::class, 'manageBranches']);
        Gate::define('manage-banks', [MasterDataPolicy::class, 'manageBanks']);
        Gate::define('manage-misc-charges', [MasterDataPolicy::class, 'manageMiscCharges']);

        // Settings permissions
        Gate::define('view-settings', [SettingsPolicy::class, 'viewAny']);
        Gate::define('update-settings', [SettingsPolicy::class, 'update']);
        Gate::define('manage-penalty-settings', [SettingsPolicy::class, 'managePenalties']);
        Gate::define('clear-students', [SettingsPolicy::class, 'clearStudents']);

        // Payment permissions
        Gate::define('create-payment', function ($user) {
            return $user->isStaff() || $user->isAdmin();
        });
    }
}

