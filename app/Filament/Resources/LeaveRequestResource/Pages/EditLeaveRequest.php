<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * صفحة تعديل طلب إجازة.
 * لا تُتاح إلا للطلبات القابلة للتعديل (DRAFT أو RETURNED).
 * لطلبات RETURNED: يتم تحديث الحقول مباشرة دون إعادة تشغيل LeaveRequestService::create.
 * لطلبات DRAFT:    يُعاد توجيه البيانات عبر LeaveRequestService::create لضمان التحقق.
 */
class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    /**
     * التحقق من صلاحية التعديل قبل عرض الصفحة.
     */
    public function authorizeAccess(): void
    {
        $record = $this->getRecord();

        abort_unless(
            Gate::allows('update', $record),
            403,
            'ليس لديك صلاحية تعديل هذا الطلب.'
        );

        if (! $record->status->canBeEdited()) {
            abort(403, 'لا يمكن تعديل الطلب في حالته الحالية.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * تجاوز عملية الحفظ:
     * - RETURNED → تحديث الحقول مباشرة (الحالة تبقى RETURNED)
     * - DRAFT    → يُمكن تحديث مباشرة (LeaveRequestService يُستدعى عند التقديم)
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LeaveRequest $record */

        // Fields allowed to be updated directly on the request record
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

        $record->update($updateData);

        return $record;
    }
}
