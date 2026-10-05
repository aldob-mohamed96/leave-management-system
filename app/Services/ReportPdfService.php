<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfService
{
    /**
     * Generate a PDF summary report binary string for the given filters.
     *
     * @param  array{
     *     status?: string,
     *     organization_id?: int,
     *     leave_type_id?: int,
     *     date_range?: array{from?: string, to?: string}
     * } $filters
     * @return string  PDF binary string
     */
    public function generateSummary(array $filters): string
    {
        $requests = LeaveRequest::withoutGlobalScopes()
            ->with(['employee.organization', 'leaveType'])
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['organization_id'] ?? null,
                fn($q, $v) => $q->where('organization_id', $v))
            ->when($filters['leave_type_id'] ?? null,
                fn($q, $v) => $q->where('leave_type_id', $v))
            ->when($filters['date_range']['from'] ?? null,
                fn($q, $v) => $q->whereDate('start_date', '>=', $v))
            ->when($filters['date_range']['to'] ?? null,
                fn($q, $v) => $q->whereDate('start_date', '<=', $v))
            ->get();

        $total = $requests->count();

        // Status breakdown: count and percentage per status
        $statusBreakdown = $requests
            ->groupBy(fn($r) => $r->status->value)
            ->map(fn($group, $statusValue) => [
                'status'     => $statusValue,
                'label'      => $group->first()->status->label(),
                'count'      => $group->count(),
                'percentage' => $total > 0 ? round($group->count() / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->toArray();

        // Top 10 employees by total days taken
        $topEmployees = $requests
            ->groupBy('employee_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'name'       => $first->employee?->full_name ?? '—',
                    'school'     => $first->employee?->organization?->name ?? '—',
                    'total_days' => $group->sum(fn($r) => (float) $r->days),
                ];
            })
            ->sortByDesc('total_days')
            ->values()
            ->take(10)
            ->toArray();

        return Pdf::loadView('pdf.report-summary', compact('filters', 'statusBreakdown', 'topEmployees'))
            ->output();
    }
}
