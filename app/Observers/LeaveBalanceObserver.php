<?php

namespace App\Observers;

use App\Models\LeaveBalance;
use Illuminate\Support\Facades\Log;

/**
 * Guards the leave balance against direct `used` updates made outside
 * of LeaveBalanceService (which is the only allowed path in Phase 2).
 *
 * In Phase 1 this observer logs a warning when `used` is modified without
 * a corresponding transaction in `leave_balance_transactions`.
 * In Phase 2 LeaveBalanceService will enforce this programmatically.
 */
class LeaveBalanceObserver
{
    /**
     * Warn (don't block yet) if `used` is being updated directly.
     * LeaveBalanceService should be the only caller that changes `used`.
     */
    public function updating(LeaveBalance $balance): void
    {
        if (! $balance->isDirty('used')) {
            return;
        }

        // Check if there's a matching transaction for this balance change
        // (Phase 2 will enforce this strictly with a DB transaction check)
        $hasMatchingTransaction = $balance->transactions()
            ->where('created_at', '>=', now()->subSeconds(2))
            ->exists();

        if (! $hasMatchingTransaction) {
            Log::warning('[LeaveBalance] Direct update of `used` detected outside LeaveBalanceService.', [
                'leave_balance_id' => $balance->id,
                'employee_id'      => $balance->employee_id,
                'old_used'         => $balance->getOriginal('used'),
                'new_used'         => $balance->used,
                'trace'            => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5))
                    ->pluck('function')
                    ->implode(' → '),
            ]);
        }
    }

    /**
     * Log balance changes for audit trail supplement.
     */
    public function updated(LeaveBalance $balance): void
    {
        $changed = array_keys($balance->getChanges());

        if (empty(array_intersect($changed, ['entitled', 'carried_over', 'used']))) {
            return;
        }

        Log::info('[LeaveBalance] Balance updated.', [
            'leave_balance_id' => $balance->id,
            'employee_id'      => $balance->employee_id,
            'leave_type_id'    => $balance->leave_type_id,
            'year'             => $balance->year,
            'changes'          => array_intersect_key(
                $balance->getChanges(),
                array_flip(['entitled', 'carried_over', 'used'])
            ),
            'remaining'        => $balance->remaining,
        ]);
    }
}
