<?php

namespace Database\Factories;

use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        $pop = Pop::first() ?? Pop::factory()->create();
        $code = sprintf('JCN-%s-%04d', $pop->code, $counter++);

        // Random Indonesian phone number in 62xxx format
        $phone = '628' . $this->faker->numerify('#########');

        return [
            'pop_id'         => $pop->id,
            'customer_code'  => $code,
            'name'           => $this->faker->name(),
            'phone'          => $phone,
            'address'        => $this->faker->address(),
            'subscribed_at'  => $this->faker->dateTimeBetween('-3 years', '-6 months')->format('Y-m-d'),
            'package'        => $this->faker->randomElement(['10 Mbps', '20 Mbps', '30 Mbps', '50 Mbps', '100 Mbps']),
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ];
    }
}
