<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $period = $this->period;
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            try {
                $this->merge([
                    'period' => Carbon::createFromFormat('Y-m', $period)->startOfMonth()->format('Y-m-d'),
                ]);
            } catch (\Exception) {
                // validation will catch malformed date
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id ?? $this->input('customer_id');

        return [
            'customer_id'       => $this->route('customer') ? ['nullable'] : ['required', 'exists:customers,id'],
            'period'            => ['required', 'date', Rule::unique('invoices')->where('customer_id', $customerId)],
            'deferred_amount'   => ['required', 'integer', 'min:0', 'max:999999999999'],
            'status'            => ['required', Rule::in(['PAID', 'UNPAID'])],
            'due_date'          => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $customer = $this->route('customer')
                ?? ($this->customer_id ? \App\Models\Customer::find($this->customer_id) : null);

            if ($customer && $customer->service_status === 'PENDING') {
                $validator->errors()->add('customer_id', 'Tagihan tidak dapat dibuat untuk pelanggan berstatus PENDING (menunggu pemasangan).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required'     => 'Pelanggan wajib dipilih.',
            'customer_id.exists'       => 'Pelanggan tidak valid.',
            'period.required'          => 'Periode wajib diisi.',
            'period.unique'            => 'Tagihan untuk periode ini sudah ada.',
            'period.date'              => 'Format periode tidak valid.',
            'deferred_amount.required' => 'Nominal ditangguhkan wajib diisi.',
            'deferred_amount.integer'  => 'Nominal harus berupa angka bulat.',
            'deferred_amount.min'      => 'Nominal tidak boleh negatif.',
            'status.required'          => 'Status wajib dipilih.',
            'status.in'                => 'Status tidak valid.',
        ];
    }
}
