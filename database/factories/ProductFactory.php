<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
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
        'stock_init'=>fake()->numberBetween(100,10000),
        'price'=>fake()->numberBetween(10000,100000),
        'commission'=>fake()->numberBetween(100,100000),
        'bar_code'=>fake()->sentence(7,true),
        'description'=>fake()->sentences(4,true),
        'slug'=>fake()->sentence(7,true),
        'product_image1'=>fake()->randomElement(['productElements/image/image1.jpg']),
        'product_image2'=>fake()->randomElement(['productElements/image/image2.jpg']),
        'product_image3'=>fake()->randomElement(['productElements/image/image3.jpg']),
        'product_video1'=>fake()->randomElement(['productElements/video/video1.mp4']),
        'product_video2'=>fake()->randomElement(['productElements/video/video1.mp4']),
        'product_epaisseur'=>fake()->numberBetween(10,100),
        'product_volume'=>fake()->numberBetween(10,100),
        'product_weigth'=>fake()->numberBetween(10,100),
        'ref'=>fake()->sentence(7,true),
     
        ];
    }
}
