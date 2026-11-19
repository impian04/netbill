<?php

namespace Database\Factories;

use App\Models\MarketingProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingProfileFactory extends Factory
{
    protected $model = MarketingProfile::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory()->state(['role' => \App\Enums\Role::MARKETING]),
            'payout_method'  => 'BANK',
            'provider_name'  => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => fake()->name(),
            'notes'          => fake()->sentence(),
        ];
    }
}
