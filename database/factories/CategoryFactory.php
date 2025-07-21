<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'=>fake()->name,
            'ref'=>fake()->unique()->numberBetween(10000, 1000000),
            'slug'=>fake()->sentence(10,true),
            'image'=>fake()->randomElement(['categoriesElements/img/img.jpg']),
            'description'=>fake()->sentences(7,true),
            
        ];
    }
}
