<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // Normalize phone to 62xxxxxxxxxx
        if ($this->phone) {
            $this->merge(['phone' => self::normalizePhone($this->phone)]);
        }
        // Auto-generate customer_code from prefix + next sequence
        if (! $this->customer_code && $this->code_prefix) {
            $this->merge(['customer_code' => $this->resolveNextCode($this->code_prefix)]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_code'  => ['required', 'string', 'max:20', 'unique:customers,customer_code', 'regex:/^[A-Z0-9\-]+$/'],
            'name'           => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'regex:/^62[0-9]{7,14}$/'],
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
            'customer_code.max'             => 'Kode pelanggan maksimal 20 karakter.',
            'name.required'                 => 'Nama pelanggan wajib diisi.',
            'phone.required'                => 'Nomor telepon wajib diisi.',
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

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // Convert 08xx → 628xx
        if (str_starts_with($digits, '08')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }
        return $digits;
    }

    private function resolveNextCode(string $prefix): string
    {
        // Done inside a lock to reduce (not eliminate) race; unique constraint is final guard
        $prefix = strtoupper(trim($prefix));
        $last   = Customer::where('customer_code', 'like', $prefix . '-%')
            ->orderByDesc('customer_code')
            ->lockForUpdate()
            ->value('customer_code');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $last  = end($parts);
            if (is_numeric($last)) {
                $next = ((int) $last) + 1;
            }
        }

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
