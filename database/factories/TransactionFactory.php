<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        
        return [
            'user_id' => $user->id,
            'type' => $this->faker->randomElement([TransactionType::CREDIT, TransactionType::DEBIT]),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'status' => TransactionStatus::ACTIVE,
            'description' => $this->faker->sentence(),
            'created_by' => $user->id, // Always set a valid created_by
            'order_id' => null,
        ];
    }

    /**
     * Indicate that the transaction is a credit.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::CREDIT,
        ]);
    }

    /**
     * Indicate that the transaction is a debit.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::DEBIT,
        ]);
    }

    /**
     * Indicate that the transaction is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::CANCELLED,
        ]);
    }

    /**
     * Indicate that the transaction was created by the system (no created_by user).
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => null,
        ]);
    }

    /**
     * Indicate that the transaction is related to an order.
     */
    public function withOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $this->faker->numberBetween(1, 100),
        ]);
    }
}