<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
            ->where('code', 'DIR-LXR')
            ->first();

        if (! $directorate) {
            $directorate = Organization::withoutGlobalScopes()
                ->where('type', OrganizationType::DIRECTORATE->value)
                ->first();
        }

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
        // 3. Assign the مدير المديرية role (has all permissions)
        //    scoped to the directorate team
        // -------------------------------------------------------
        setPermissionsTeamId($directorate->id);

        $role = Role::where('name', 'مدير المديرية')
            ->where('organization_id', $directorate->id)
            ->first();

        if ($role) {
            $user->syncRoles([$role]);
        } else {
            $this->command->warn('Role مدير المديرية not found — user created without role.');
        }

        setPermissionsTeamId(null);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✓ Super admin created:');
        $this->command->info("  Email    : admin@rtltec.com");
        $this->command->info("  Password : Admin@2026!");
        $this->command->info("  Org      : {$directorate->name}");
    }
}
