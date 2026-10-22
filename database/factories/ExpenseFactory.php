<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'label' => fake()->sentence(3),
            'amount' => '1500.00',
            'spent_at' => today(),
            'payment_method_id' => null,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }
}
