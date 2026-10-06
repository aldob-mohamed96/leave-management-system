<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'leave_type'   => LeaveTypeResource::make($this->whenLoaded('leaveType')),
            'year'         => $this->year,
            'entitled'     => (float) $this->entitled,
            'carried_over' => (float) $this->carried_over,
            'used'         => (float) $this->used,
            'remaining'    => (float) $this->remaining,
        ];
    }
}
