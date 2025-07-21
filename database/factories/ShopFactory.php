<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
          
        'name'=>fake()->name(),
        'ref'=>fake()->unique()->numberBetween(10000, 1000000),
        'banner'=>fake()->randomElement(['shopElement/banner/banner.jpg']),
        'phone1'=>fake()->phoneNumber,
        'phone2'=>fake()->phoneNumber,
        'city'=>fake()->city,
        'address'=>fake()->address,
        'email1'=>fake()->email,
        'email2'=>fake()->email,
        'commercial_register'=>fake()->sentence(7,true),
        'logo'=>fake()->randomElement(['shopElement/logo/logo.png']),
        'slug'=>fake()->sentence(7,true),
        'description'=>fake()->sentences(4,true)
        ];
    }
}
