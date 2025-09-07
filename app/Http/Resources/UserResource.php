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
        if (method_exists(get_class($this->resource), 'getAllPermissions')) {
            $data['all_permissions'] = $this->resource->getAllPermissions();
        }

        return $data;
    }
}
