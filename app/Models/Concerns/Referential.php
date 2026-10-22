<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Comportement commun aux référentiels (CLAUDE.md règle 5) :
 * actif / inactif, et verrouillage des éléments utilisés par le code.
 */
trait Referential
{
    /**
     * Only active items: for selectors. History keeps showing inactive ones.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A locked item can't be renamed, deactivated or deleted from the UI.
     */
    public function isLocked(): bool
    {
        return false;
    }
}
