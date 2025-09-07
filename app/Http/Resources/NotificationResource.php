<?php

namespace App\Http\Resources;

use App\Enums\NotificationLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        if (! $this->resource) {
            return [];
        }

        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? 'system',
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'icon' => $data['icon'] ?? 'bell',
            'level' => $data['level'] ?? NotificationLevel::INFO->value,
            'action_url' => $data['action_url'] ?? null,
            'meta' => $data['meta'] ?? null,
            'read' => $this->read(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
