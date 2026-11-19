<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'pop_id'    => Pop::factory(),
            'name'      => $this->faker->words(3, true),
            'unit'      => $this->faker->randomElement(Material::UNITS),
            'is_active' => true,
        ];
    }
}

