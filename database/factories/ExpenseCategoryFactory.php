<?php

namespace Database\Factories;

use App\Enums\SystemExpenseCategory;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }

    public function system(SystemExpenseCategory $key): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $key->label(),
            'system_key' => $key,
        ]);
    }
}
