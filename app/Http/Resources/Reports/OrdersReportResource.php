<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdersReportResource extends JsonResource
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
            'reference' => $this->reference,
            'customer' => [
                'name' => $this->full_name,
                'email' => $this->email,
                'phone' => $this->phone_number,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ],
            'status' => $this->status,
            'delivery_method' => $this->delivery_method,
            'payment_method' => $this->payment_method,
            'subtotal_price' => (float) $this->subtotal_price,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'total_price' => (float) $this->total_price,
            'items_count' => $this->items?->count() ?? 0,
            'items' => $this->items,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
