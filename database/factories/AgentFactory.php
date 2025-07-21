<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_number' =>fake()->numberBetween(1000,10000),
            'agent_name'=>fake()->name,
            'phone'=>fake()->numberBetween(1000000000,10000000000),
            'national_identity_card_number'=>fake()->sentence(7,true),
            'location_plan_file'=>fake()->randomElement(['agentDoc/cni/cni.pdf']),
            'identity_card_file'=>fake()->randomElement(['planLocalisation/plan.pdf']),
            'photo'=>fake()->randomElement(['assets/img/avatars/1.png']),
            'contrat'=>fake()->randomElement(['contrat/contrat.pdf']),
            'city'=>fake()->city,
           
           
        ];
    }
}
