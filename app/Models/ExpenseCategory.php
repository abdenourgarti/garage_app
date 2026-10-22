<?php

namespace App\Models;

use App\Enums\SystemExpenseCategory;
use App\Models\Concerns\Referential;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property SystemExpenseCategory|null $system_key
 * @property bool $is_active
 */
#[Fillable(['name', 'is_active'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_key' => SystemExpenseCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * System categories are used by the Actions: never renamed, deactivated or deleted.
     */
    public function isLocked(): bool
    {
        return $this->system_key !== null;
    }

    public static function system(SystemExpenseCategory $key): self
    {
        return self::where('system_key', $key)->firstOrFail();
    }
}
