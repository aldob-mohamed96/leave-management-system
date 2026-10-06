<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'deducts_balance'       => $this->deducts_balance,
            'yearly_entitlement'    => (float) $this->yearly_entitlement,
            'max_days_per_request'  => $this->max_days_per_request,
            'is_active'             => $this->is_active,
        ];
    }
}
