<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'type'      => $this->type->value,
            'type_label' => $this->type->label(),
            'code'      => $this->code,
            'depth'     => $this->depth,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
        ];
    }
}
