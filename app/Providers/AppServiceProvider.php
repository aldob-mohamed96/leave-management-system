<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\User;
use App\Observers\LeaveBalanceObserver;
use App\Observers\LeaveRequestObserver;
use App\Observers\OrganizationObserver;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use App\Services\LeaveBalanceService;
use App\Services\LeaveRequestService;
use App\Services\ReportPdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * ربط النماذج بسياسات التفويض الخاصة بها.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Organization::class => OrganizationPolicy::class,
        User::class         => UserPolicy::class,
        Employee::class     => EmployeePolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LeaveBalanceService::class);
        $this->app->singleton(LeaveRequestService::class);
        $this->app->singleton(\App\Services\DashboardStatsService::class);
        $this->app->singleton(ReportPdfService::class);
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

        // ---------------------------------------------------------------
        // Policy registration
        // ---------------------------------------------------------------
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
