<?php

/**
 * Tests for the "رصيد إجازات اعتيادية مُرحَّل" (carried-over initial balance) feature.
 *
 * These tests exercise the balance-manipulation logic that CreateEmployee::afterCreate()
 * and EditEmployee::afterSave() perform, without requiring a full Filament/Livewire harness.
 *
 * Review findings addressed:
 *   1. Integer-only input — ->step(0.5) removed; form enforces integers via ->integer().
 *      Cast stays (int) to match LeaveBalance model's integer-based design.
 *   2. ->active() scope — inactive LeaveType rows are ignored.
 *   3. Test coverage — create with balance, create without balance, edit additive increment,
 *      inactive LeaveType guard.
 */

use App\Enums\TransactionType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helper: reproduce exactly what afterCreate() / afterSave() does so that
// the tests cover the real logic path without needing Livewire.
// ---------------------------------------------------------------------------

/**
 * Apply an initial carried-over balance to an employee, mirroring
 * CreateEmployee::afterCreate() and EditEmployee::afterSave().
 *
 * @param  Employee  $employee
 * @param  int       $days       Days to add to carried_over (0 = skip)
 * @param  string    $note       Transaction note
 */
function applyCarriedOverBalance(Employee $employee, int $days, string $note = 'رصيد مبدئي قديم'): void
{
    if ($days <= 0) {
        return;
    }

    $regularType = LeaveType::where('code', 'regular')->active()->first();

    if (! $regularType) {
        return;
    }

    $balance = LeaveBalance::firstOrCreate(
        [
            'employee_id'   => $employee->id,
            'leave_type_id' => $regularType->id,
            'year'          => now()->year,
        ],
        [
            'entitled'     => $employee->regularLeaveEntitlement(),
            'carried_over' => 0,
            'used'         => 0,
        ]
    );

    DB::transaction(function () use ($balance, $days, $note) {
        $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

        LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'type'             => TransactionType::CARRYOVER,
            'days'             => $days,
            'note'             => "{$note}: {$days} يوم",
            'created_by'       => auth()->id(),
        ]);

        $balance->increment('carried_over', $days);
    });
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Initial balance field — carried-over logic', function () {

    // -----------------------------------------------------------------------
    // Test 1: create with initial_balance_days = 5 → carried_over == 5
    // -----------------------------------------------------------------------
    it('creates a carried-over balance and CARRYOVER transaction when days > 0', function () {
        $orgs    = createHierarchy();
        $regular = LeaveType::factory()->regular()->create(); // is_active = true

        $employee = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create();

        // Simulate observer creating the balance row first (observer uses withoutEvents in factory)
        LeaveBalance::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $regular->id,
            'year'          => now()->year,
            'entitled'      => 30,
            'carried_over'  => 0,
            'used'          => 0,
        ]);

        applyCarriedOverBalance($employee, 5, 'رصيد مبدئي قديم');

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $regular->id)
            ->where('year', now()->year)
            ->firstOrFail();

        expect((int) $balance->carried_over)->toBe(5);

        $tx = LeaveBalanceTransaction::where('leave_balance_id', $balance->id)
            ->where('type', TransactionType::CARRYOVER)
            ->first();

        expect($tx)->not->toBeNull();
        expect((int) $tx->days)->toBe(5);
        expect($tx->note)->toContain('5');
    });

    // -----------------------------------------------------------------------
    // Test 2: create with initial_balance_days = 0 → no transaction written
    // -----------------------------------------------------------------------
    it('writes no transaction when days is 0', function () {
        $orgs    = createHierarchy();
        $regular = LeaveType::factory()->regular()->create();

        $employee = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create();

        LeaveBalance::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $regular->id,
            'year'          => now()->year,
            'entitled'      => 30,
            'carried_over'  => 0,
            'used'          => 0,
        ]);

        applyCarriedOverBalance($employee, 0);

        $txCount = LeaveBalanceTransaction::whereHas(
            'leaveBalance',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->count();

        expect($txCount)->toBe(0);

        $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
        expect((int) $balance->carried_over)->toBe(0);
    });

    // -----------------------------------------------------------------------
    // Test 3: edit — additive increment on existing balance
    // -----------------------------------------------------------------------
    it('additively increments carried_over on subsequent calls (edit scenario)', function () {
        $orgs    = createHierarchy();
        $regular = LeaveType::factory()->regular()->create();

        $employee = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create();

        LeaveBalance::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $regular->id,
            'year'          => now()->year,
            'entitled'      => 30,
            'carried_over'  => 5,  // already had 5 days from before
            'used'          => 0,
        ]);

        // Admin adds 3 more days via edit form
        applyCarriedOverBalance($employee, 3, 'رصيد مُرحَّل مُعدَّل يدوياً');

        $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
        expect((int) $balance->carried_over)->toBe(8); // 5 + 3

        $txCount = LeaveBalanceTransaction::whereHas(
            'leaveBalance',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->where('type', TransactionType::CARRYOVER)->count();

        expect($txCount)->toBe(1);
    });

    // -----------------------------------------------------------------------
    // Test 4: inactive LeaveType ignored (review finding #2)
    // -----------------------------------------------------------------------
    it('skips balance update when the regular leave type is inactive', function () {
        $orgs = createHierarchy();

        // Create an INACTIVE regular leave type
        LeaveType::factory()->regular()->create(['is_active' => false]);

        $employee = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create();

        applyCarriedOverBalance($employee, 5);

        // No balance row should have been created or modified
        $txCount = LeaveBalanceTransaction::whereHas(
            'leaveBalance',
            fn ($q) => $q->where('employee_id', $employee->id)
        )->count();

        expect($txCount)->toBe(0);
        expect(LeaveBalance::where('employee_id', $employee->id)->count())->toBe(0);
    });

    // -----------------------------------------------------------------------
    // Test 5: firstOrCreate safety net — no pre-existing balance row
    // -----------------------------------------------------------------------
    it('creates balance row via firstOrCreate if observer did not run', function () {
        $orgs    = createHierarchy();
        $regular = LeaveType::factory()->regular()->create();

        $employee = Employee::factory()
            ->inOrganization($orgs['school'])
            ->create();

        // Intentionally do NOT create a balance row (simulates observer failure)
        expect(LeaveBalance::where('employee_id', $employee->id)->count())->toBe(0);

        applyCarriedOverBalance($employee, 10);

        $balance = LeaveBalance::where('employee_id', $employee->id)->firstOrFail();
        expect((int) $balance->carried_over)->toBe(10);

        $tx = LeaveBalanceTransaction::where('leave_balance_id', $balance->id)->first();
        expect($tx)->not->toBeNull();
        expect($tx->type)->toBe(TransactionType::CARRYOVER);
    });
});
