<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->phone) {
            $this->merge(['phone' => StoreCustomerRequest::normalizePhone($this->phone)]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_code'  => [
                'required', 'string', 'max:20',
                Rule::unique('customers', 'customer_code')->ignore($this->customer),
                'regex:/^[A-Z0-9\-]+$/',
            ],
            'name'           => ['required', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'regex:/^62[0-9]{7,14}$/'],
            'address'        => ['required', 'string', 'max:500'],
            'subscribed_at'  => ['required', 'date', 'before_or_equal:today'],
            'package'        => ['required', 'string', 'max:50'],
            'billing_type'   => ['required', 'string', 'max:50'],
            'service_status' => ['required', Rule::in(Customer::SERVICE_STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_code.required'        => 'Kode pelanggan wajib diisi.',
            'customer_code.unique'          => 'Kode pelanggan sudah digunakan.',
            'customer_code.regex'           => 'Kode hanya boleh huruf kapital, angka, dan tanda hubung.',
            'name.required'                 => 'Nama wajib diisi.',
            'phone.regex'                   => 'Format nomor: 62xxxxxxxxxx (diawali 62).',
            'address.required'              => 'Alamat wajib diisi.',
            'subscribed_at.required'        => 'Tanggal mulai berlangganan wajib diisi.',
            'subscribed_at.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'package.required'              => 'Paket wajib diisi.',
            'billing_type.required'         => 'Jenis tagihan wajib diisi.',
            'service_status.required'       => 'Status layanan wajib dipilih.',
            'service_status.in'             => 'Status layanan tidak valid.',
        ];
    }
}
