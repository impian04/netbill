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

        if ($this->has('coordinates')) {
            $coords = trim((string) $this->coordinates);
            if ($coords !== '') {
                $parts = preg_split('/[,\s]+/', $coords, 2);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $this->merge([
                        'latitude'  => (float) $parts[0],
                        'longitude' => (float) $parts[1],
                    ]);
                } else {
                    $this->merge([
                        'latitude'  => 'invalid',
                        'longitude' => 'invalid',
                    ]);
                }
            } else {
                $this->merge([
                    'latitude'  => null,
                    'longitude' => null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'name'           => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'regex:/^62[0-9]{8,13}$/'],
            'address'        => ['required', 'string', 'max:500'],
            'latitude'       => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude'      => ['nullable', 'numeric', 'between:94.0,141.5'],
            'subscribed_at'  => ['required', 'date', 'before_or_equal:today'],
            'package'        => ['required', 'string', 'max:50'],
            'billing_type'   => ['required', 'string', 'max:50'],
            'service_status' => ['required', Rule::in(Customer::SERVICE_STATUSES)],
            'odc_id'         => ['nullable', 'integer', 'exists:odcs,id'],
            'odp_id'         => ['nullable', 'integer', 'exists:odps,id'],
            'ktp_file'       => ['nullable', 'file', 'max:3072', 'mimes:jpeg,jpg,png,webp'],
        ];

        if (auth()->user()?->isSuperAdmin()) {
            $rules['pop_id'] = ['required', 'exists:pops,id'];
        } else {
            $rules['pop_id'] = ['nullable'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $popId = auth()->user()?->isSuperAdmin() ? (int) $this->input('pop_id') : (int) auth()->user()?->pop_id;
            $odcId = $this->input('odc_id') ? (int) $this->input('odc_id') : null;
            $odpId = $this->input('odp_id') ? (int) $this->input('odp_id') : null;

            if ($odcId && $popId) {
                $odcValid = \App\Models\Odc::where('id', $odcId)->where('pop_id', $popId)->exists();
                if (! $odcValid) {
                    $validator->errors()->add('odc_id', 'ODC yang dipilih bukan milik POP terkait.');
                }
            }

            if ($odpId) {
                if (! $odcId) {
                    $validator->errors()->add('odp_id', 'ODC wajib dipilih jika ODP dipilih.');
                } elseif ($popId) {
                    $odpValid = \App\Models\Odp::where('id', $odpId)
                        ->where('odc_id', $odcId)
                        ->where('pop_id', $popId)
                        ->exists();
                    if (! $odpValid) {
                        $validator->errors()->add('odp_id', 'ODP yang dipilih tidak sesuai dengan ODC atau POP terkait.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'pop_id.required'               => 'POP wajib dipilih.',
            'pop_id.exists'                 => 'POP yang dipilih tidak valid.',
            'name.required'                 => 'Nama pelanggan wajib diisi.',
            'phone.required'                => 'Nomor telepon wajib diisi.',
            'phone.regex'                   => 'Format nomor: 62xxxxxxxxxx (diawali 62, panjang 10-15 digit).',
            'address.required'              => 'Alamat wajib diisi.',
            'latitude.between'              => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5). Pastikan format: latitude, longitude.',
            'latitude.numeric'              => 'Format latitude tidak valid. Contoh: 0.5071',
            'longitude.between'             => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5). Pastikan format: latitude, longitude.',
            'longitude.numeric'             => 'Format longitude tidak valid. Contoh: 101.4478',
            'subscribed_at.required'        => 'Tanggal mulai berlangganan wajib diisi.',
            'subscribed_at.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'package.required'              => 'Paket wajib diisi.',
            'billing_type.required'         => 'Jenis tagihan wajib diisi.',
            'service_status.required'       => 'Status layanan wajib dipilih.',
            'service_status.in'             => 'Status layanan tidak valid.',
            'ktp_file.max'                  => 'Ukuran berkas KTP maksimal 3 MB.',
            'ktp_file.mimes'                => 'Format berkas KTP harus JPG, PNG, atau WebP.',
        ];
    }

    public static function normalizePhone(string $phone): string
    {
        $p = trim($phone);
        // Buang petik tunggal di awal
        $p = ltrim($p, "'");
        // Buang spasi, -, (, )
        $p = str_replace([' ', '-', '(', ')'], '', $p);

        // Jika diawali +62 atau +
        if (str_starts_with($p, '+62')) {
            $p = substr($p, 1);
        } elseif (str_starts_with($p, '+')) {
            $p = substr($p, 1);
        }

        // Ambil hanya digit
        $digits = preg_replace('/\D/', '', $p);

        // Convert 08xx → 628xx
        if (str_starts_with($digits, '08')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            // Jika diawali 8 (angka tanpa 0 di depan karena cell bertipe angka) anggap 08xx
            $digits = '62' . $digits;
        }

        return $digits;
    }
}
