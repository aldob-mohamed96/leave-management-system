<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // 1. Leave types & holidays (no org dependency)
        // ------------------------------------------------------------------
        $this->call([
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            EntitlementGradeSeeder::class,
        ]);

        $leaveTypes = LeaveType::all()->keyBy('code');

        // ------------------------------------------------------------------
        // 2. Organization hierarchy
        //    مديرية الأقصر → إدارة أرمنت + إدارة الطود → 3 مدارس كل إدارة
        // ------------------------------------------------------------------
        $directorate = $this->createOrg(
            type:  OrganizationType::DIRECTORATE,
            name:  'مديرية الأقصر التعليمية',
            code:  'DIR-LXR',
            parent: null,
        );

        $administrations = [
            $this->createOrg(OrganizationType::ADMINISTRATION, 'إدارة أرمنت التعليمية',  'ADM-ARM', $directorate),
            $this->createOrg(OrganizationType::ADMINISTRATION, 'إدارة الطود التعليمية',   'ADM-TWD', $directorate),
        ];

        $schoolNames = [
            'إدارة أرمنت' => [
                'مدرسة النيل الابتدائية بأرمنت',
                'مدرسة الأمل الإعدادية المشتركة',
                'مدرسة الشهيد الثانوية بنين',
            ],
            'إدارة الطود' => [
                'مدرسة الفجر الابتدائية بالطود',
                'مدرسة الوحدة الإعدادية بنات',
                'مدرسة القدس الثانوية المشتركة',
            ],
        ];

        $schools = [];
        $schoolCodes = ['SCH-001', 'SCH-002', 'SCH-003', 'SCH-004', 'SCH-005', 'SCH-006'];
        $codeIdx = 0;

        foreach ($administrations as $idx => $adm) {
            $admLabel = $idx === 0 ? 'إدارة أرمنت' : 'إدارة الطود';
            foreach ($schoolNames[$admLabel] as $schoolName) {
                $schools[] = $this->createOrg(
                    OrganizationType::SCHOOL,
                    $schoolName,
                    $schoolCodes[$codeIdx++],
                    $adm
                );
            }
        }

        $this->command->info('✓ Organization hierarchy created: 1 directorate, 2 administrations, 6 schools.');

        // ------------------------------------------------------------------
        // 3. Roles & permissions (needs orgs to exist first)
        // ------------------------------------------------------------------
        $this->call(RoleAndPermissionSeeder::class);

        // ------------------------------------------------------------------
        // 4. Demo system users skipped — ArmantSchoolsSeeder is the only user source.
        // ------------------------------------------------------------------

        // ------------------------------------------------------------------
        // 5. Employees (3–5 per school = ~24 total)
        // ------------------------------------------------------------------
        $employees = [];
        foreach ($schools as $school) {
            $count = rand(3, 5);
            for ($i = 0; $i < $count; $i++) {
                $employees[] = Employee::factory()
                    ->inOrganization($school)
                    ->create();
            }
        }
        $this->command->info('✓ ' . count($employees) . ' employees seeded across 6 schools.');

        // ------------------------------------------------------------------
        // 6. Opening leave balances for current year
        //    Regular leave entitlement is calculated from entitlement_grade
        // ------------------------------------------------------------------
        $year = now()->year;
        $balanceCount = 0;

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                // For regular leave, use the grade-based entitlement
                $entitled = match($leaveType->code) {
                    'regular' => $employee->regularLeaveEntitlement(),
                    default   => $leaveType->yearly_entitlement,
                };

                // Only create balance records for leave types that have a yearly entitlement
                if ($entitled == 0 && ! $leaveType->deducts_balance) {
                    // Event-based leaves (maternity, hajj, etc.) don't need annual balance rows
                    // They are tracked per-request, not per-year
                    continue;
                }

                LeaveBalance::create([
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'year'          => $year,
                    'entitled'      => $entitled,
                    'carried_over'  => ($leaveType->deducts_balance && $leaveType->code !== 'casual')
                        ? rand(0, 5)
                        : 0,
                    'used'          => 0,
                ]);
                $balanceCount++;
            }
        }
        $this->command->info("✓ {$balanceCount} leave balance records seeded for year {$year}.");

        // ------------------------------------------------------------------
        // 7. Workflow configurations (needs schools to exist)
        // ------------------------------------------------------------------
        $this->call(WorkflowConfigurationSeeder::class);

        // ------------------------------------------------------------------
        // 8. Real Armant schools + accounts (63 schools)
        // ------------------------------------------------------------------
        $this->call(ArmantSchoolsSeeder::class);

        // ------------------------------------------------------------------
        // 9. Super admin account
        // ------------------------------------------------------------------
        $this->call(SuperAdminSeeder::class);

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  Phase 1 seed complete. Summary:');
        $this->command->info('  • Leave types : ' . $leaveTypes->count());
        $this->command->info('  • Holidays    : ' . \App\Models\Holiday::count());
        $this->command->info('  • Orgs        : ' . Organization::count());
        $this->command->info('  • Users       : ' . User::count());
        $this->command->info('  • Employees   : ' . count($employees));
        $this->command->info('  • Balances    : ' . $balanceCount);
        $this->command->info('═══════════════════════════════════════════════');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createOrg(
        OrganizationType $type,
        string $name,
        string $code,
        ?Organization $parent
    ): Organization {
        $org = Organization::create([
            'parent_id' => $parent?->id,
            'type'      => $type,
            'name'      => $name,
            'code'      => $code,
            'path'      => '/',   // will be updated below
            'depth'     => $type->depth(),
            'is_active' => true,
        ]);

        // Build materialized path after we have the ID
        $path = $parent ? $parent->path . "{$org->id}/" : "/{$org->id}/";
        $org->updateQuietly(['path' => $path]);

        return $org->fresh();
    }

    private function seedUsers(
        Organization $directorate,
        array $administrations,
        array $schools
    ): void {
        // Super-admin (no org)
        User::updateOrCreate(
            ['email' => 'admin@luxor-edu.gov.eg'],
            [
                'name'            => 'مدير النظام',
                'password'        => Hash::make('password'),
                'organization_id' => null,
                'is_active'       => true,
            ]
        );

        // Directorate director
        $dirUser = User::updateOrCreate(
            ['email' => 'director@luxor-edu.gov.eg'],
            [
                'name'            => 'مدير المديرية',
                'password'        => Hash::make('password'),
                'organization_id' => $directorate->id,
                'is_active'       => true,
            ]
        );
        $this->assignRole($dirUser, 'مدير المديرية', $directorate->id);

        // Administration managers and leaves officers
        foreach ($administrations as $idx => $adm) {
            $n = $idx + 1;

            $admManager = User::updateOrCreate(
                ['email' => "admin-manager-{$n}@luxor-edu.gov.eg"],
                [
                    'name'            => "مدير إدارة {$adm->name}",
                    'password'        => Hash::make('password'),
                    'organization_id' => $adm->id,
                    'is_active'       => true,
                ]
            );
            $this->assignRole($admManager, 'مدير الإدارة', $adm->id);

            $leavesOfficer = User::updateOrCreate(
                ['email' => "leaves-officer-{$n}@luxor-edu.gov.eg"],
                [
                    'name'            => "مسؤول إجازات {$adm->name}",
                    'password'        => Hash::make('password'),
                    'organization_id' => $adm->id,
                    'is_active'       => true,
                ]
            );
            $this->assignRole($leavesOfficer, 'مسؤول الإجازات', $adm->id);
        }

        // Per-school: one manager + one employee user
        foreach ($schools as $idx => $school) {
            $n = $idx + 1;

            $schoolManager = User::updateOrCreate(
                ['email' => "school-manager-{$n}@luxor-edu.gov.eg"],
                [
                    'name'            => "مدير {$school->name}",
                    'password'        => Hash::make('password'),
                    'organization_id' => $school->id,
                    'is_active'       => true,
                ]
            );
            $this->assignRole($schoolManager, 'مدير مدرسة', $school->id);

            $schoolEmployee = User::updateOrCreate(
                ['email' => "employee-{$n}@luxor-edu.gov.eg"],
                [
                    'name'            => "موظف مدرسة {$n}",
                    'password'        => Hash::make('password'),
                    'organization_id' => $school->id,
                    'is_active'       => true,
                ]
            );
            $this->assignRole($schoolEmployee, 'موظف مدرسة', $school->id);
        }

        $this->command->info('✓ ' . User::count() . ' system users seeded with roles.');
    }

    private function assignRole(User $user, string $roleName, int $organizationId): void
    {
        setPermissionsTeamId($organizationId);
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)
            ->where('organization_id', $organizationId)
            ->first();

        if ($role) {
            $user->assignRole($role);
        }

        setPermissionsTeamId(null);
    }
}
