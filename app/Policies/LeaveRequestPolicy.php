<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveRequestPolicy
{
    use HandlesAuthorization;

    /**
     * هل يمكن للمستخدم عرض قائمة طلبات الإجازات؟
     */
    public function viewAny(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('view_leave_requests');
    }

    /**
     * هل يمكن للمستخدم عرض طلب إجازة بعينه؟
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('view_leave_requests');
    }

    /**
     * هل يمكن للمستخدم إنشاء طلب إجازة جديد؟
     */
    public function create(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('create_leave_request');
    }

    /**
     * هل يمكن للمستخدم تعديل طلب إجازة؟
     * يشترط أن تكون حالة الطلب قابلة للتعديل.
     */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('edit_leave_request')
            && $leaveRequest->status->canBeEdited();
    }

    /**
     * هل يمكن للمستخدم حذف طلب إجازة؟
     * مخصص للمدير فقط (manage_organization).
     */
    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization');
    }

    /**
     * هل يمكن للمستخدم تقديم طلب إجازة؟
     */
    public function submit(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('submit_leave_request');
    }

    /**
     * هل يمكن للمستخدم اعتماد طلب الإجازة؟
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('approve_leave_request');
    }

    /**
     * هل يمكن للمستخدم رفض طلب الإجازة؟
     */
    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('reject_leave_request');
    }

    /**
     * هل يمكن للمستخدم إعادة طلب الإجازة للتعديل؟
     */
    public function return(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('return_leave_request');
    }

    /**
     * هل يمكن للمستخدم إلغاء طلب الإجازة؟
     * يشترط أن تكون حالة الطلب قابلة للإلغاء.
     */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('cancel_leave_request')
            && $leaveRequest->status->canBeCancelled();
    }
}
