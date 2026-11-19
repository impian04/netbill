<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_number'     => 'TKT-' . now()->format('Ym') . '-' . str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id'       => null,
            'pop_id'            => Pop::factory(),
            'reporter_name'     => fake()->name(),
            'reporter_phone'    => '08' . fake()->numerify('##########'),
            'category'          => fake()->randomElement(TicketCategory::cases()),
            'channel'           => fake()->randomElement(TicketChannel::cases()),
            'priority'          => TicketPriority::NORMAL,
            'subject'           => fake()->sentence(4),
            'description'       => fake()->paragraph(),
            'status'            => TicketStatus::OPEN,
            'created_by'        => User::factory(),
            'assigned_to'       => null,
            'opened_at'         => now(),
            'first_response_at' => null,
            'resolved_at'       => null,
            'closed_at'         => null,
            'resolution_note'   => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => [
            'customer_id'    => $customer->id,
            'pop_id'         => $customer->pop_id,
            'reporter_name'  => $customer->name,
            'reporter_phone' => $customer->phone ?? '081234567890',
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::OPEN,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status'            => TicketStatus::IN_PROGRESS,
            'first_response_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status'            => TicketStatus::RESOLVED,
            'first_response_at' => now()->subHours(2),
            'resolved_at'       => now(),
            'resolution_note'   => 'Kendala telah diselesaikan dengan baik.',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status'            => TicketStatus::CLOSED,
            'first_response_at' => now()->subDays(1),
            'resolved_at'       => now()->subHours(5),
            'closed_at'         => now(),
            'resolution_note'   => 'Tiket ditutup setelah konfirmasi pelanggan.',
        ]);
    }
}

