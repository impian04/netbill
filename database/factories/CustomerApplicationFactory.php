<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\CustomerApplication;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerApplication>
 */
class CustomerApplicationFactory extends Factory
{
    protected $model = CustomerApplication::class;

    public function definition(): array
    {
        return [
            'pop_id'            => Pop::factory(),
            'marketing_user_id' => User::factory()->state(['role' => \App\Enums\Role::MARKETING]),
            'name'              => fake()->name(),
            'phone'             => '628' . fake()->numerify('#########'),
            'address'           => fake()->address(),
            'latitude'          => 0.5071000,
            'longitude'         => 101.4478000,
            'package'           => '20 Mbps',
            'billing_type'      => 'Bulanan',
            'notes'             => fake()->sentence(),
            'status'            => ApplicationStatus::SUBMITTED,
        ];
    }
}
