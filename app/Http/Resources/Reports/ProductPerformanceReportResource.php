<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPerformanceReportResource extends JsonResource
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
            'slug' => $this->slug,
            'display_price' => $this->display_price,
            'total_sold' => (int) ($this->total_sold ?? $this->popular_total_sold ?? $this->trending_total_sold ?? $this->total_top_selling_sold ?? 0),
            'available_quantity' => (int) $this->available_quantity,
            'created_at' => $this->created_at?->toISOString(),
            'image' => $this->image,
        ];
    }
}
