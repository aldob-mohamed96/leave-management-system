<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'number'           => $this->number,
            'employee'         => EmployeeResource::make($this->whenLoaded('employee')),
            'leave_type'       => LeaveTypeResource::make($this->whenLoaded('leaveType')),
            'organization'     => OrganizationResource::make($this->whenLoaded('organization')),
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
            'days'             => (int) $this->days,
            'written_at'       => $this->written_at?->toDateString(),
            'reason'           => $this->reason,
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'status_color'     => $this->status->color(),
            'current_stage'    => $this->current_stage,
            'rejection_reason' => $this->rejection_reason,
            'balance_snapshot' => [
                'entitled'  => $this->balance_entitled !== null ? (int) $this->balance_entitled : null,
                'used'      => $this->balance_used !== null ? (int) $this->balance_used : null,
                'remaining' => $this->balance_remaining !== null ? (int) $this->balance_remaining : null,
            ],
            'steps'            => LeaveRequestStepResource::collection($this->whenLoaded('steps')),
            'created_by'       => $this->createdBy?->name,
            'submitted_at'     => $this->submitted_at?->toDateTimeString(),
            'decided_at'       => $this->decided_at?->toDateTimeString(),
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
