<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
{
    use HandlesAuthorization;

    /**
     * هل يمكن للمستخدم عرض قائمة المؤسسات؟
     */
    public function viewAny(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization')
            || $user->hasPermissionTo('view_employees');
    }

    /**
     * هل يمكن للمستخدم عرض مؤسسة بعينها؟
     */
    public function view(User $user, Organization $organization): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization')
            || $user->hasPermissionTo('view_employees');
    }

    /**
     * هل يمكن للمستخدم إنشاء مؤسسة جديدة؟
     */
    public function create(User $user): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization');
    }

    /**
     * هل يمكن للمستخدم تعديل مؤسسة؟
     */
    public function update(User $user, Organization $organization): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization');
    }

    /**
     * هل يمكن للمستخدم حذف مؤسسة؟
     */
    public function delete(User $user, Organization $organization): bool
    {
        $user->setOrganizationTeam();

        return $user->hasPermissionTo('manage_organization');
    }
}
