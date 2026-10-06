<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * Count of leave requests per status for a school's subtree.
     * All 7 statuses are always present (default 0 if missing).
     */
    public function schoolStatusCounts(Organization $org): array
    {
        $counts = LeaveRequest::withoutGlobalScopes()
            ->whereIn('organization_id', $org->subtreeIds())
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Build zero-defaults for all 7 statuses
        $defaults = array_fill_keys(
            array_map(fn(LeaveStatus $s) => $s->value, LeaveStatus::cases()),
            0
        );

        return array_merge($defaults, $counts);
    }

    /**
     * Leave requests currently active today (approved, overlapping today).
     */
    public function onLeaveToday(Organization $org): Collection
    {
        return LeaveRequest::withoutGlobalScopes()
            ->with(['employee', 'leaveType'])
            ->whereIn('organization_id', $org->subtreeIds())
            ->where('status', LeaveStatus::APPROVED->value)
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->limit(20)
            ->get();
    }

    /**
     * All pending (submitted or in_review) requests ordered by submission date.
     */
    public function pendingRequests(Organization $org): Collection
    {
        return LeaveRequest::withoutGlobalScopes()
            ->with(['employee', 'leaveType'])
            ->whereIn('organization_id', $org->subtreeIds())
            ->whereIn('status', [LeaveStatus::SUBMITTED->value, LeaveStatus::IN_REVIEW->value])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Average entitled/used balance per leave type for an org's subtree.
     */
    public function employeeBalanceSummary(Organization $org): array
    {
        return LeaveBalance::withoutGlobalScopes()
            ->join('employees', 'employees.id', '=', 'leave_balances.employee_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_balances.leave_type_id')
            ->whereIn('employees.organization_id', $org->subtreeIds())
            ->where('leave_balances.year', now()->year)
            ->selectRaw('leave_types.name as type_name, AVG(leave_balances.entitled) as avg_entitled, AVG(leave_balances.used) as avg_used')
            ->groupBy('leave_types.id', 'leave_types.name')
            ->get()
            ->toArray();
    }

    /**
     * Per-school stats for an administration: request counts, approval/rejection rates.
     */
    public function administrationSchoolStats(Organization $adm): Collection
    {
        $schools = Organization::withoutGlobalScopes()
            ->where('parent_id', $adm->id)
            ->get();

        return $schools->map(function (Organization $school) {
            $requests = LeaveRequest::withoutGlobalScopes()
                ->whereIn('organization_id', $school->subtreeIds())
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            $total    = array_sum($requests);
            $pending  = ($requests[LeaveStatus::SUBMITTED->value] ?? 0)
                      + ($requests[LeaveStatus::IN_REVIEW->value] ?? 0);
            $approved = $requests[LeaveStatus::APPROVED->value] ?? 0;
            $rejected = $requests[LeaveStatus::REJECTED->value] ?? 0;

            $rejectionRate = $total > 0
                ? round($rejected / $total * 100, 1)
                : 0.0;

            return [
                'name'            => $school->name,
                'total_requests'  => $total,
                'pending'         => $pending,
                'approved'        => $approved,
                'rejected'        => $rejected,
                'rejection_rate'  => $rejectionRate,
            ];
        });
    }

    /**
     * Top leave takers by total approved days within an org subtree.
     */
    public function topLeaveTakers(Organization $adm, int $limit = 10): Collection
    {
        return Employee::withoutGlobalScopes()
            ->join('leave_requests', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('organizations', 'organizations.id', '=', 'employees.organization_id')
            ->whereIn('employees.organization_id', $adm->subtreeIds())
            ->where('leave_requests.status', LeaveStatus::APPROVED->value)
            ->selectRaw('employees.id, employees.full_name, organizations.name as school_name, SUM(leave_requests.days) as total_days, COUNT(leave_requests.id) as request_count')
            ->groupBy('employees.id', 'employees.full_name', 'organizations.name')
            ->orderByDesc('total_days')
            ->limit($limit)
            ->get();
    }

    /**
     * Average response time in hours (from submitted_at to decided_at).
     */
    public function avgResponseTime(Organization $adm): float
    {
        $avg = LeaveRequest::withoutGlobalScopes()
            ->whereIn('organization_id', $adm->subtreeIds())
            ->whereIn('status', [LeaveStatus::APPROVED->value, LeaveStatus::REJECTED->value])
            ->whereNotNull('submitted_at')
            ->whereNotNull('decided_at')
            ->selectRaw($this->averageResponseHoursSelect().' as avg_hours')
            ->value('avg_hours') ?? 0.0;

        return round((float) $avg, 1);
    }

    /**
     * Breakdown of rejected requests by rejection reason.
     */
    public function rejectionRateByReason(Organization $adm): Collection
    {
        return LeaveRequest::withoutGlobalScopes()
            ->whereIn('organization_id', $adm->subtreeIds())
            ->where('status', LeaveStatus::REJECTED->value)
            ->whereNotNull('rejection_reason')
            ->selectRaw('rejection_reason, COUNT(*) as count')
            ->groupBy('rejection_reason')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Per-administration stats for a directorate.
     */
    public function directorateAdminStats(Organization $dir): Collection
    {
        $admins = Organization::withoutGlobalScopes()
            ->where('parent_id', $dir->id)
            ->get();

        return $admins->map(function (Organization $adm) {
            $subtreeIds = $adm->subtreeIds();

            $counts = LeaveRequest::withoutGlobalScopes()
                ->whereIn('organization_id', $subtreeIds)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            $totalRequests  = array_sum($counts);
            $approvedCount  = $counts[LeaveStatus::APPROVED->value] ?? 0;
            $pendingCount   = ($counts[LeaveStatus::SUBMITTED->value] ?? 0)
                            + ($counts[LeaveStatus::IN_REVIEW->value] ?? 0);

            $avgHours = LeaveRequest::withoutGlobalScopes()
                ->whereIn('organization_id', $subtreeIds)
                ->whereIn('status', [LeaveStatus::APPROVED->value, LeaveStatus::REJECTED->value])
                ->whereNotNull('submitted_at')
                ->whereNotNull('decided_at')
                ->selectRaw($this->averageResponseHoursSelect().' as avg_hours')
                ->value('avg_hours') ?? 0.0;

            return [
                'name'               => $adm->name,
                'total_requests'     => $totalRequests,
                'approved_count'     => $approvedCount,
                'pending_count'      => $pendingCount,
                'avg_response_hours' => round((float) $avgHours, 1),
            ];
        });
    }

    /**
     * Monthly request count trend for a given year.
     * Returns array of 12 elements (index 0 = January).
     */
    public function monthlyRequestTrend(Organization $dir, int $year): array
    {
        $raw = LeaveRequest::withoutGlobalScopes()
            ->whereIn('organization_id', $dir->subtreeIds())
            ->whereYear('created_at', $year)
            ->selectRaw($this->monthBucketSelect().', COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $key      = str_pad($m, 2, '0', STR_PAD_LEFT);
            $result[] = (int) ($raw[$key] ?? $raw[(string) $m] ?? 0);
        }

        return $result;
    }

    /**
     * Total requests per year for the last N years.
     */
    public function yearlyComparison(Organization $dir, int $years = 3): array
    {
        $result      = [];
        $currentYear = (int) now()->year;

        for ($y = $currentYear - $years + 1; $y <= $currentYear; $y++) {
            $result[(string) $y] = LeaveRequest::withoutGlobalScopes()
                ->whereIn('organization_id', $dir->subtreeIds())
                ->whereYear('created_at', $y)
                ->count();
        }

        return $result;
    }

    /**
     * Count of pending/in_review requests that have been waiting too long.
     * Threshold: config('leave.overdue_days', 3).
     */
    public function overdueCount(?Organization $org = null): int
    {
        $query = LeaveRequest::withoutGlobalScopes()
            ->whereIn('status', [LeaveStatus::SUBMITTED->value, LeaveStatus::IN_REVIEW->value])
            ->where('submitted_at', '<', now()->subDays(config('leave.overdue_days', 3)));

        if ($org !== null) {
            $query->whereIn('organization_id', $org->subtreeIds());
        }

        return $query->count();
    }

    /**
     * Driver-aware AVG hours expression (MySQL / MariaDB / SQLite / Postgres).
     */
    private function averageResponseHoursSelect(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(SECOND, submitted_at, decided_at) / 3600.0)',
            'pgsql'            => 'AVG(EXTRACT(EPOCH FROM (decided_at - submitted_at)) / 3600.0)',
            default            => 'AVG((julianday(decided_at) - julianday(submitted_at)) * 24)',
        };
    }

    /**
     * Driver-aware month bucket: zero-padded month string as `month`.
     */
    private function monthBucketSelect(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%m') as month",
            'pgsql'            => "to_char(created_at, 'MM') as month",
            default            => "strftime('%m', created_at) as month",
        };
    }
}
