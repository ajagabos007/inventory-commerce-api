<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'cashier' => [
                'id' => $this->cashier?->id,
                'staff_no' => $this->cashier?->staff_no,
                'name' => $this->cashier?->user?->first_name . ' ' . $this->cashier?->user?->last_name,
            ],
            'payment_method' => $this->payment_method,
            'subtotal_price' => (float) $this->subtotal_price,
            'tax' => (float) ($this->tax ?? 0),
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'total_price' => (float) $this->total_price,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
