<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(Expense::CATEGORIES),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(1, 100) * 1000,
            'expense_date' => today(),
        ];
    }
}
