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
     * Default 3-stage workflow applied to each school.
     * Admin-level (administration) gets its own 1-stage final approval config.
     */
    private const SCHOOL_STAGES = [
        [
            'stage_name'    => 'direct_manager',
            'step_order'    => 1,
            'approval_rule' => ApprovalRule::ANY,
            'required_role' => 'مدير مدرسة',
            'label'         => 'رأي المدير المباشر',
        ],
        [
            'stage_name'    => 'leaves_officer',
            'step_order'    => 2,
            'approval_rule' => ApprovalRule::ALL,
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
        // Seed 3-stage workflow for every school
        $schools = Organization::where('type', OrganizationType::SCHOOL->value)->get();

        foreach ($schools as $school) {
            foreach (self::SCHOOL_STAGES as $stage) {
                WorkflowConfiguration::updateOrCreate(
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

        $this->command->info("✓ 3-stage workflow configured for {$schools->count()} schools.");
    }
}
