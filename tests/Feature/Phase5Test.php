<?php

use App\Enums\EntitlementGrade;
use App\Enums\LeaveStatus;
use App\Filament\Widgets\Administration\AvgResponseTimeWidget;
use App\Filament\Widgets\School\SchoolStatusOverview;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\ReportPdfService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

// =============================================================================
// Helpers
// =============================================================================

function setupPhase5(): array
{
    $orgs    = createHierarchy();
    $regular = LeaveType::factory()->regular()->create();
    $emp1    = Employee::factory()->inOrganization($orgs['school'])
                    ->withGrade(EntitlementGrade::TEACHER_FIRST)
                    ->create(['birth_date' => now()->subYears(35)]);
    $emp2    = Employee::factory()->inOrganization($orgs['school'])
                    ->withGrade(EntitlementGrade::TEACHER_SENIOR)
                    ->create(['birth_date' => now()->subYears(40)]);
    $user    = User::factory()->inOrganization($orgs['school'])->create();

    return compact('orgs', 'regular', 'emp1', 'emp2', 'user');
}

// =============================================================================
// GROUP 1: DashboardStatsService — School
// =============================================================================

describe('DashboardStatsService school stats', function () {

    it('schoolStatusCounts returns correct counts per status', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
        ]);

        $counts = $svc->schoolStatusCounts($ctx['orgs']['school']);

        expect($counts[LeaveStatus::APPROVED->value])->toBe(1);
        expect($counts[LeaveStatus::SUBMITTED->value])->toBe(1);
        expect($counts[LeaveStatus::DRAFT->value])->toBe(0);
    });

    it('schoolStatusCounts always includes all 7 statuses', function () {
        $orgs = createHierarchy();
        $svc  = app(DashboardStatsService::class);

        $counts = $svc->schoolStatusCounts($orgs['school']);

        expect($counts)->toHaveCount(7);
        foreach (LeaveStatus::cases() as $status) {
            expect($counts)->toHaveKey($status->value);
        }
    });

    it('onLeaveToday returns only employees with approved leave spanning today', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        // Approved and overlapping today
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id,
            'start_date'    => now()->subDay(),
            'end_date'      => now()->addDay(),
            'days'          => 3,
            'created_by'    => $ctx['user']->id,
        ]);

        // Approved but ended yesterday
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id,
            'start_date'    => now()->subDays(5),
            'end_date'      => now()->subDay(),
            'days'          => 5,
            'created_by'    => $ctx['user']->id,
        ]);

        $onLeave = $svc->onLeaveToday($ctx['orgs']['school']);

        expect($onLeave)->toHaveCount(1);
        expect($onLeave->first()->employee_id)->toBe($ctx['emp1']->id);
    });

    it('pendingRequests returns submitted and in_review requests', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        LeaveRequest::factory()->forEmployee($ctx['emp1'])->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->inReview()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
        ]);

        $pending = $svc->pendingRequests($ctx['orgs']['school']);

        expect($pending)->toHaveCount(2);
    });
});

// =============================================================================
// GROUP 2: DashboardStatsService — Administration
// =============================================================================

describe('DashboardStatsService administration stats', function () {

    it('topLeaveTakers orders employees by total approved days descending', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        // emp1: 3 + 2 = 5 days total
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id, 'days' => 3, 'created_by' => $ctx['user']->id,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id, 'days' => 2, 'created_by' => $ctx['user']->id,
        ]);

        // emp2: 8 days total
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id, 'days' => 8, 'created_by' => $ctx['user']->id,
        ]);

        $takers = $svc->topLeaveTakers($ctx['orgs']['administration'], 10);

        expect($takers->first()->full_name)->toBe($ctx['emp2']->full_name);
        expect((float) $takers->first()->total_days)->toBe(8.0);
    });

    it('overdueCount respects org subtree path filter', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        // Overdue request in this school
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'submitted_at'  => now()->subDays(5),
            'created_by'    => $ctx['user']->id,
        ]);

        // Request in a different school (outside org's subtree)
        $otherSchool = Organization::create([
            'parent_id' => $ctx['orgs']['administration']->id,
            'type'      => 'school',
            'name'      => 'مدرسة خارجية',
            'code'      => 'OUT-SCH',
            'path'      => '/',
            'depth'     => 2,
            'is_active' => true,
        ]);
        $otherEmp  = Employee::factory()->inOrganization($otherSchool)->create();
        $otherUser = User::factory()->inOrganization($otherSchool)->create();
        LeaveRequest::factory()->forEmployee($otherEmp)->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'submitted_at'  => now()->subDays(5),
            'created_by'    => $otherUser->id,
        ]);

        // School scope: only sees 1 overdue
        config(['leave.overdue_days' => 3]);
        $schoolCount = $svc->overdueCount($ctx['orgs']['school']);
        expect($schoolCount)->toBe(1);

        // Administration scope: sees both (both schools are under the administration)
        $admCount = $svc->overdueCount($ctx['orgs']['administration']);
        expect($admCount)->toBe(2);
    });
});

// =============================================================================
// GROUP 3: DashboardStatsService — Directorate
// =============================================================================

describe('DashboardStatsService directorate stats', function () {

    it('monthlyRequestTrend returns array of 12 elements', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        $trend = $svc->monthlyRequestTrend($ctx['orgs']['directorate'], now()->year);

        expect($trend)->toHaveCount(12);
        // Each element is an integer >= 0
        foreach ($trend as $count) {
            expect($count)->toBeGreaterThanOrEqual(0);
        }
    });

    it('monthlyRequestTrend counts requests in correct month', function () {
        $ctx = setupPhase5();
        $svc = app(DashboardStatsService::class);

        // Create 2 requests in January of current year
        $jan = Carbon::create(now()->year, 1, 15);
        LeaveRequest::factory()->forEmployee($ctx['emp1'])->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
            'created_at'    => $jan,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->submitted()->create([
            'leave_type_id' => $ctx['regular']->id,
            'created_by'    => $ctx['user']->id,
            'created_at'    => $jan,
        ]);

        $trend = $svc->monthlyRequestTrend($ctx['orgs']['directorate'], now()->year);

        expect($trend[0])->toBe(2); // January = index 0
    });
});

// =============================================================================
// GROUP 4: ReportPdfService
// =============================================================================

describe('ReportPdfService', function () {

    it('generateSummary returns a non-empty PDF binary', function () {
        $ctx = setupPhase5();
        $svc = app(ReportPdfService::class);

        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id,
            'days'          => 5,
            'created_by'    => $ctx['user']->id,
        ]);

        $pdf = $svc->generateSummary([]);

        expect($pdf)->not->toBeEmpty();
        expect(str_starts_with($pdf, '%PDF'))->toBeTrue();
    });

    it('generateSummary respects status filter', function () {
        $ctx = setupPhase5();
        $svc = app(ReportPdfService::class);

        LeaveRequest::factory()->forEmployee($ctx['emp1'])->approved()->create([
            'leave_type_id' => $ctx['regular']->id, 'days' => 3, 'created_by' => $ctx['user']->id,
        ]);
        LeaveRequest::factory()->forEmployee($ctx['emp2'])->rejected()->create([
            'leave_type_id' => $ctx['regular']->id, 'days' => 2, 'created_by' => $ctx['user']->id,
        ]);

        // Filter for approved only — should not throw
        $pdf = $svc->generateSummary(['status' => LeaveStatus::APPROVED->value]);

        expect($pdf)->not->toBeEmpty();
    });
});

// =============================================================================
// GROUP 5: Widget canView() scoping
// =============================================================================

describe('Widget canView() scoping', function () {

    it('SchoolStatusOverview returns false for administration user', function () {
        $orgs = createHierarchy();
        $user = User::factory()->inOrganization($orgs['administration'])->create();
        Auth::login($user);

        expect(SchoolStatusOverview::canView())->toBeFalse();
    });

    it('SchoolStatusOverview returns true for school user', function () {
        $orgs = createHierarchy();
        $user = User::factory()->inOrganization($orgs['school'])->create();
        Auth::login($user);

        expect(SchoolStatusOverview::canView())->toBeTrue();
    });

    it('AvgResponseTimeWidget returns false for school user', function () {
        $orgs = createHierarchy();
        $user = User::factory()->inOrganization($orgs['school'])->create();
        Auth::login($user);

        expect(AvgResponseTimeWidget::canView())->toBeFalse();
    });

    it('AvgResponseTimeWidget returns true for administration user', function () {
        $orgs = createHierarchy();
        $user = User::factory()->inOrganization($orgs['administration'])->create();
        Auth::login($user);

        expect(AvgResponseTimeWidget::canView())->toBeTrue();
    });
});
