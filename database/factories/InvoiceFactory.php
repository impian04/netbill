<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'     => Customer::factory(),
            'period'          => now()->startOfMonth()->format('Y-m-d'),
            'deferred_amount' => $this->faker->randomElement([110000, 165000, 220000, 275000, 330000]),
            'status'          => $this->faker->randomElement(['PAID', 'UNPAID']),
        ];
    }
}
