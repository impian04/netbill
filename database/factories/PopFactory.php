<?php

namespace Database\Factories;

use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

class PopFactory extends Factory
{
    protected $model = Pop::class;

    public function definition(): array
    {
        static $counter = 1;
        $codes = ['KTU', 'EP', 'BKN', 'PWK', 'DMI', 'RGT'];
        $code = $codes[($counter - 1) % count($codes)] . ($counter > count($codes) ? ($counter - count($codes)) : '');
        $counter++;

        return [
            'code'                 => substr(strtoupper($code), 0, 4),
            'name'                 => 'POP ' . strtoupper($code),
            'va_code'              => str_pad((string) fake()->unique()->numberBetween(10, 99), 2, '0', STR_PAD_LEFT),
            'is_active'            => true,
            'last_customer_number' => 0,
        ];
    }
}

