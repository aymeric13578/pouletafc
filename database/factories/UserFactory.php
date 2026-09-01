<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'last_name' => fake()->name(),
            'country'=>fake()->randomElement(['Cameroon']),
            'birth'=>fake()->date("Y-m-d"),
            'phone'=>fake()->phoneNumber,
            'city'=>fake()->city,
            'country_code'=>fake()->randomElement(['cm']),
            'ref'=>fake()->unique()->numberBetween(10000, 1000000),
            // `whatsapp` n'existe pas comme colonne sur `users` dans cette base
            // (vérifié via le schéma réel) — l'attribut a été retiré plutôt que
            // deviné. `id_country` reste `null` : la table `countries` est vide
            // en local, une valeur non nulle ferait échouer la contrainte de clé
            // étrangère.
            'id_country'=>null,
            'photo'=>fake()->randomElement(['img/avatars/1.png']),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
