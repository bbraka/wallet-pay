<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TopUpProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => true,
            'requires_reference' => $this->faker->boolean(30),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function requiresReference(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_reference' => true,
        ]);
    }
}