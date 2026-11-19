<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\MarketingFee;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingFeeFactory extends Factory
{
    protected $model = MarketingFee::class;

    public function definition(): array
    {
        return [
            'marketing_user_id' => User::factory()->state(['role' => \App\Enums\Role::MARKETING]),
            'customer_id'       => Customer::factory(),
            'pop_id'            => Pop::factory(),
            'amount'            => 50000,
            'status'            => 'PENDING',
            'eligible_at'       => now(),
        ];
    }
}
