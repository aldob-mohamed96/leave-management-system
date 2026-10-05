<?php

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\ReportPdfService;

// =============================================================================
// Test 1 — schoolStatusCounts: all 7 keys present, counts match
// =============================================================================

it('schoolStatusCounts returns all 7 status keys with correct counts', function () {
    $orgs   = createHierarchy();
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();

    // Create 2 approved, 1 submitted, 1 rejected
    foreach (['approved', 'approved', 'submitted', 'rejected'] as $state) {
        $employee = Employee::factory()->inOrganization($school)->create();
        LeaveRequest::factory()
            ->forEmployee($employee)
            ->{$state}()
            ->create([
                'organization_id' => $school->id,
                'leave_type_id'   => $leaveType->id,
                'created_by'      => $user->id,
            ]);
    }

    $service = app(DashboardStatsService::class);
    $counts  = $service->schoolStatusCounts($school);

    // All 7 keys must be present
    foreach (LeaveStatus::cases() as $case) {
        expect($counts)->toHaveKey($case->value);
    }

    expect($counts[LeaveStatus::APPROVED->value])->toBe(2);
    expect($counts[LeaveStatus::SUBMITTED->value])->toBe(1);
    expect($counts[LeaveStatus::REJECTED->value])->toBe(1);
    expect($counts[LeaveStatus::DRAFT->value])->toBe(0);
});

// =============================================================================
// Test 2 — onLeaveToday: returns currently-on-leave employees
// =============================================================================

it('onLeaveToday returns approved requests overlapping today', function () {
    $orgs   = createHierarchy();
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();

    // Active today
    $employee1 = Employee::factory()->inOrganization($school)->create();
    LeaveRequest::create([
        'number'          => 'LR-TODAY-001',
        'employee_id'     => $employee1->id,
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'start_date'      => today()->subDays(1),
        'end_date'        => today()->addDays(1),
        'days'            => 3,
        'status'          => LeaveStatus::APPROVED,
        'created_by'      => $user->id,
        'decided_at'      => now()->subDay(),
    ]);

    // Ended yesterday — should NOT appear
    $employee2 = Employee::factory()->inOrganization($school)->create();
    LeaveRequest::create([
        'number'          => 'LR-PAST-001',
        'employee_id'     => $employee2->id,
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'start_date'      => today()->subDays(5),
        'end_date'        => today()->subDays(1),
        'days'            => 5,
        'status'          => LeaveStatus::APPROVED,
        'created_by'      => $user->id,
        'decided_at'      => now()->subDays(5),
    ]);

    $service = app(DashboardStatsService::class);
    $result  = $service->onLeaveToday($school);

    expect($result)->toHaveCount(1);
    expect($result->first()->number)->toBe('LR-TODAY-001');
});

// =============================================================================
// Test 3 — topLeaveTakers: ordered by total days descending
// =============================================================================

it('topLeaveTakers returns employees ordered by total approved days', function () {
    $orgs   = createHierarchy();
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();

    // Employee A: 10 days
    $empA = Employee::factory()->inOrganization($school)->create(['full_name' => 'موظف أ']);
    LeaveRequest::factory()
        ->forEmployee($empA)
        ->approved()
        ->create([
            'organization_id' => $school->id,
            'leave_type_id'   => $leaveType->id,
            'created_by'      => $user->id,
            'days'            => 10,
        ]);

    // Employee B: 25 days
    $empB = Employee::factory()->inOrganization($school)->create(['full_name' => 'موظف ب']);
    LeaveRequest::factory()
        ->forEmployee($empB)
        ->approved()
        ->create([
            'organization_id' => $school->id,
            'leave_type_id'   => $leaveType->id,
            'created_by'      => $user->id,
            'days'            => 25,
        ]);

    $service = app(DashboardStatsService::class);
    $result  = $service->topLeaveTakers($school, 10);

    expect($result)->toHaveCount(2);
    expect((float) $result->first()->total_days)->toBe(25.0);
    expect((float) $result->last()->total_days)->toBe(10.0);
});

// =============================================================================
// Test 4 — monthlyRequestTrend: returns 12-element array, counts match
// =============================================================================

it('monthlyRequestTrend returns 12-element array with correct month counts', function () {
    $orgs = createHierarchy();
    $dir  = $orgs['directorate'];
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();
    $employee  = Employee::factory()->inOrganization($school)->create();

    $year = now()->year;

    // 2 requests in January, 1 in March
    LeaveRequest::factory()->forEmployee($employee)->count(2)->create([
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'created_by'      => $user->id,
        'created_at'      => "{$year}-01-15 10:00:00",
    ]);
    LeaveRequest::factory()->forEmployee($employee)->create([
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'created_by'      => $user->id,
        'created_at'      => "{$year}-03-10 10:00:00",
    ]);

    $service = app(DashboardStatsService::class);
    $trend   = $service->monthlyRequestTrend($dir, $year);

    expect($trend)->toHaveCount(12);
    expect($trend[0])->toBe(2);   // January (index 0)
    expect($trend[2])->toBe(1);   // March (index 2)
    expect($trend[1])->toBe(0);   // February (index 1) — no requests
});

// =============================================================================
// Test 5 — overdueCount: counts pending requests past threshold
// =============================================================================

it('overdueCount returns correct count for pending overdue requests', function () {
    $orgs   = createHierarchy();
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();
    $employee  = Employee::factory()->inOrganization($school)->create();

    // 2 overdue (submitted 5 days ago — past the 3-day default threshold)
    LeaveRequest::factory()->forEmployee($employee)->count(2)->create([
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'created_by'      => $user->id,
        'status'          => LeaveStatus::SUBMITTED,
        'submitted_at'    => now()->subDays(5),
    ]);

    // 1 recent — not overdue
    LeaveRequest::factory()->forEmployee($employee)->create([
        'organization_id' => $school->id,
        'leave_type_id'   => $leaveType->id,
        'created_by'      => $user->id,
        'status'          => LeaveStatus::SUBMITTED,
        'submitted_at'    => now()->subHours(2),
    ]);

    $service = app(DashboardStatsService::class);

    // Without org filter
    expect($service->overdueCount())->toBe(2);

    // With org filter (school subtree)
    expect($service->overdueCount($school))->toBe(2);
});

// =============================================================================
// Test 6 — ReportPdfService returns a non-empty PDF binary starting with %PDF
// =============================================================================

it('ReportPdfService generates a non-empty PDF binary starting with %PDF', function () {
    $orgs   = createHierarchy();
    $school = $orgs['school'];

    $leaveType = LeaveType::factory()->regular()->create();
    $user      = User::factory()->inOrganization($school)->create();
    $employee  = Employee::factory()->inOrganization($school)->create();

    LeaveRequest::factory()
        ->forEmployee($employee)
        ->approved()
        ->create([
            'organization_id' => $school->id,
            'leave_type_id'   => $leaveType->id,
            'created_by'      => $user->id,
        ]);

    $service = app(ReportPdfService::class);
    $pdf     = $service->generateSummary([]);

    expect($pdf)->not->toBeEmpty();
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});
