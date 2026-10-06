<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'roles'        => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'is_active'    => $this->is_active,
        ];
    }
}
