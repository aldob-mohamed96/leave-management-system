<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that restricts all queries to the authenticated user's
 * organization subtree, using the materialized path column for efficiency.
 *
 * Applied to: Organization, Employee, LeaveRequest
 *
 * Bypass with: Model::withoutGlobalScope(OrganizationScope::class)
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Skip scope for unauthenticated requests (e.g., artisan commands, tests that opt out)
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Super-admins (no organization) see everything
        if (! $user->organization_id) {
            return;
        }

        $organization = \App\Models\Organization::withoutGlobalScope(self::class)
            ->select('path')
            ->find($user->organization_id);

        if (! $organization) {
            // User has an organization_id that no longer exists — show nothing
            $builder->whereRaw('1 = 0');
            return;
        }

        /*
         * Determine which column to filter on.
         *
         * - Organization model  → filter on `organizations.path`
         * - Employee model      → join through organization to filter by path
         * - LeaveRequest model  → filter on `organization_id` using a subquery
         *
         * We use a consistent approach: for models with a direct `organization_id`,
         * we use a subquery on `organizations.path`. For the Organization model
         * itself, we use the path column directly.
         */
        $table = $model->getTable();

        if ($table === 'organizations') {
            $builder->where('path', 'like', $organization->path . '%');
        } else {
            // Models with organization_id FK (employees, leave_requests)
            $path = $organization->path;
            $builder->whereIn("{$table}.organization_id", function ($query) use ($path) {
                $query->select('id')
                    ->from('organizations')
                    ->where('path', 'like', $path . '%')
                    ->whereNull('deleted_at');
            });
        }
    }
}
