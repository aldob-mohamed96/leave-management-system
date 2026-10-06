<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    // -------------------------------------------------------------------------
    // Balance Retrieval / Creation
    // -------------------------------------------------------------------------

    /**
     * Get or create a LeaveBalance row for the given employee, type, and year.
     * When creating, entitled is set from regularLeaveEntitlement() for regular
     * leave; otherwise from the type's yearly_entitlement.
     */
    public function getOrCreateBalance(
        Employee $employee,
        LeaveType $leaveType,
        int $year
    ): LeaveBalance {
        $entitled = $leaveType->code === 'regular'
            ? $employee->regularLeaveEntitlement()
            : (int) $leaveType->yearly_entitlement;

        return LeaveBalance::firstOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year'          => $year,
            ],
            [
                'entitled'     => $entitled,
                'carried_over' => 0,
                'used'         => 0,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    /**
     * Deduct days from a balance when a leave request is approved.
     * Increments leave_balances.used and writes a DEDUCTION transaction.
     */
    public function deduct(
        LeaveBalance $balance,
        int $days,
        LeaveRequest $request,
        User $actedBy
    ): LeaveBalanceTransaction {
        return DB::transaction(function () use ($balance, $days, $request, $actedBy) {
            // Lock the row to prevent concurrent double-deductions
            $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

            $transaction = LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_request_id' => $request->id,
                'type'             => TransactionType::DEDUCTION,
                'days'             => $days,
                'created_by'       => $actedBy->id,
            ]);

            $balance->increment('used', $days);

            return $transaction;
        });
    }

    /**
     * Refund days back to a balance when a leave request is cancelled/rejected.
     * Decrements used (floored at 0) and writes a REFUND transaction.
     */
    public function refund(
        LeaveBalance $balance,
        int $days,
        LeaveRequest $request,
        User $actedBy
    ): LeaveBalanceTransaction {
        return DB::transaction(function () use ($balance, $days, $request, $actedBy) {
            $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

            $transaction = LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_request_id' => $request->id,
                'type'             => TransactionType::REFUND,
                'days'             => $days,
                'created_by'       => $actedBy->id,
            ]);

            $balance->update([
                'used' => max(0, (int) $balance->used - $days),
            ]);

            return $transaction;
        });
    }

    /**
     * Manual adjustment to a balance (admin operation).
     * Positive $days → increase entitled.
     * Negative $days → decrease used (floors at 0).
     */
    public function adjust(
        LeaveBalance $balance,
        int $days,
        string $note,
        User $actedBy
    ): LeaveBalanceTransaction {
        return DB::transaction(function () use ($balance, $days, $note, $actedBy) {
            $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

            $transaction = LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'type'             => TransactionType::ADJUSTMENT,
                'days'             => $days,
                'note'             => $note,
                'created_by'       => $actedBy->id,
            ]);

            if ($days > 0) {
                $balance->increment('entitled', $days);
            } elseif ($days < 0) {
                $balance->update([
                    'used' => max(0, (int) $balance->used - abs($days)),
                ]);
            }

            return $transaction;
        });
    }

    // -------------------------------------------------------------------------
    // Year-end Operations
    // -------------------------------------------------------------------------

    /**
     * Carry over remaining days from fromYear into toYear for all deductible
     * leave types of the given employee.
     */
    public function carryOver(Employee $employee, int $fromYear, int $toYear): void
    {
        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $fromYear)
            ->get()
            ->filter(fn(LeaveBalance $bal) =>
                $bal->leaveType->deducts_balance && $bal->remaining > 0
            );

        foreach ($balances as $bal) {
            $maxCarry  = config('leave.carry_over_max_days') ?? PHP_INT_MAX;
            $carryDays = min($bal->remaining, $maxCarry);

            $toBalance = $this->getOrCreateBalance($employee, $bal->leaveType, $toYear);

            DB::transaction(function () use ($toBalance, $carryDays, $bal) {
                $toBalance = LeaveBalance::lockForUpdate()->findOrFail($toBalance->id);

                LeaveBalanceTransaction::create([
                    'leave_balance_id' => $toBalance->id,
                    'type'             => TransactionType::CARRYOVER,
                    'days'             => $carryDays,
                ]);

                $toBalance->increment('carried_over', $carryDays);
            });
        }
    }

    /**
     * Set (or reset) annual entitlements for all active leave types.
     * Uses regularLeaveEntitlement() for the 'regular' code type.
     */
    public function accrueAnnual(Employee $employee, int $year): void
    {
        $leaveTypes = LeaveType::active()->where('yearly_entitlement', '>', 0)->get();

        foreach ($leaveTypes as $leaveType) {
            $days = $leaveType->code === 'regular'
                ? $employee->regularLeaveEntitlement()
                : (int) $leaveType->yearly_entitlement;

            $balance = $this->getOrCreateBalance($employee, $leaveType, $year);

            DB::transaction(function () use ($balance, $days) {
                $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

                LeaveBalanceTransaction::create([
                    'leave_balance_id' => $balance->id,
                    'type'             => TransactionType::ACCRUAL,
                    'days'             => $days,
                ]);

                // Full reset (not increment) — replaces whatever was set before
                $balance->update(['entitled' => $days]);
            });
        }
    }
}
