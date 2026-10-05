<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Observers\LeaveBalanceObserver;
use App\Observers\LeaveRequestObserver;
use App\Observers\OrganizationObserver;
use App\Services\LeaveBalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LeaveBalanceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ---------------------------------------------------------------
        // Strict mode in non-production environments:
        // prevents lazy loading, silently discarded attributes, etc.
        // ---------------------------------------------------------------
        Model::shouldBeStrict(! app()->isProduction());

        // ---------------------------------------------------------------
        // Model observers
        // ---------------------------------------------------------------
        Organization::observe(OrganizationObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        LeaveBalance::observe(LeaveBalanceObserver::class);
    }
}
