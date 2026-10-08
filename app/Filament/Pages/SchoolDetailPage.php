<?php

namespace App\Filament\Pages;

use App\Enums\LeaveStatus;
use App\Enums\OrganizationType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Organization;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class SchoolDetailPage extends Page
{
    protected static bool   $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.school-detail-page';
    protected static ?string $slug = 'school-database/{schoolId}';

    public int    $schoolId     = 0;
    public string $activeTab    = 'info';
    public string $statusFilter = '';

    // -------------------------------------------------------------------------
    // Route — custom slug with {schoolId} parameter
    // -------------------------------------------------------------------------

    public static function getSlug(): string
    {
        return 'school-database/{schoolId}';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return 'filament.admin.pages.school-database.detail';
    }

    public static function getRoutes(): \Closure
    {
        return function () {
            Route::get('/school-database/{schoolId}', static::class)
                ->name('filament.admin.pages.school-database.detail');
        };
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $user?->setOrganizationTeam();
        $type = $user?->organization?->type;

        return $type === OrganizationType::ADMINISTRATION
            || $type === OrganizationType::DIRECTORATE;
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->schoolId = (int) request()->route('schoolId');

        abort_if(! $this->schoolId, 404);
    }

    // -------------------------------------------------------------------------
    // Page heading
    // -------------------------------------------------------------------------

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return Organization::withoutGlobalScopes()->find($this->schoolId)?->name
            ?? 'بيانات المدرسة';
    }

    // -------------------------------------------------------------------------
    // Tab switching action
    // -------------------------------------------------------------------------

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // -------------------------------------------------------------------------
    // View data
    // -------------------------------------------------------------------------

    protected function getViewData(): array
    {
        $school = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::SCHOOL->value)
            ->findOrFail($this->schoolId);

        $parentOrg = $school->parent_id
            ? Organization::withoutGlobalScopes()->find($school->parent_id)
            : null;

        $employeeCount       = Employee::withoutGlobalScopes()->where('organization_id', $this->schoolId)->count();
        $activeEmployeeCount = Employee::withoutGlobalScopes()->where('organization_id', $this->schoolId)->where('is_active', true)->count();

        $requestBase   = LeaveRequest::withoutGlobalScopes()->where('organization_id', $this->schoolId);
        $totalRequests = (clone $requestBase)->count();
        $approvedCount = (clone $requestBase)->where('status', LeaveStatus::APPROVED->value)->count();
        $pendingCount  = (clone $requestBase)->whereIn('status', [LeaveStatus::SUBMITTED->value, LeaveStatus::IN_REVIEW->value])->count();
        $rejectedCount = (clone $requestBase)->where('status', LeaveStatus::REJECTED->value)->count();

        $employees = Employee::withoutGlobalScopes()
            ->with('entitlementGrade')
            ->where('organization_id', $this->schoolId)
            ->orderBy('full_name')
            ->get();

        $leaveRequests = LeaveRequest::withoutGlobalScopes()
            ->with(['employee', 'leaveType'])
            ->where('organization_id', $this->schoolId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();

        return compact(
            'school', 'parentOrg',
            'employeeCount', 'activeEmployeeCount',
            'totalRequests', 'approvedCount', 'pendingCount', 'rejectedCount',
            'employees', 'leaveRequests'
        );
    }
}
