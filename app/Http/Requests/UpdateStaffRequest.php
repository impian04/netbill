<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
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
        $staff = $this->route('staff');
        $targetUser = $staff instanceof User ? $staff : User::findOrFail($staff);

        $roleVal = $this->has('role') ? $this->input('role') : $targetUser->role->value;
        $role = Role::tryFrom($roleVal);

        return [
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'phone'     => ['nullable', 'string', 'regex:/^62[0-9]{8,13}$/'],
            'role'      => ['sometimes', 'required', Rule::enum(Role::class)],
            'pop_id'    => [
                'sometimes',
                $role && $role->requiresPop() ? 'required' : 'nullable',
                'exists:pops,id',
            ],
            'is_active' => ['sometimes', 'nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $staff = $this->route('staff');
            $targetUser = $staff instanceof User ? $staff : User::find($staff);

            if (! $targetUser) {
                return;
            }

            $currentAuth = auth()->user();

            // Field yang tidak dikirim berarti tidak berubah
            $newIsActive = $this->has('is_active') ? $this->boolean('is_active') : $targetUser->is_active;
            $newRoleVal  = $this->has('role') ? $this->role : $targetUser->role->value;
            $newRole     = Role::tryFrom($newRoleVal);

            $isDeactivating       = $targetUser->is_active && ! $newIsActive;
            $isDemotingSuperAdmin = $targetUser->isSuperAdmin() && ($newRole !== Role::SUPER_ADMIN);

            // Rule: super_admin cannot deactivate or demote themselves
            if ($currentAuth && $currentAuth->id === $targetUser->id) {
                if ($isDeactivating) {
                    $validator->errors()->add('is_active', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
                }
                if ($isDemotingSuperAdmin) {
                    $validator->errors()->add('role', 'Anda tidak dapat menurunkan peran akun Anda sendiri.');
                }
            }

            // Rule: there must always be at least one active super_admin
            if ($targetUser->isSuperAdmin() && ($isDeactivating || $isDemotingSuperAdmin)) {
                $otherActiveSuperAdmins = User::where('role', Role::SUPER_ADMIN->value)
                    ->where('is_active', true)
                    ->where('id', '!=', $targetUser->id)
                    ->count();

                if ($otherActiveSuperAdmins < 1) {
                    $validator->errors()->add('role', 'Tidak dapat mengubah atau menonaktifkan: harus ada minimal satu Super Admin yang aktif.');
                }
            }
        });
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

