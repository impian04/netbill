<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketUpdateFactory extends Factory
{
    protected $model = TicketUpdate::class;

    public function definition(): array
    {
        return [
            'ticket_id'   => Ticket::factory(),
            'user_id'     => User::factory(),
            'type'        => TicketUpdateType::NOTE,
            'body'        => fake()->sentence(),
            'from_status' => null,
            'to_status'   => null,
            'created_at'  => now(),
        ];
    }
}

