<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Support\ArabicPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class ReportPdfService
{
    public function __construct(
        private readonly DashboardStatsService $statsService,
    ) {}

    /**
     * Generate a summary report PDF binary.
     *
     * @param  array{
     *   from?: string,
     *   to?: string,
     *   organization_id?: int,
     *   leave_type_id?: int,
     *   status?: string,
     * } $filters
     */
    public function generateSummary(array $filters = []): string
    {
        $data = $this->buildReportData($filters);

        $html = view('pdf.report-summary', $data)->render();
        $html = ArabicPdf::shapeHtml($html);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->output();
    }

    private function buildReportData(array $filters): array
    {
        $query = LeaveRequest::withoutGlobalScopes()
            ->with(['employee.organization', 'leaveType']);

        if (! empty($filters['from'])) {
            $query->where('start_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('end_date', '<=', $filters['to']);
        }
        if (! empty($filters['organization_id'])) {
            $org = Organization::withoutGlobalScopes()->find($filters['organization_id']);
            if ($org) {
                $query->whereIn('organization_id', $org->subtreeIds());
            }
        }
        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $requests = $query->get();

        // Status breakdown
        $statusBreakdown = collect(LeaveStatus::cases())->map(fn($s) => [
            'label' => $s->label(),
            'count' => $requests->where('status', $s)->count(),
        ])->filter(fn($r) => $r['count'] > 0)->values();

        // Top 10 employees by days taken
        $topTakers = $requests
            ->where('status', LeaveStatus::APPROVED)
            ->groupBy('employee_id')
            ->map(fn($group) => [
                'name'       => $group->first()->employee?->full_name ?? '—',
                'school'     => $group->first()->employee?->organization?->name ?? '—',
                'total_days' => $group->sum('days'),
                'count'      => $group->count(),
            ])
            ->sortByDesc('total_days')
            ->take(10)
            ->values();

        $org = ! empty($filters['organization_id'])
            ? Organization::withoutGlobalScopes()->find($filters['organization_id'])
            : null;

        return [
            'filters'         => $filters,
            'orgName'         => $org?->name ?? 'جميع المؤسسات',
            'generatedAt'     => now()->format('Y/m/d H:i'),
            'totalRequests'   => $requests->count(),
            'statusBreakdown' => $statusBreakdown,
            'topTakers'       => $topTakers,
            'leaveTypeName'   => ! empty($filters['leave_type_id'])
                ? LeaveType::find($filters['leave_type_id'])?->name
                : null,
        ];
    }
}
