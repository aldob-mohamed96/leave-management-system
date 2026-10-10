<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // -------------------------------------------------------
        // 1. Find the directorate organization to scope the admin
        // -------------------------------------------------------
        $directorate = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::DIRECTORATE->value)
            ->first();

        if (! $directorate) {
            $this->command->error('No directorate found. Run DatabaseSeeder first.');
            return;
        }

        // -------------------------------------------------------
        // 2. Create or update the super admin user
        // -------------------------------------------------------
        $user = User::updateOrCreate(
            ['email' => 'admin@rtltec.com'],
            [
                'name'                 => 'مدير النظام',
                'password'             => Hash::make('Admin@2026!'),
                'organization_id'      => $directorate->id,
                'is_active'            => true,
                'must_change_password' => false,
            ]
        );

        // -------------------------------------------------------
        // 3. Give all permissions directly on the user
        //    (bypasses role-team scoping)
        // -------------------------------------------------------
        $allPermissions = Permission::all();
        $user->syncPermissions($allPermissions);

        // -------------------------------------------------------
        // 4. Also assign the مدير المديرية role for the directorate
        // -------------------------------------------------------
        setPermissionsTeamId($directorate->id);

        $role = Role::where('name', 'مدير المديرية')
            ->where('organization_id', $directorate->id)
            ->first();

        if ($role) {
            $user->syncRoles([$role]);
        }

        setPermissionsTeamId(null);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✓ Super admin created:');
        $this->command->info("  Email    : admin@rtltec.com");
        $this->command->info("  Password : Admin@2026!");
        $this->command->info("  Org      : {$directorate->name}");
    }
}
