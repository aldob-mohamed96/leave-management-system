<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'step_order' => $this->step_order,
            'stage'      => $this->stage,
            'status'     => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'acted_by'   => $this->actedBy?->name,
            'acted_at'   => $this->acted_at?->toDateTimeString(),
            'note'       => $this->note,
        ];
    }
}
