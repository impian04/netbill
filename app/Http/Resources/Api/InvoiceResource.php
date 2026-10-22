<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'invoice_number'  => $this->invoice_number,
            'period'          => $this->period?->format('Y-m'),
            'due_date'        => $this->due_date?->toDateString(),
            'amount'          => (int) $this->deferred_amount,
            'status'          => $this->status,
            'paid_at'         => $this->paid_at?->toIso8601String(),
            // Customer denormalized for invoice detail display
            'customer_code'   => $this->whenLoaded('customer', fn () => $this->customer->customer_code),
            'customer_name'   => $this->whenLoaded('customer', fn () => $this->customer->name),
            'address'         => $this->whenLoaded('customer', fn () => $this->customer->address),
            'package'         => $this->whenLoaded('customer', fn () => $this->customer->package),
        ];
    }
}
