<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NocCustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Hanya memuat informasi operasional yang diizinkan untuk NOC.
     * Tidak memuat KTP, fee, data marketing, atau kode aktivasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customer_code'  => $this->customer_code,
            'name'           => $this->name,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'package'        => $this->package,
            'service_status' => $this->service_status,
            'pop'            => $this->pop ? [
                'id'   => $this->pop->id,
                'code' => $this->pop->code,
                'name' => $this->pop->name,
            ] : null,
            'odc'            => $this->odc ? [
                'id'   => $this->odc->id,
                'name' => $this->odc->name,
            ] : null,
            'odp'            => $this->odp ? [
                'id'   => $this->odp->id,
                'name' => $this->odp->name,
            ] : null,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'recent_invoices' => $this->relationLoaded('invoices')
                ? $this->invoices->take(6)->map(fn ($inv) => [
                    'period' => is_string($inv->period) ? substr($inv->period, 0, 7) : $inv->period?->format('Y-m'),
                    'amount' => (int) ($inv->deferred_amount ?? 0),
                    'status' => $inv->status,
                ])
                : [],
        ];
    }
}

