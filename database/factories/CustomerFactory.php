<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        $prefixes = ['SLX-GS', 'SLX-KT', 'SLX-BT', 'SLX-TG'];
        $prefix   = $this->faker->randomElement($prefixes);
        $code     = $prefix . '-' . str_pad($counter++, 5, '0', STR_PAD_LEFT);

        // Random Indonesian phone number in 62xxx format
        $phone = '628' . $this->faker->numerify('#########');

        return [
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
