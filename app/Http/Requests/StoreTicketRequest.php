<?php

namespace App\Http\Requests;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isSuperAdmin() || $user->isNoc());
    }

    public function rules(): array
    {
        return [
            'customer_id'    => ['nullable', 'integer', 'exists:customers,id'],
            'pop_id'         => ['nullable', 'integer', 'exists:pops,id', 'required_without:customer_id'],
            'reporter_name'  => ['nullable', 'string', 'max:120', 'required_without:customer_id'],
            'reporter_phone' => ['nullable', 'string', 'max:30', 'required_without:customer_id'],
            'category'       => ['required', Rule::enum(TicketCategory::class)],
            'channel'        => ['required', Rule::enum(TicketChannel::class)],
            'priority'       => ['required', Rule::enum(TicketPriority::class)],
            'subject'        => ['required', 'string', 'max:120'],
            'description'    => ['required', 'string', 'max:2000'],
            'assigned_to'    => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pop_id.required_without'         => 'POP wajib dipilih jika pelapor bukan pelanggan.',
            'reporter_name.required_without'  => 'Nama pelapor wajib diisi jika pelapor bukan pelanggan.',
            'reporter_phone.required_without' => 'Nomor telepon pelapor wajib diisi jika pelapor bukan pelanggan.',
            'subject.max'                     => 'Subjek maksimal 120 karakter.',
            'description.max'                 => 'Deskripsi maksimal 2000 karakter.',
        ];
    }
}

