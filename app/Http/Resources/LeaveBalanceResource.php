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
            'entitled'     => (int) $this->entitled,
            'carried_over' => (int) $this->carried_over,
            'used'         => (int) $this->used,
            'remaining'    => (int) $this->remaining,
        ];
    }
}
