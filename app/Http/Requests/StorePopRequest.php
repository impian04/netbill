<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->code) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'regex:/^[A-Z]{2,4}$/', 'unique:pops,code'],
            'name'      => ['required', 'string', 'max:100'],
            'va_code'   => ['nullable', 'string', 'regex:/^\d{2,3}$/', 'unique:pops,va_code'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Kode POP wajib diisi.',
            'code.regex'     => 'Kode POP harus berupa 2-4 huruf kapital (mis. KTU, EP, BKN).',
            'code.unique'    => 'Kode POP sudah digunakan.',
            'name.required'  => 'Nama POP wajib diisi.',
            'va_code.regex'  => 'Kode VA harus berupa 2-3 digit angka.',
            'va_code.unique' => 'Kode VA sudah digunakan oleh POP lain.',
        ];
    }
}

