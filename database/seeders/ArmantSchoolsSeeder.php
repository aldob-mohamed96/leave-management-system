<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ArmantSchoolsSeeder extends Seeder
{
    private const ROLE_MAP = [
        'manager'    => 'مدير مدرسة',
        'specialist' => 'أخصائي',
    ];

    public function run(): void
    {
        $path = database_path('data/armant-schools.json');

        if (! File::exists($path)) {
            $this->command?->error("Missing data file: {$path}");

            return;
        }

        $payload = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        $directorate = $this->ensureOrganization(
            type: OrganizationType::DIRECTORATE,
            name: $payload['directorate'],
            code: 'DIR-LXR',
            parent: null,
        );

        $administration = $this->ensureOrganization(
            type: OrganizationType::ADMINISTRATION,
            name: $payload['administration'],
            code: 'ADM-ARM',
            parent: $directorate,
        );

        $schools = [];
        foreach ($payload['schools'] as $schoolData) {
            $schools[] = $this->ensureOrganization(
                type: OrganizationType::SCHOOL,
                name: $schoolData['name'],
                code: strtoupper($schoolData['code']),
                parent: $administration,
            );
        }

        $this->command?->info('✓ Armant hierarchy ready: directorate, administration, '.count($schools).' schools.');

        // Create/refresh roles for any newly added organizations.
        $this->call(RoleAndPermissionSeeder::class);

        $allowedEmails = [];
        $userCount = 0;

        foreach ($payload['schools'] as $index => $schoolData) {
            $school = $schools[$index];
            $code = strtolower($schoolData['code']);

            foreach ($schoolData['accounts'] as $account) {
                $roleName = self::ROLE_MAP[$account['role']] ?? null;

                if (! $roleName) {
                    $this->command?->warn("Unknown account role [{$account['role']}] for {$schoolData['code']}");

                    continue;
                }

                $allowedEmails[] = $account['email'];

                // Migrate legacy @armant-schools.example accounts to the new domain.
                $legacyEmail = "{$code}.{$account['role']}@armant-schools.example";
                $user = User::where('email', $account['email'])->first()
                    ?? User::where('email', $legacyEmail)->first();

                if ($user) {
                    $user->fill([
                        'email'                => $account['email'],
                        'name'                 => "{$account['title']} — {$school->name}",
                        'password'             => $account['password'],
                        'organization_id'      => $school->id,
                        'is_active'            => true,
                        'must_change_password' => (bool) ($account['must_change_password'] ?? false),
                    ])->save();
                } else {
                    $user = User::create([
                        'email'                => $account['email'],
                        'name'                 => "{$account['title']} — {$school->name}",
                        'password'             => $account['password'],
                        'organization_id'      => $school->id,
                        'is_active'            => true,
                        'must_change_password' => (bool) ($account['must_change_password'] ?? false),
                    ]);
                }

                $this->assignRole($user, $roleName, $school->id);
                $userCount++;
            }
        }

        $deleted = User::query()
            ->whereNotIn('email', $allowedEmails)
            ->get()
            ->each(function (User $user): void {
                setPermissionsTeamId($user->organization_id);
                $user->syncRoles([]);
                $user->tokens()->delete();
                $user->delete();
            })
            ->count();

        setPermissionsTeamId(null);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure workflows exist for every school (including newly added ones).
        $this->call(WorkflowConfigurationSeeder::class);

        $domain = $payload['email_domain'] ?? 'armant-schools.edu';
        $this->command?->info("✓ {$userCount} Armant school accounts seeded (@{$domain}).");
        $this->command?->info("✓ Removed {$deleted} users outside the Armant schools list.");
        $this->command?->info('  Sample login: arm001.manager@armant-schools.edu');
    }

    private function ensureOrganization(
        OrganizationType $type,
        string $name,
        string $code,
        ?Organization $parent
    ): Organization {
        $org = Organization::withoutGlobalScopes()->updateOrCreate(
            ['code' => $code],
            [
                'parent_id' => $parent?->id,
                'type'      => $type,
                'name'      => $name,
                'depth'     => $type->depth(),
                'is_active' => true,
                'path'      => '/', // temporary until ID is known
            ]
        );

        $path = $parent ? $parent->path."{$org->id}/" : "/{$org->id}/";

        if ($org->path !== $path || $org->parent_id !== $parent?->id) {
            $org->updateQuietly([
                'path'      => $path,
                'parent_id' => $parent?->id,
                'depth'     => $type->depth(),
            ]);
        }

        return $org->fresh();
    }

    private function assignRole(User $user, string $roleName, int $organizationId): void
    {
        setPermissionsTeamId($organizationId);

        $role = Role::query()
            ->where('name', $roleName)
            ->where('organization_id', $organizationId)
            ->first();

        if ($role) {
            $user->syncRoles([$role]);
        } else {
            $this->command?->warn("Role [{$roleName}] missing for organization #{$organizationId}");
        }
    }
}
