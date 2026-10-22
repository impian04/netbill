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
            'paid_at'           => $this->paid_at?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            // Customer denormalized info
            'customer_code'     => $this->relationLoaded('customer') ? $this->customer?->customer_code : null,
            'customer_name'     => $this->relationLoaded('customer') ? $this->customer?->name : null,
            'address'           => $this->relationLoaded('customer') ? $this->customer?->address : null,
            'package'           => $this->relationLoaded('customer') ? $this->customer?->package : null,
        ];
    }
}
