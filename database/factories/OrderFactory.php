<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
            'order_type' => $this->faker->randomElement(OrderType::cases()),
            'description' => $this->faker->optional()->paragraph(),
            'user_id' => User::factory(),
            'receiver_user_id' => null,
            'top_up_provider_id' => null,
            'provider_reference' => $this->faker->optional()->uuid(),
        ];
    }

    public function topUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => TopUpProvider::factory(),
            'receiver_user_id' => null,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'receiver_user_id' => User::factory(),
            'top_up_provider_id' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
        ]);
    }
}