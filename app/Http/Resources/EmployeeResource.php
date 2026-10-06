<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'employee_code'     => $this->employee_code,
            'full_name'         => $this->full_name,
            'job_title'         => $this->job_title,
            'grade'             => $this->grade,
            'entitlement_grade' => $this->entitlement_grade?->value,
            'entitlement_grade_label' => $this->entitlement_grade?->label(),
            'organization'      => OrganizationResource::make($this->whenLoaded('organization')),
            'is_active'         => $this->is_active,
            'birth_date'        => $this->birth_date?->toDateString(),
            'hire_date'         => $this->hire_date?->toDateString(),
            'work_start_date'   => $this->work_start_date?->toDateString(),
            'phone'             => $this->phone,
        ];
    }
}
