<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePopRequest extends FormRequest
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
        $pop = $this->route('pop');

        return [
            'code'      => [
                'required',
                'string',
                'regex:/^[A-Z]{2,4}$/',
                Rule::unique('pops', 'code')->ignore($pop->id),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'va_code'   => ['nullable', 'string', 'regex:/^\d{2,3}$/', Rule::unique('pops', 'va_code')->ignore($pop->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pop = $this->route('pop');
            if ($pop && $pop->customers()->exists() && $this->code !== $pop->code) {
                $validator->errors()->add('code', 'Kode POP tidak dapat diubah karena sudah memiliki pelanggan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Kode POP wajib diisi.',
            'code.regex'     => 'Kode POP harus berupa 2-4 huruf kapital.',
            'code.unique'    => 'Kode POP sudah digunakan.',
            'name.required'  => 'Nama POP wajib diisi.',
            'va_code.regex'  => 'Kode VA harus berupa 2-3 digit angka.',
            'va_code.unique' => 'Kode VA sudah digunakan oleh POP lain.',
        ];
    }
}

