<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Rules\Leave\CasualBeforeRegularRule;
use App\Rules\Leave\MaxDaysPerRequestRule;
use App\Rules\Leave\NoOverlapRule;
use App\Rules\Leave\SufficientBalanceRule;
use App\Rules\Leave\WorkingDaysRule;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * صفحة تعديل طلب إجازة.
 *
 * السماح بالتعديل فقط للطلبات في حالة DRAFT أو RETURNED.
 *
 * عند الحفظ:
 *  - DRAFT   → نعيد حساب أيام العمل + نتحقق من التداخل والحد الأقصى والرصيد
 *  - RETURNED → نفس التحقق (الطلب سيُعاد تقديمه لاحقاً عبر submit)
 *
 * لا نستدعي LeaveRequestService::create هنا لأن الطلب موجود بالفعل؛
 * نكتفي بتشغيل Rules منفردة قبل تحديث السجل.
 */
class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    // -------------------------------------------------------------------------
    // Access guard
    // -------------------------------------------------------------------------

    public function authorizeAccess(): void
    {
        $record = $this->getRecord();

        abort_unless(Gate::allows('update', $record), 403, 'ليس لديك صلاحية تعديل هذا الطلب.');

        if (! $record->status->canBeEdited()) {
            abort(403, 'لا يمكن تعديل الطلب في حالته الحالية.');
        }
    }

    // -------------------------------------------------------------------------
    // Header actions
    // -------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف')
                ->visible(fn () => Gate::allows('delete', $this->getRecord())),
        ];
    }

    // -------------------------------------------------------------------------
    // Save override with validation
    // -------------------------------------------------------------------------

    /**
     * Run validation rules before persisting changes.
     * Both DRAFT and RETURNED requests go through the same checks so that
     * when the user later submits, no surprises arise.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LeaveRequest $record */

        $allowed = [
            'employee_id',
            'leave_type_id',
            'start_date',
            'end_date',
            'days',
            'written_at',
            'substitute_employee_id',
            'reason',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));

        // Re-calculate working days
        $workingDaysRule = new WorkingDaysRule();
        $workingDaysRule->setData($updateData);
        $workingDaysRule->validate('days', $updateData['days'] ?? 0, fn($msg) => null);

        if ($workingDaysRule->calculatedDays > 0) {
            $updateData['days'] = $workingDaysRule->calculatedDays;
        }

        $employee  = Employee::withoutGlobalScopes()->findOrFail($updateData['employee_id'] ?? $record->employee_id);
        $leaveType = LeaveType::findOrFail($updateData['leave_type_id'] ?? $record->leave_type_id);

        // No overlap (exclude the current request)
        $overlapErrors = [];
        $overlapRule   = new NoOverlapRule($employee->id, $record->id);
        $overlapRule->setData($updateData);
        $overlapRule->validate('days', $updateData['days'], function ($msg) use (&$overlapErrors) {
            $overlapErrors[] = $msg;
        });

        if (! empty($overlapErrors)) {
            Notification::make()
                ->title('تعديل غير مسموح')
                ->body($overlapErrors[0])
                ->danger()
                ->send();

            $this->halt();
        }

        // Max days per request
        $maxErrors = [];
        $maxRule   = new MaxDaysPerRequestRule($leaveType);
        $maxRule->validate('days', $updateData['days'], function ($msg) use (&$maxErrors) {
            $maxErrors[] = $msg;
        });

        if (! empty($maxErrors)) {
            Notification::make()
                ->title('تجاوز الحد الأقصى')
                ->body($maxErrors[0])
                ->danger()
                ->send();

            $this->halt();
        }

        // Sufficient balance (warn only — don't block, consistent with create behavior)
        $balanceRule = new SufficientBalanceRule($employee, $leaveType, now()->year);
        $balanceRule->validate('days', $updateData['days'], function ($msg) {
            Notification::make()
                ->title('تحذير: الرصيد غير كافٍ')
                ->body($msg)
                ->warning()
                ->send();
        });

        // Casual before regular warning
        $casualRule = new CasualBeforeRegularRule($employee, $leaveType, now()->year);
        $casualRule->validate('days', $updateData['days'], fn($msg) => null);
        if ($casualRule->warning) {
            Notification::make()
                ->title('تنبيه')
                ->body($casualRule->warning)
                ->warning()
                ->send();
        }

        $record->update($updateData);

        return $record;
    }
}
