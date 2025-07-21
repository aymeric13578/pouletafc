<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Merchand>
 */
class MerchandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
        
        'ref'=>fake()->unique()->numberBetween(10000, 1000000),
        'contrat'=>fake()->randomElement(['contrat/merchand/contrat.pdf']),
        ];
    }
}
