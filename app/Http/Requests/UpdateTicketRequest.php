<?php

namespace App\Http\Requests;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isSuperAdmin() || $user->isNoc());
    }

    public function rules(): array
    {
        return [
            'category'    => ['required', Rule::enum(TicketCategory::class)],
            'channel'     => ['required', Rule::enum(TicketChannel::class)],
            'priority'    => ['required', Rule::enum(TicketPriority::class)],
            'subject'     => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}

