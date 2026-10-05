<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * هل يمكن للمستخدم عرض قائمة المستخدمين؟
     */
    public function viewAny(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_users');
    }

    /**
     * هل يمكن للمستخدم عرض مستخدم بعينه؟
     */
    public function view(User $user, User $model): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_users');
    }

    /**
     * هل يمكن للمستخدم إنشاء مستخدم جديد؟
     */
    public function create(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_users');
    }

    /**
     * هل يمكن للمستخدم تعديل مستخدم؟
     */
    public function update(User $user, User $model): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_users');
    }

    /**
     * هل يمكن للمستخدم حذف مستخدم؟
     */
    public function delete(User $user, User $model): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_users');
    }
}
