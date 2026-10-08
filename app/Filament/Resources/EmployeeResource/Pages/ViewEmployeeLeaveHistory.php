<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Enums\LeaveStatus;
use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Collection;

/**
 * صفحة تاريخ إجازات الموظف — تعرض الأرصدة والفترات الوظيفية المستنتجة
 * وإجماليات الخدمة والطلبات المعتمدة.
 */
class ViewEmployeeLeaveHistory extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EmployeeResource::class;

    protected static string $view = 'filament.pages.employee-leave-history';

    // -------------------------------------------------------------------------
    // Filter inputs (Livewire properties for wire:model)
    // -------------------------------------------------------------------------

    /** @var int|null */
    public $fromYear = null;

    /** @var int|null */
    public $toYear = null;

    // -------------------------------------------------------------------------
    // Data properties exposed to the Blade template
    // -------------------------------------------------------------------------

    public Employee $employee;

    /** @var array<string, mixed> */
    public array $employeeInfo = [];

    public Collection $balanceRows;

    public Collection $gradePeriods;

    /** @var array<string, mixed> */
    public array $summaryStats = [];

    public Collection $approvedRequests;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(int|string $record): void
    {
        // Resolve the employee bypassing the OrganizationScope global scope
        $this->employee = Employee::withoutGlobalScopes()
            ->with('entitlementGrade')
            ->findOrFail($record);

        // Seed filter inputs from URL query params
        $fromParam = request()->query('from_year');
        $toParam   = request()->query('to_year');

        $this->fromYear = $fromParam !== null ? (int) $fromParam : null;
        $this->toYear   = $toParam !== null   ? (int) $toParam   : null;

        $this->loadData();
    }

    /**
     * Livewire action: re-run queries with the current fromYear / toYear values.
     */
    public function applyFilter(): void
    {
        $this->loadData();
    }

    // -------------------------------------------------------------------------
    // Page title
    // -------------------------------------------------------------------------

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'تاريخ إجازات: ' . $this->employee->full_name;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadData(): void
    {
        $employee = $this->employee;

        // ------- Employee info -------
        $startDate   = $employee->work_start_date ?? $employee->hire_date;
        $serviceYears = $startDate ? (int) Carbon::parse($startDate)->diffInYears(now()) : 0;

        $this->employeeInfo = [
            'full_name'        => $employee->full_name,
            'employee_code'    => $employee->employee_code ?? '—',
            'job_title'        => $employee->job_title ?? '—',
            'grade_name'       => $employee->entitlementGrade?->name ?? ($employee->grade ?? '—'),
            'yearly_days'      => $employee->entitlementGrade?->yearly_days ?? '—',
            'hire_date'        => $employee->hire_date?->translatedFormat('d F Y') ?? '—',
            'work_start_date'  => $employee->work_start_date?->translatedFormat('d F Y') ?? '—',
            'years_of_service' => $serviceYears,
        ];

        // ------- Balance rows -------
        $balanceQuery = $employee->leaveBalances()
            ->with('leaveType')
            ->orderByDesc('year');

        if ($this->fromYear !== null) {
            $balanceQuery->where('year', '>=', (int) $this->fromYear);
        }
        if ($this->toYear !== null) {
            $balanceQuery->where('year', '<=', (int) $this->toYear);
        }

        $rawBalances = $balanceQuery->get();

        $this->balanceRows = $rawBalances
            ->sortByDesc('year')
            ->map(fn ($b) => [
                'year'            => $b->year,
                'leave_type_name' => $b->leaveType?->name ?? '—',
                'entitled'        => (int) $b->entitled,
                'carried_over'    => (int) $b->carried_over,
                'used'            => (int) $b->used,
                'remaining'       => max(0, (int) $b->entitled + (int) $b->carried_over - (int) $b->used),
            ])
            ->values();

        // ------- Grade periods (inferred from اعتيادية balances) -------
        // Use all balance rows (unfiltered by year) to infer full service periods
        $allBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->get();

        $regularBalances = $allBalances->filter(
            fn ($b) => str_contains($b->leaveType?->name ?? '', 'اعتيادية')
        );

        $sourceForPeriods = $regularBalances->isNotEmpty() ? $regularBalances : $allBalances;

        // Group consecutive years with the same entitled value
        $this->gradePeriods = $sourceForPeriods
            ->groupBy('entitled')
            ->map(function (Collection $group, int $entitled) {
                $years = $group->pluck('year');
                return [
                    'entitled_days' => $entitled,
                    'from_year'     => (int) $years->min(),
                    'to_year'       => (int) $years->max(),
                    'years_count'   => $years->unique()->count(),
                ];
            })
            ->sortBy('from_year')
            ->values();

        // ------- Summary stats -------
        $totalEntitled  = $this->balanceRows->sum('entitled');
        $totalUsed      = $this->balanceRows->sum('used');
        $totalRemaining = $this->balanceRows->sum('remaining');

        $approvedCount = LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::APPROVED->value)
            ->count();

        $this->summaryStats = [
            'years_of_service'        => $serviceYears,
            'total_entitled'          => (int) $totalEntitled,
            'total_used'              => (int) $totalUsed,
            'total_remaining'         => (int) $totalRemaining,
            'approved_requests_count' => $approvedCount,
        ];

        // ------- Approved leave requests -------
        $this->approvedRequests = LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::APPROVED->value)
            ->with('leaveType')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($req) => [
                'number'          => $req->number,
                'leave_type_name' => $req->leaveType?->name ?? '—',
                'start_date'      => $req->start_date?->format('d/m/Y') ?? '—',
                'end_date'        => $req->end_date?->format('d/m/Y') ?? '—',
                'days'            => (int) $req->days,
                'year'            => $req->start_date?->year ?? '—',
            ])
            ->values();
    }
}
