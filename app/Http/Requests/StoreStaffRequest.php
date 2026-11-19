<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->phone) {
            $this->merge(['phone' => StoreCustomerRequest::normalizePhone($this->phone)]);
        }
    }

    public function rules(): array
    {
        $role = $this->role ? Role::tryFrom($this->role) : null;

        return [
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'regex:/^62[0-9]{8,13}$/'],
            'role'      => ['required', Rule::enum(Role::class)],
            'pop_id'    => [
                $role && $role->requiresPop() ? 'required' : 'nullable',
                'exists:pops,id',
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Nama staf wajib diisi.',
            'email.required'  => 'Email staf wajib diisi.',
            'email.email'     => 'Format email tidak valid.',
            'email.unique'    => 'Email sudah digunakan oleh staf lain.',
            'phone.regex'     => 'Format nomor telepon: 62xxxxxxxxxx.',
            'role.required'   => 'Peran staf wajib dipilih.',
            'pop_id.required' => 'Peran ini wajib terikat pada satu POP.',
            'pop_id.exists'   => 'POP yang dipilih tidak valid.',
        ];
    }
}

