<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketingApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (! $user || $user->role !== \App\Enums\Role::MARKETING) {
            return false;
        }

        $application = $this->route('application');
        if (! $application) {
            return false;
        }

        // Must be own application
        if ((int) $application->marketing_user_id !== (int) $user->id) {
            return false;
        }

        // Only SUBMITTED or NEEDS_REVISION can be updated
        if (! in_array($application->status, [ApplicationStatus::SUBMITTED, ApplicationStatus::NEEDS_REVISION], true)) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('phone')) {
            $merge['phone'] = StoreCustomerRequest::normalizePhone($this->input('phone'));
        }

        if ($this->has('coordinates') && is_string($this->input('coordinates'))) {
            $raw = trim($this->input('coordinates'));
            if ($raw === '') {
                $merge['latitude'] = null;
                $merge['longitude'] = null;
            } elseif (str_contains($raw, ',')) {
                $parts = explode(',', $raw, 2);
                $merge['latitude'] = trim($parts[0]);
                $merge['longitude'] = trim($parts[1]);
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'regex:/^62[0-9]{8,13}$/'],
            'address'      => ['required', 'string', 'max:1000'],
            'latitude'     => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude'    => ['nullable', 'numeric', 'between:94.0,141.5'],
            'package'      => ['required', 'string', 'max:100'],
            'billing_type' => ['nullable', 'string', 'in:Bulanan,Tahunan,Pascabayar,Prabayar'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'ktp_file'     => ['nullable', 'file', 'max:3072', 'mimes:jpeg,jpg,png,webp'],
            'delete_ktp'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Nama calon pelanggan wajib diisi.',
            'phone.required'    => 'Nomor telepon wajib diisi.',
            'phone.regex'       => 'Nomor telepon harus berformat Indonesia yang valid (contoh: 08123456789 atau 628123456789).',
            'address.required'  => 'Alamat pemasangan wajib diisi.',
            'latitude.between'  => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5). Pastikan format: latitude, longitude.',
            'longitude.between' => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5). Pastikan format: latitude, longitude.',
            'package.required'  => 'Paket layanan wajib dipilih atau diisi.',
            'ktp_file.max'      => 'Ukuran berkas KTP maksimal 3 MB.',
            'ktp_file.mimes'    => 'Berkas KTP harus berformat JPG, JPEG, PNG, atau WEBP.',
        ];
    }
}

