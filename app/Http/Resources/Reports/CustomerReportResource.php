<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'country' => $this->country,
            'city' => $this->city,
            'purchase_count' => (int) ($this->purchase_count ?? 0),
            'total_spent' => (float) ($this->total_spent ?? 0),
            'average_order_value' => (float) ($this->average_order_value ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
