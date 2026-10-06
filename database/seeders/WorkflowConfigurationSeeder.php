<?php

namespace Database\Seeders;

use App\Enums\ApprovalRule;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\WorkflowConfiguration;
use Illuminate\Database\Seeder;

class WorkflowConfigurationSeeder extends Seeder
{
    /**
     * مسار الاعتماد للمدرسة:
     * 1) مدير المدرسة (توقيع إلكتروني)
     * 2) مسؤول الإجازات بالإدارة
     * 3) مدير الإدارة
     */
    private const SCHOOL_STAGES = [
        [
            'stage_name'    => 'school_principal',
            'step_order'    => 1,
            'approval_rule' => ApprovalRule::ANY,
            'required_role' => 'مدير مدرسة',
            'label'         => 'اعتماد مدير المدرسة',
        ],
        [
            'stage_name'    => 'leaves_officer',
            'step_order'    => 2,
            'approval_rule' => ApprovalRule::ANY,
            'required_role' => 'مسؤول الإجازات',
            'label'         => 'رأي مسؤول الإجازات',
        ],
        [
            'stage_name'    => 'admin_manager',
            'step_order'    => 3,
            'approval_rule' => ApprovalRule::ANY,
            'required_role' => 'مدير الإدارة',
            'label'         => 'رأي مدير الإدارة',
        ],
    ];

    public function run(): void
    {
        $schools = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::SCHOOL->value)
            ->get();

        foreach ($schools as $school) {
            // Remove legacy direct_manager stage if present.
            WorkflowConfiguration::withoutGlobalScopes()
                ->where('organization_id', $school->id)
                ->where('stage_name', 'direct_manager')
                ->delete();

            WorkflowConfiguration::withoutGlobalScopes()
                ->where('organization_id', $school->id)
                ->where('step_order', '>', count(self::SCHOOL_STAGES))
                ->delete();

            foreach (self::SCHOOL_STAGES as $stage) {
                WorkflowConfiguration::withoutGlobalScopes()->updateOrCreate(
                    [
                        'organization_id' => $school->id,
                        'step_order'      => $stage['step_order'],
                    ],
                    [
                        'stage_name'    => $stage['stage_name'],
                        'approval_rule' => $stage['approval_rule'],
                        'required_role' => $stage['required_role'],
                        'label'         => $stage['label'],
                        'is_active'     => true,
                    ]
                );
            }
        }

        $this->command->info("✓ School workflow configured for {$schools->count()} schools (principal → leaves officer → admin manager).");
    }
}
