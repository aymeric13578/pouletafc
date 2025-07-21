<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Category;
use App\Models\Caution;
use App\Models\Merchand;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SubCategory;
use Database\Factories\MerchandFactory;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Agent;



class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //  \App\Models\User::factory(100)->create();

         User::factory(10)
         ->has(Agent::factory()->count(1))
         ->has(Merchand::factory()->has(Shop::factory()->count(2)
         ->has(Category::factory(20)->has(SubCategory::factory()->count(10))->hasProduct(10,
         ['id_merchand'=>Merchand::all()->random(1)->first()->id,'id_shop'=>Shop::all()->random(1)->first()->id,'id_sub_category'=>SubCategory::all()->random(1)->first()->id]
         ))
         )->count(1))
         ->create();





        //  \App\Models\Cart::factory(100)->create();
        //  \App\Models\CartItem::factory(100)->create();
        //  \App\Models\Category::factory(100)->create();
        //  \App\Models\Caution::factory(100)->create();
        //  \App\Models\DeliveryAddress::factory(100)->create();
        //  \App\Models\Merchand::factory(100)->create();
        //  \App\Models\Order::factory(100)->create();
        //  \App\Models\Shop::factory(100)->create();
        //  \App\Models\SubCategory::factory(100)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
