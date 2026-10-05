<?php

namespace Database\Factories;

use App\Enums\ApprovalRule;
use App\Models\Organization;
use App\Models\WorkflowConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowConfigurationFactory extends Factory
{
    protected $model = WorkflowConfiguration::class;

    private static array $stages = [
        ['stage_name' => 'direct_manager',  'label' => 'رأي المدير المباشر',    'order' => 1],
        ['stage_name' => 'leaves_officer',  'label' => 'رأي مسؤول الإجازات',    'order' => 2],
        ['stage_name' => 'admin_manager',   'label' => 'رأي مدير الإدارة',      'order' => 3],
    ];

    public function definition(): array
    {
        $stage = $this->faker->randomElement(self::$stages);
        return [
            'organization_id' => Organization::factory(),
            'stage_name'      => $stage['stage_name'],
            'step_order'      => $stage['order'],
            'approval_rule'   => ApprovalRule::ANY,
            'required_role'   => null,
            'label'           => $stage['label'],
            'is_active'       => true,
        ];
    }

    /** Build a full 3-stage workflow for the given organization */
    public function forOrganization(Organization $org): static
    {
        return $this->state(fn() => ['organization_id' => $org->id]);
    }

    public function directManagerStage(Organization $org): static
    {
        return $this->state(fn() => [
            'organization_id' => $org->id,
            'stage_name'      => 'direct_manager',
            'step_order'      => 1,
            'approval_rule'   => ApprovalRule::ANY,
            'label'           => 'رأي المدير المباشر',
        ]);
    }

    public function leavesOfficerStage(Organization $org): static
    {
        return $this->state(fn() => [
            'organization_id' => $org->id,
            'stage_name'      => 'leaves_officer',
            'step_order'      => 2,
            'approval_rule'   => ApprovalRule::ALL,
            'label'           => 'رأي مسؤول الإجازات',
        ]);
    }

    public function adminManagerStage(Organization $org): static
    {
        return $this->state(fn() => [
            'organization_id' => $org->id,
            'stage_name'      => 'admin_manager',
            'step_order'      => 3,
            'approval_rule'   => ApprovalRule::ANY,
            'label'           => 'رأي مدير الإدارة',
        ]);
    }
}
