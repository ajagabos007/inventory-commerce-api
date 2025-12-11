<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryReportResource extends JsonResource
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
            'product' => [
                'id' => $this->productVariant?->product?->id,
                'name' => $this->productVariant?->product?->name,
                'image' => $this->productVariant->product->image,
            ],
            'variant' => [
                'id' => $this->productVariant?->id,
                'name' => $this->productVariant?->name,
                'sku' => $this->productVariant?->sku,
                'image' => $this->productVariant->image,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ],
            'quantity' => (int) $this->quantity,
            'status' => $this->status,
            'serial_number' => $this->serial_number,
            'batch_number' => $this->batch_number,
            'stock_value' => (float) ($this->quantity * ($this->productVariant?->cost_price ?? 0)),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
