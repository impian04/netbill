<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_code'  => $this->customer_code,
            'name'           => $this->name,
            'address'        => $this->address,
            'phone'          => $this->phone,
            'package'        => $this->package,
            'billing_type'   => $this->billing_type,
            'service_status' => $this->service_status,
            'subscribed_at'  => $this->subscribed_at?->toDateString(),
        ];
    }
}
