<?php

namespace App\Enums\Concerns;

/**
 * Enums exposés au front sous la forme [{value, label}] (docs/02-conception-technique.md §3).
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
