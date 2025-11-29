<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        
        if ($this->relationLoaded('permissions') && $this->relationLoaded('roles')) {
            $data['all_permissions'] = $this->resource->permissions->merge($this->resource->getPermissionsViaRoles())->pluck('name')->unique()->values();
        } 

        return $data;
    }
}
