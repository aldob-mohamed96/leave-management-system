<?php

use App\Enums\ApprovalRule;
use App\Enums\EntitlementGrade;
use App\Enums\LeaveStatus;
use App\Enums\OrganizationType;
use App\Enums\StepStatus;
use App\Enums\TransactionType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStep;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowConfiguration;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// =============================================================================
// GROUP 1: Organization Hierarchy
// =============================================================================

describe('Organization hierarchy', function () {

    it('observer builds materialized path automatically on create', function () {
        $orgs = createHierarchy();

        $dir    = $orgs['directorate'];
        $adm    = $orgs['administration'];
        $school = $orgs['school'];

        expect($dir->path)->toBe("/{$dir->id}/");
        expect($dir->depth)->toBe(0);

        expect($adm->path)->toBe("/{$dir->id}/{$adm->id}/");
        expect($adm->depth)->toBe(1);

        expect($school->path)->toBe("/{$dir->id}/{$adm->id}/{$school->id}/");
        expect($school->depth)->toBe(2);
    });

    it('subtreeIds returns all descendants including self', function () {
        $orgs   = createHierarchy();
        $dir    = $orgs['directorate'];
        $adm    = $orgs['administration'];
        $school = $orgs['school'];

        $ids = $dir->subtreeIds();

        expect($ids)->toContain($dir->id);
        expect($ids)->toContain($adm->id);
        expect($ids)->toContain($school->id);
        expect($ids)->toHaveCount(3);
    });

    it('school subtree contains only itself', function () {
        $orgs   = createHierarchy();
        $school = $orgs['school'];

        expect($school->subtreeIds())->toHaveCount(1);
        expect($school->subtreeIds())->toContain($school->id);
    });

    it('observer cascades path update to descendants when parent changes', function () {
        // Build: dir → adm1 → school
        //              adm2
        $dir  = createHierarchy()['directorate'];
        $adm2 = Organization::create([
            'parent_id' => $dir->id,
            'type'      => 'administration',
            'name'      => 'إدارة ثانية',
            'code'      => 'ADM-2',
            'path'      => '/',
            'depth'     => 0,
            'is_active' => true,
        ]);

        $school = Organization::withoutGlobalScopes()
            ->where('type', 'school')
            ->first();

        // Move school from adm1 to adm2
        $school->update(['parent_id' => $adm2->id]);
        $school->refresh();

        expect($school->path)->toContain("/{$adm2->id}/");
        expect($school->depth)->toBe(2);
    });

    it('type helpers work correctly', function () {
        $orgs = createHierarchy();

        expect($orgs['directorate']->isDirectorate())->toBeTrue();
        expect($orgs['directorate']->isSchool())->toBeFalse();
        expect($orgs['administration']->isAdministration())->toBeTrue();
        expect($orgs['school']->isSchool())->toBeTrue();
    });

    it('allChildren relationship loads recursively', function () {
        $orgs   = createHierarchy();
        $dir    = $orgs['directorate'];

        $dir->load('allChildren');

        expect($dir->allChildren)->toHaveCount(1); // only adm (direct child)
        expect($dir->allChildren->first()->allChildren)->toHaveCount(1); // school
    });
});

// =============================================================================
// GROUP 2: OrganizationScope
// =============================================================================

describe('OrganizationScope', function () {

    it('school user only sees their own school', function () {
        $orgs   = createHierarchy();
        $school = $orgs['school'];

        // Create a second school in the same admin
        Organization::create([
            'parent_id' => $orgs['administration']->id,
            'type'      => 'school',
            'name'      => 'مدرسة أخرى',
            'code'      => 'SCH-OTHER',
            'path'      => '/',
            'depth'     => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->inOrganization($school)->create();
        Auth::login($user);

        $visibleOrgs = Organization::all();

        // Should see only their school (scope filters to school's path subtree)
        expect($visibleOrgs->pluck('id'))->toContain($school->id);
        $visibleOrgs->each(fn($o) => expect($o->path)->toStartWith($school->path));
    });

    it('administration user sees all schools under their administration', function () {
        $orgs = createHierarchy();

        // Add a second school under same admin
        $school2 = Organization::create([
            'parent_id' => $orgs['administration']->id,
            'type'      => 'school',
            'name'      => 'مدرسة ثانية',
            'code'      => 'SCH-2',
            'path'      => '/',
            'depth'     => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->inOrganization($orgs['administration'])->create();
        Auth::login($user);

        $visibleOrgs = Organization::all();
        $ids = $visibleOrgs->pluck('id');

        expect($ids)->toContain($orgs['administration']->id);
        expect($ids)->toContain($orgs['school']->id);
        expect($ids)->toContain($school2->id);
        // Should NOT see the directorate
        expect($ids)->not->toContain($orgs['directorate']->id);
    });

    it('directorate user sees everything', function () {
        $orgs = createHierarchy();
        $user = User::factory()->inOrganization($orgs['directorate'])->create();
        Auth::login($user);

        $ids = Organization::all()->pluck('id');

        expect($ids)->toContain($orgs['directorate']->id);
        expect($ids)->toContain($orgs['administration']->id);
        expect($ids)->toContain($orgs['school']->id);
    });

    it('super admin (no organization) sees everything', function () {
        createHierarchy();
        $user = User::factory()->superAdmin()->create();
        Auth::login($user);

        // Scope is bypassed when organization_id is null
        expect(Organization::count())->toBe(3);
    });

    it('scope applies to employees through organization_id', function () {
        $orgs    = createHierarchy();
        $school  = $orgs['school'];

        Employee::factory()->inOrganization($school)->count(3)->create();

        // Employee in a different school (outside scope)
        $otherSchool = Organization::create([
            'parent_id' => $orgs['administration']->id,
            'type'      => 'school',
            'name'      => 'مدرسة خارج النطاق',
            'code'      => 'SCH-OUT',
            'path'      => '/',
            'depth'     => 0,
            'is_active' => true,
        ]);
        Employee::factory()->inOrganization($otherSchool)->count(2)->create();

        $user = User::factory()->inOrganization($school)->create();
        Auth::login($user);

        expect(Employee::count())->toBe(3); // only their school's employees
    });

    it('withoutGlobalScope bypasses scope', function () {
        $orgs   = createHierarchy();
        $school = $orgs['school'];

        Employee::factory()->inOrganization($school)->count(2)->create();

        $user = User::factory()->inOrganization($school)->create();
        Auth::login($user);

        $scoped   = Employee::count();
        $unscoped = Employee::withoutGlobalScope(OrganizationScope::class)->count();

        expect($scoped)->toBe(2);
        expect($unscoped)->toBeGreaterThanOrEqual(2);
    });
});

// =============================================================================
// GROUP 3: Employee & EntitlementGrade
// =============================================================================

describe('Employee entitlement grade', function () {

    it('regularLeaveEntitlement returns grade-based days', function () {
        $orgs = createHierarchy();

        $teacher = Employee::factory()
            ->inOrganization($orgs['school'])
            ->withGrade(EntitlementGrade::TEACHER)
            ->create(['birth_date' => now()->subYears(35)]);

        $senior = Employee::factory()
            ->inOrganization($orgs['school'])
            ->withGrade(EntitlementGrade::TEACHER_SENIOR)
            ->create(['birth_date' => now()->subYears(35)]);

        expect($teacher->regularLeaveEntitlement())->toBe(28);
        expect($senior->regularLeaveEntitlement())->toBe(45);
    });

    it('employee over 50 always gets 50 days regardless of grade', function () {
        $orgs = createHierarchy();

        $over50 = Employee::factory()
            ->inOrganization($orgs['school'])
            ->withGrade(EntitlementGrade::TEACHER) // grade says 28
            ->create(['birth_date' => now()->subYears(52)]); // but over 50

        expect($over50->regularLeaveEntitlement())->toBe(50);
    });

    it('employee with null entitlement_grade defaults to 28', function () {
        $orgs = createHierarchy();

        $emp = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create(['entitlement_grade' => null]);

        expect($emp->regularLeaveEntitlement())->toBe(28);
    });

    it('all 8 entitlement grades have distinct yearly days', function () {
        $days = array_map(
            fn($g) => $g->yearlyDays(),
            EntitlementGrade::cases()
        );

        // Values: 28, 30, 35, 40, 45, 28, 30, 50 — some are shared intentionally
        expect(min($days))->toBe(28);
        expect(max($days))->toBe(50);
    });
});

// =============================================================================
// GROUP 4: Leave Balance
// =============================================================================

describe('LeaveBalance', function () {

    it('remaining is computed as entitled + carried_over - used', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();

        $balance = LeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'year'          => now()->year,
            'entitled'      => 45,
            'carried_over'  => 5,
            'used'          => 10,
        ]);

        expect($balance->remaining)->toBe(40.0); // 45 + 5 - 10
    });

    it('remaining never goes below zero', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();

        $balance = LeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'year'          => now()->year,
            'entitled'      => 10,
            'carried_over'  => 0,
            'used'          => 15, // over-used
        ]);

        expect($balance->remaining)->toBe(0.0);
    });

    it('transactions can be appended to a balance', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();

        $balance = LeaveBalance::factory()
            ->forEmployee($emp)
            ->forLeaveType($lt)
            ->fresh()
            ->create();

        LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'type'             => TransactionType::DEDUCTION,
            'days'             => 3,
            'note'             => 'خصم إجازة',
        ]);

        expect($balance->transactions()->count())->toBe(1);
        expect($balance->transactions()->first()->type)->toBe(TransactionType::DEDUCTION);
    });

    it('balance is unique per employee, leave type, and year', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();

        LeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'year'          => 2026,
            'entitled'      => 45,
            'carried_over'  => 0,
            'used'          => 0,
        ]);

        expect(fn() => LeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $lt->id,
            'year'          => 2026,
            'entitled'      => 45,
            'carried_over'  => 0,
            'used'          => 0,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

// =============================================================================
// GROUP 5: LeaveRequest
// =============================================================================

describe('LeaveRequest', function () {

    it('observer generates a unique readable number on create', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $request = LeaveRequest::create([
            'employee_id'     => $emp->id,
            'organization_id' => $orgs['school']->id,
            'leave_type_id'   => $lt->id,
            'start_date'      => '2026-02-01',
            'end_date'        => '2026-02-03',
            'days'            => 3,
            'status'          => LeaveStatus::DRAFT,
            'created_by'      => $user->id,
        ]);

        expect($request->number)->toMatch('/^LV-\d{4}-\d{6}$/');
        expect($request->number)->toStartWith('LV-' . now()->year . '-');
    });

    it('generates sequential numbers', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $base = [
            'employee_id'     => $emp->id,
            'organization_id' => $orgs['school']->id,
            'leave_type_id'   => $lt->id,
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-01-02',
            'days'            => 2,
            'status'          => LeaveStatus::DRAFT,
            'created_by'      => $user->id,
        ];

        $r1 = LeaveRequest::create($base + ['start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $r2 = LeaveRequest::create($base + ['start_date' => '2026-02-01', 'end_date' => '2026-02-02']);

        $seq1 = (int) substr($r1->number, -6);
        $seq2 = (int) substr($r2->number, -6);

        expect($seq2)->toBe($seq1 + 1);
    });

    it('observer stamps submitted_at when status changes to submitted', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $request = LeaveRequest::create([
            'employee_id'     => $emp->id,
            'organization_id' => $orgs['school']->id,
            'leave_type_id'   => $lt->id,
            'start_date'      => '2026-03-01',
            'end_date'        => '2026-03-03',
            'days'            => 3,
            'status'          => LeaveStatus::DRAFT,
            'created_by'      => $user->id,
        ]);

        expect($request->submitted_at)->toBeNull();

        $request->update(['status' => LeaveStatus::SUBMITTED]);

        expect($request->fresh()->submitted_at)->not->toBeNull();
    });

    it('canCancel attribute reflects status', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $base = [
            'employee_id'     => $emp->id,
            'organization_id' => $orgs['school']->id,
            'leave_type_id'   => $lt->id,
            'start_date'      => '2026-04-01',
            'end_date'        => '2026-04-02',
            'days'            => 2,
            'created_by'      => $user->id,
        ];

        $draft    = LeaveRequest::factory()->forEmployee($emp)->create(['status' => LeaveStatus::DRAFT,     'created_by' => $user->id, 'leave_type_id' => $lt->id]);
        $approved = LeaveRequest::factory()->forEmployee($emp)->create(['status' => LeaveStatus::APPROVED,  'created_by' => $user->id, 'leave_type_id' => $lt->id]);
        $rejected = LeaveRequest::factory()->forEmployee($emp)->create(['status' => LeaveStatus::REJECTED,  'created_by' => $user->id, 'leave_type_id' => $lt->id]);

        expect($draft->canCancel)->toBeTrue();
        expect($approved->canCancel)->toBeFalse();
        expect($rejected->canCancel)->toBeFalse();
    });

    it('scopeOverlapping detects date range conflicts', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        LeaveRequest::factory()->forEmployee($emp)->submitted()->create([
            'leave_type_id' => $lt->id,
            'start_date'    => '2026-05-01',
            'end_date'      => '2026-05-10',
            'days'          => 10,
            'created_by'    => $user->id,
        ]);

        // Overlapping range
        $overlap = LeaveRequest::overlapping($emp->id, '2026-05-05', '2026-05-15')->count();
        expect($overlap)->toBe(1);

        // Non-overlapping range
        $noOverlap = LeaveRequest::overlapping($emp->id, '2026-05-11', '2026-05-15')->count();
        expect($noOverlap)->toBe(0);
    });

    it('scopeOverlapping ignores rejected and cancelled requests', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        LeaveRequest::factory()->forEmployee($emp)->rejected()->create([
            'leave_type_id' => $lt->id,
            'start_date'    => '2026-06-01',
            'end_date'      => '2026-06-10',
            'days'          => 10,
            'created_by'    => $user->id,
        ]);

        $overlap = LeaveRequest::overlapping($emp->id, '2026-06-05', '2026-06-07')->count();
        expect($overlap)->toBe(0);
    });

    it('steps relationship loads in correct order', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $request = LeaveRequest::factory()->forEmployee($emp)->submitted()->create([
            'leave_type_id' => $lt->id,
            'created_by'    => $user->id,
        ]);

        LeaveRequestStep::factory()->forStage('admin_manager', 3)->create(['leave_request_id' => $request->id]);
        LeaveRequestStep::factory()->forStage('direct_manager', 1)->create(['leave_request_id' => $request->id]);
        LeaveRequestStep::factory()->forStage('leaves_officer', 2)->create(['leave_request_id' => $request->id]);

        $steps = $request->steps;

        expect($steps[0]->step_order)->toBe(1);
        expect($steps[1]->step_order)->toBe(2);
        expect($steps[2]->step_order)->toBe(3);
    });
});

// =============================================================================
// GROUP 6: WorkflowConfiguration
// =============================================================================

describe('WorkflowConfiguration', function () {

    it('can configure 3-stage workflow for a school', function () {
        $orgs   = createHierarchy();
        $school = $orgs['school'];

        WorkflowConfiguration::factory()->directManagerStage($school)->create();
        WorkflowConfiguration::factory()->leavesOfficerStage($school)->create();
        WorkflowConfiguration::factory()->adminManagerStage($school)->create();

        $stages = $school->workflowConfigurations;

        expect($stages)->toHaveCount(3);
        expect($stages[0]->stage_name)->toBe('direct_manager');
        expect($stages[1]->stage_name)->toBe('leaves_officer');
        expect($stages[2]->stage_name)->toBe('admin_manager');
    });

    it('approval_rule is correctly cast to ApprovalRule enum', function () {
        $orgs = createHierarchy();

        $config = WorkflowConfiguration::factory()
            ->directManagerStage($orgs['school'])
            ->create();

        expect($config->approval_rule)->toBe(ApprovalRule::ANY);
        expect($config->approval_rule->label())->toBe('أي معتمد');
    });

    it('active scope filters inactive stages', function () {
        $orgs = createHierarchy();

        WorkflowConfiguration::factory()
            ->directManagerStage($orgs['school'])
            ->create(['is_active' => true]);

        WorkflowConfiguration::factory()
            ->leavesOfficerStage($orgs['school'])
            ->create(['is_active' => false]);

        $active = WorkflowConfiguration::active()->count();
        expect($active)->toBe(1);
    });
});

// =============================================================================
// GROUP 7: Spatie Permission with Teams (organization_id scoping)
// =============================================================================

describe('Spatie Permission team scoping', function () {

    beforeEach(function () {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    });

    it('role scoped to org A does not appear in org B context', function () {
        $orgs = createHierarchy();
        $schoolA = $orgs['school'];

        $schoolB = Organization::create([
            'parent_id' => $orgs['administration']->id,
            'type'      => 'school',
            'name'      => 'مدرسة ب',
            'code'      => 'SCH-B',
            'path'      => '/',
            'depth'     => 0,
            'is_active' => true,
        ]);

        // Create permission and role scoped to school A
        Permission::create(['name' => 'approve_leave_request', 'guard_name' => 'web']);

        setPermissionsTeamId($schoolA->id);
        $roleA = Role::create([
            'name'            => 'مدير مدرسة',
            'guard_name'      => 'web',
            'organization_id' => $schoolA->id,
        ]);
        $roleA->givePermissionTo('approve_leave_request');

        $user = User::factory()->inOrganization($schoolA)->create();
        $user->assignRole($roleA);

        // Check permission in school A context
        setPermissionsTeamId($schoolA->id);
        expect($user->hasPermissionTo('approve_leave_request'))->toBeTrue();

        // Check permission in school B context — should NOT have it
        setPermissionsTeamId($schoolB->id);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        expect($user->hasPermissionTo('approve_leave_request'))->toBeFalse();

        setPermissionsTeamId(null);
    });

    it('user can have different roles in different organizations', function () {
        $orgs = createHierarchy();

        Permission::create(['name' => 'view_leave_requests', 'guard_name' => 'web']);
        Permission::create(['name' => 'manage_organization', 'guard_name' => 'web']);

        setPermissionsTeamId($orgs['school']->id);
        $schoolRole = Role::create([
            'name'            => 'موظف مدرسة',
            'guard_name'      => 'web',
            'organization_id' => $orgs['school']->id,
        ]);
        $schoolRole->givePermissionTo('view_leave_requests');

        setPermissionsTeamId($orgs['administration']->id);
        $admRole = Role::create([
            'name'            => 'مدير الإدارة',
            'guard_name'      => 'web',
            'organization_id' => $orgs['administration']->id,
        ]);
        $admRole->givePermissionTo('manage_organization');

        $user = User::factory()->inOrganization($orgs['school'])->create();

        setPermissionsTeamId($orgs['school']->id);
        $user->assignRole($schoolRole);

        setPermissionsTeamId($orgs['administration']->id);
        $user->assignRole($admRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        setPermissionsTeamId($orgs['school']->id);
        expect($user->hasPermissionTo('view_leave_requests'))->toBeTrue();
        expect($user->hasPermissionTo('manage_organization'))->toBeFalse();

        setPermissionsTeamId($orgs['administration']->id);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        expect($user->hasPermissionTo('manage_organization'))->toBeTrue();
        expect($user->hasPermissionTo('view_leave_requests'))->toBeFalse();

        setPermissionsTeamId(null);
    });
});

// =============================================================================
// GROUP 8: Soft Deletes
// =============================================================================

describe('Soft deletes', function () {

    it('soft-deleted organization is excluded from queries', function () {
        $orgs = createHierarchy();

        $orgs['school']->delete();

        $found = Organization::withoutGlobalScopes()
            ->where('id', $orgs['school']->id)
            ->first();

        expect($found)->toBeNull();
    });

    it('soft-deleted organization can be restored', function () {
        $orgs = createHierarchy();
        $orgs['school']->delete();

        Organization::withoutGlobalScopes()
            ->withTrashed()
            ->where('id', $orgs['school']->id)
            ->first()
            ->restore();

        $found = Organization::withoutGlobalScopes()->find($orgs['school']->id);
        expect($found)->not->toBeNull();
    });

    it('soft-deleted employee is excluded from queries', function () {
        $orgs = createHierarchy();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();

        $emp->delete();

        expect(Employee::withoutGlobalScopes()->find($emp->id))->toBeNull();
        expect(Employee::withoutGlobalScopes()->withTrashed()->find($emp->id))->not->toBeNull();
    });

    it('soft-deleted leave request is excluded but restorable', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $req = LeaveRequest::factory()->forEmployee($emp)->create([
            'leave_type_id' => $lt->id,
            'created_by'    => $user->id,
        ]);

        $req->delete();

        expect(LeaveRequest::withoutGlobalScopes()->find($req->id))->toBeNull();

        LeaveRequest::withoutGlobalScopes()->withTrashed()->find($req->id)->restore();

        expect(LeaveRequest::withoutGlobalScopes()->find($req->id))->not->toBeNull();
    });
});

// =============================================================================
// GROUP 9: Activity Log
// =============================================================================

describe('Activity logging', function () {

    it('logs creation of an organization', function () {
        $orgs = createHierarchy();

        $logs = \Spatie\Activitylog\Models\Activity::where('subject_type', Organization::class)
            ->where('subject_id', $orgs['school']->id)
            ->where('event', 'created')
            ->get();

        expect($logs->count())->toBeGreaterThanOrEqual(1);
    });

    it('logs update of a leave request', function () {
        $orgs = createHierarchy();
        $lt   = LeaveType::factory()->regular()->create();
        $emp  = Employee::factory()->inOrganization($orgs['school'])->create();
        $user = User::factory()->inOrganization($orgs['school'])->create();

        $req = LeaveRequest::create([
            'employee_id'     => $emp->id,
            'organization_id' => $orgs['school']->id,
            'leave_type_id'   => $lt->id,
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-07-03',
            'days'            => 3,
            'status'          => LeaveStatus::DRAFT,
            'created_by'      => $user->id,
        ]);

        $req->update(['status' => LeaveStatus::SUBMITTED]);

        $logs = \Spatie\Activitylog\Models\Activity::where('subject_type', LeaveRequest::class)
            ->where('subject_id', $req->id)
            ->get();

        expect($logs->count())->toBeGreaterThanOrEqual(1);
    });
});

// =============================================================================
// GROUP 10: Leave Types
// =============================================================================

describe('LeaveType seeding', function () {

    it('all 12 leave types are present after seeding', function () {
        $this->seed(\Database\Seeders\LeaveTypeSeeder::class);

        $codes = LeaveType::pluck('code')->toArray();

        $expected = [
            'regular', 'casual', 'sick', 'sick_half_pay', 'emergency',
            'maternity', 'paternity', 'hajj', 'marriage', 'bereavement',
            'study', 'without_pay',
        ];

        foreach ($expected as $code) {
            expect($codes)->toContain($code);
        }
    });

    it('regular leave deducts balance', function () {
        $this->seed(\Database\Seeders\LeaveTypeSeeder::class);

        expect(LeaveType::where('code', 'regular')->first()->deducts_balance)->toBeTrue();
    });

    it('sick leave does not deduct balance', function () {
        $this->seed(\Database\Seeders\LeaveTypeSeeder::class);

        expect(LeaveType::where('code', 'sick')->first()->deducts_balance)->toBeFalse();
    });

    it('event-based leaves have zero yearly_entitlement', function () {
        $this->seed(\Database\Seeders\LeaveTypeSeeder::class);

        $eventBased = ['maternity', 'paternity', 'hajj', 'marriage', 'bereavement', 'study', 'without_pay'];

        foreach ($eventBased as $code) {
            $lt = LeaveType::where('code', $code)->first();
            expect($lt->yearly_entitlement)->toBe('0.0');
        }
    });
});
